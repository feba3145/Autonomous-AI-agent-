from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
import requests, os, time
import urllib3
urllib3.disable_warnings()

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
