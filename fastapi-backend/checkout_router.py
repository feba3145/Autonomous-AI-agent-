from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from typing import Optional
import requests, os
import urllib3
urllib3.disable_warnings()

router = APIRouter(prefix="/checkout", tags=["Checkout"])
MAGENTO_URL = os.getenv("MAGENTO_BASE_URL", "https://magento.test/rest/V1")

class CheckoutRequest(BaseModel):
    session_id: str
    email: str
    firstname: str
    lastname: str
    street: str
    city: str
    postcode: str
    telephone: str
    region_code: str = "KL"
    country_id: str = "IN"

@router.post("/place-order")
def place_order(body: CheckoutRequest):
    from main import cart_store
    cart = cart_store.get(body.session_id, [])
    if not cart:
        raise HTTPException(status_code=400, detail="Cart is empty")
    # Step 1: Create guest cart
    r = requests.post(f"{MAGENTO_URL}/guest-carts", verify=False)
    cart_id = r.json()
    # Step 2: Add items to Magento cart
    for item in cart:
        requests.post(
            f"{MAGENTO_URL}/guest-carts/{cart_id}/items",
            json={"cartItem": {"sku": item["sku"], "qty": item["qty"], "quote_id": cart_id}},
            verify=False
        )
    # Step 3: Set shipping address
    addr = {
        "email": body.email,
        "firstname": body.firstname,
        "lastname": body.lastname,
        "street": [body.street],
        "city": body.city,
        "region_code": body.region_code,
        "postcode": body.postcode,
        "country_id": body.country_id,
        "telephone": body.telephone
    }
    requests.post(
        f"{MAGENTO_URL}/guest-carts/{cart_id}/shipping-information",
        json={"addressInformation": {"shipping_address": addr, "billing_address": addr, "shipping_carrier_code": "flatrate", "shipping_method_code": "flatrate"}},
        verify=False
    )
    # Step 4: Place order
    r = requests.put(
        f"{MAGENTO_URL}/guest-carts/{cart_id}/order",
        json={"paymentMethod": {"method": "checkmo"}},
        verify=False
    )
    order_id = r.json()
    if not isinstance(order_id, int):
        raise HTTPException(status_code=400, detail=str(order_id))
    # Step 5: Clear cart
    from main import cart_store
    cart_store[body.session_id] = []
    return {
        "order_id": order_id,
        "message": "Order #" + str(order_id) + " placed successfully!",
        "items": cart,
        "shipping_to": body.street + ", " + body.city,
        "payment": "Check/Money Order",
        "total": sum(i["price"] * i["qty"] for i in cart)
    }
