from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
import requests, os, time
import urllib3
urllib3.disable_warnings()


def sync_magento_addresses(customer_id, token):
    import psycopg2
    try:
        r = requests.get(f"{MAGENTO_URL}/customers/me", headers={"Authorization": f"Bearer {token}"}, verify=False)
        customer = r.json()
        magento_addresses = customer.get("addresses", [])
        if not magento_addresses:
            return
        conn = psycopg2.connect(host=os.getenv('DB_HOST','localhost'), port=os.getenv('DB_PORT',5432), dbname=os.getenv('DB_NAME','aidb'), user=os.getenv('DB_USER','aiuser'), password=os.getenv('DB_PASSWORD','aipassword'))
        cur = conn.cursor()
        cur.execute('DELETE FROM addresses WHERE customer_id = %s', (customer_id,))
        for addr in magento_addresses:
            street = ' '.join(addr.get('street', []))
            city = addr.get('city', '')
            state = addr.get('region', {}).get('region', '')
            postal_code = addr.get('postcode', '')
            telephone = addr.get('telephone', '')
            country = addr.get('country_id', 'IN')
            is_default = addr.get('default_shipping', False)
            if addr.get('default_shipping'):
                label = 'home'
            elif addr.get('default_billing'):
                label = 'office'
            else:
                label = 'address' + str(addr.get('id', ''))
            full_address = ', '.join(filter(None, [street, city, state, postal_code]))
            cur.execute('INSERT INTO addresses (customer_id, label, full_address, street, city, state, postal_code, country, telephone, is_default) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)', (customer_id, label, full_address, street, city, state, postal_code, country, telephone, is_default))
        conn.commit()
        cur.close()
        conn.close()
        print('[ADDRESS SYNC] Done')
    except Exception as e:
        print(f'[ADDRESS SYNC ERROR] {e}')
router = APIRouter(prefix="/auth", tags=["Authentication"])

MAGENTO_URL = os.getenv("MAGENTO_BASE_URL", "https://magento.test/rest/V1")


class LoginRequest(BaseModel):
    email: str
    password: str
    session_id: str


class LoginResponse(BaseModel):
    token: str
    customer_id: int
    email: str
    firstname: str
    lastname: str
    session_id: str


def sync_guest_cart_to_magento(session_id: str, customer_token: str, cart_store: dict):
    """
    After login, push all items from the local guest cart_store
    into the customer's Magento authenticated cart.
    """
    cart_items = cart_store.get(session_id, [])
    if not cart_items:
        return {"synced": 0, "errors": []}

    # Create Magento cart first
    cart_resp = requests.post(
        f"{MAGENTO_URL}/carts/mine",
        headers={"Authorization": f"Bearer {customer_token}", "Content-Type": "application/json"},
        verify=False, timeout=10
    )
    quote_id = cart_resp.json() if cart_resp.status_code in (200, 201) else None
    synced = 0
    errors = []

    for item in cart_items:
        try:
            resp = requests.post(
                f"{MAGENTO_URL}/carts/mine/items",
                headers={
                    "Authorization": f"Bearer {customer_token}",
                    "Content-Type": "application/json"
                },
                json={
                    "cartItem": {
                        "sku": item["sku"],
                        "qty": item["qty"],
                        "quote_id": quote_id   # Magento auto-assigns for authenticated carts
                    }
                },
                verify=False,
                timeout=10
            )
            if resp.status_code in (200, 201):
                synced += 1
            else:
                errors.append({
                    "sku": item["sku"],
                    "error": resp.text
                })
        except Exception as e:
            errors.append({"sku": item["sku"], "error": str(e)})

    # Clear local guest cart after sync
    if synced > 0:
        cart_store[session_id] = []

    return {"synced": synced, "errors": errors}


@router.post("/login")
def login(body: LoginRequest):
    # ── Step 1: Get customer token from Magento ──
    r = requests.post(
        f"{MAGENTO_URL}/integration/customer/token",
        json={"username": body.email, "password": body.password},
        verify=False
    )
    if r.status_code != 200:
        raise HTTPException(status_code=401, detail="Invalid email or password")

    token = r.json()

    # ── Step 2: Fetch customer profile ──
    r2 = requests.get(
        f"{MAGENTO_URL}/customers/me",
        headers={"Authorization": f"Bearer {token}"},
        verify=False
    )
    customer = r2.json()
    customer_id = customer.get("id")
    firstname = customer.get("firstname", "")
    lastname = customer.get("lastname", "")

    # ── Step 3: Update session store ──
    from main import session_store, cart_store

    if body.session_id not in session_store:
        session_store[body.session_id] = {
            "history": [],
            "last_used": time.time(),
            "last_products": [],
            "wishlist_pending": False
        }

    session_store[body.session_id]["logged_in"] = True
    session_store[body.session_id]["customer_id"] = customer_id
    session_store[body.session_id]["customer_token"] = token
    session_store[body.session_id]["last_used"] = time.time()
    session_store[body.session_id]["email"] = body.email
    session_store[body.session_id]["firstname"] = firstname
    session_store[body.session_id]["lastname"] = lastname
    sync_magento_addresses(customer_id, token)

    # ── Step 4: Sync guest cart → Magento authenticated cart ──
    cart_sync = sync_guest_cart_to_magento(body.session_id, token, cart_store)

    return {
        "token": token,
        "customer_id": customer_id,
        "email": body.email,
        "firstname": firstname,
        "lastname": lastname,
        "session_id": body.session_id,
        "message": f"Welcome back {firstname}! You are now logged in.",
        "cart_sync": {
            "items_synced": cart_sync["synced"],
            "errors": cart_sync["errors"],
            "message": (
                f"{cart_sync['synced']} item(s) from your guest cart have been moved to your account."
                if cart_sync["synced"] > 0
                else "No guest cart items to sync."
            )
        }
    }


@router.get("/status/{session_id}")
def auth_status(session_id: str):
    from main import session_store
    session = session_store.get(session_id, {})
    return {
        "session_id": session_id,
        "logged_in": session.get("logged_in", False),
        "customer_id": session.get("customer_id", None)
    }


@router.post("/logout/{session_id}")
def logout(session_id: str):
    from main import session_store
    if session_id in session_store:
        session_store[session_id]["logged_in"] = False
        session_store[session_id]["customer_id"] = None
        session_store[session_id]["customer_token"] = None
    return {"message": "Logged out successfully", "session_id": session_id}

@router.post("/register")
def register(body: dict):
    firstname = body.get("firstname", "")
    lastname = body.get("lastname", "")
    email = body.get("email", "")
    password = body.get("password", "")
    session_id = body.get("session_id", "default")
    if not all([firstname, lastname, email, password]):
        raise HTTPException(status_code=400, detail="All fields are required")
    r = requests.post(
        f"{MAGENTO_URL}/customers",
        json={"customer": {"firstname": firstname, "lastname": lastname, "email": email, "store_id": 1, "website_id": 1}, "password": password},
        verify=False
    )
    if r.status_code not in (200, 201):
        raise HTTPException(status_code=400, detail=r.json().get("message", "Registration failed"))
    customer = r.json()
    customer_id = customer.get("id")
    token_r = requests.post(
        f"{MAGENTO_URL}/integration/customer/token",
        json={"username": email, "password": password},
        verify=False
    )
    token = token_r.json()
    from main import session_store
    if session_id not in session_store:
        session_store[session_id] = {"history": [], "last_used": 0, "last_products": [], "wishlist_pending": False}
    session_store[session_id]["logged_in"] = True
    session_store[session_id]["customer_id"] = customer_id
    session_store[session_id]["customer_token"] = token
    session_store[session_id]["email"] = email
    session_store[session_id]["firstname"] = firstname
    session_store[session_id]["lastname"] = lastname
    return {"token": token, "customer_id": customer_id, "email": email, "firstname": firstname, "lastname": lastname, "session_id": session_id}
