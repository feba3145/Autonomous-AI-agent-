from fastapi import APIRouter, Request
from datetime import datetime
import threading
import time
import requests
import os

router = APIRouter()
webhook_log = []
last_order_statuses = {}

def check_order_changes():
    while True:
        try:
            base = os.getenv("MAGENTO_BASE_URL")
            user = os.getenv("MAGENTO_ADMIN_USER")
            pwd  = os.getenv("MAGENTO_ADMIN_PASS")
            token_res = requests.post(
                base + "/rest/V1/integration/admin/token",
                json={"username": user, "password": pwd},
                verify=False
            )
            token = token_res.json()
            headers = {"Authorization": f"Bearer {token}"}
            res = requests.get(
                base + "/rest/V1/orders?searchCriteria%5BpageSize%5D=20",
                headers=headers, verify=False
            )
            orders = res.json().get("items", [])
            for order in orders:
                oid = order["increment_id"]
                status = order["status"]
                if oid in last_order_statuses:
                    if last_order_statuses[oid] != status:
                        log_entry = {
                            "time": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                            "order_id": oid,
                            "old_status": last_order_statuses[oid],
                            "new_status": status,
                            "email": order.get("customer_email", "")
                        }
                        webhook_log.append(log_entry)
                        print(f"[STATUS CHANGE] Order {oid}: {last_order_statuses[oid]} → {status}")
                last_order_statuses[oid] = status
        except Exception as e:
            print(f"[WEBHOOK POLL ERROR] {e}")
        time.sleep(30)

threading.Thread(target=check_order_changes, daemon=True).start()

@router.post("/webhook/order")
async def order_webhook(request: Request):
    data = await request.json()
    order_id = data.get("increment_id", "unknown")
    status = data.get("status", "unknown")
    email = data.get("customer_email", "")
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    log_entry = {
        "time": timestamp,
        "order_id": order_id,
        "status": status,
        "email": email
    }
    webhook_log.append(log_entry)
    print(f"[WEBHOOK] Order {order_id} → {status} ({email})")
    return {"received": True, "order_id": order_id, "status": status}

@router.get("/webhook/logs")
def get_webhook_logs():
    return {"logs": webhook_log[-20:], "tracked_orders": last_order_statuses}
