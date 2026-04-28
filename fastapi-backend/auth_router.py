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

@router.post("/login")
def login(body: LoginRequest):
    r = requests.post(
        f"{MAGENTO_URL}/integration/customer/token",
        json={"username": body.email, "password": body.password},
        verify=False
    )
    if r.status_code != 200:
        raise HTTPException(status_code=401, detail="Invalid email or password")
    token = r.json()
    r2 = requests.get(
        f"{MAGENTO_URL}/customers/me",
        headers={"Authorization": f"Bearer {token}"},
        verify=False
    )
    customer = r2.json()
    customer_id = customer.get("id")
    firstname = customer.get("firstname", "")
    lastname = customer.get("lastname", "")
    from main import session_store
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
    return {
        "token": token,
        "customer_id": customer_id,
        "email": body.email,
        "firstname": firstname,
        "lastname": lastname,
        "session_id": body.session_id,
        "message": f"Welcome back {firstname}! You are now logged in."
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
