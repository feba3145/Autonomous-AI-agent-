from dotenv import load_dotenv
load_dotenv()

import subprocess
import json
import os
import time
import requests
import urllib3

urllib3.disable_warnings()


class MagentoMCPClient:

    def __init__(self):
        self.mcp_path = "/root/magento/bold-mcp/mcp-server.js"
        self._token = None
        self._token_expiry = None

    def get_token(self):
        now = time.time()
        if self._token and self._token_expiry and now < self._token_expiry:
            return self._token
        r = requests.post(
            os.getenv("MAGENTO_BASE_URL") + "/rest/V1/integration/admin/token",
            json={
                "username": os.getenv("MAGENTO_ADMIN_USER"),
                "password": os.getenv("MAGENTO_ADMIN_PASS"),
            },
            verify=False,
        )
        self._token = r.json()
        self._token_expiry = now + 3000
        return self._token

    def call_tool(self, name, params):
        try:
            env = {
                "MAGENTO_BASE_URL": os.getenv("MAGENTO_BASE_URL"),
                "MAGENTO_API_TOKEN": self.get_token(),
                "PATH": "/usr/bin:/usr/local/bin",
            }
            p = subprocess.Popen(
                ["node", self.mcp_path],
                stdin=subprocess.PIPE,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                env=env,
            )
            req = {
                "jsonrpc": "2.0",
                "id": 1,
                "method": "tools/call",
                "params": {"name": name, "arguments": params},
            }
            out, _ = p.communicate(
                input=(json.dumps(req) + chr(10)).encode(), timeout=20
            )
            content = json.loads(out.decode()).get("result", {}).get("content", [])
            if content and content[0].get("type") == "text":
                try:
                    return json.loads(content[0]["text"])
                except Exception:
                    return {"text": content[0]["text"]}
        except Exception as e:
            print(f"MCP error ({name}): {e}")
        return {}

    def get_product_stock(self, sku):
        return self.call_tool("get_product_stock", {"sku": sku})

    def search_products(self, q, n=5):
        return self.call_tool("search_products", {"query": q, "page_size": n})

    def get_product_by_sku(self, sku):
        return self.call_tool("get_product_by_sku", {"sku": sku})

    def get_product_by_id(self, product_id):
        return self.call_tool("get_product_by_id", {"id": product_id})

    def get_categories(self, sku):
        return self.call_tool("get_product_categories", {"sku": sku})

    def get_related(self, sku):
        return self.call_tool("get_related_products", {"sku": sku})

    def get_attributes(self, sku):
        return self.call_tool("get_product_attributes", {"sku": sku})

    def get_customer_orders(self, email):
        return self.call_tool("get_customer_ordered_products_by_email", {"email": email})

    def update_product(self, sku, code, val):
        return self.call_tool("update_product_attribute", {"sku": sku, "attribute_code": code, "value": val})

    def get_order_by_increment_id(self, order_increment_id):
        return self.call_tool("get_order_by_increment_id", {"order_increment_id": str(order_increment_id)})

    def get_tracking_info(self, order_increment_id):
        return self.call_tool("get_tracking_info", {"order_increment_id": str(order_increment_id)})

    def get_shipments_by_order_id(self, order_id):
        return self.call_tool("get_shipments_by_order_id", {"order_id": int(order_id)})



    def cancel_order(self, order_id):
        return self.call_tool("cancel_order", {"order_id": int(order_id)})

    def create_creditmemo(self, order_id):
        return self.call_tool("create_creditmemo", {"order_id": int(order_id)})

    def submit_review(self, product_id, rating, review_text, nickname):
        return self.call_tool("submit_review", {"product_id": int(product_id), "rating": int(rating), "review_text": review_text, "nickname": nickname})

    def create_shipment(self, order_id, carrier_code, carrier_title, tracking_number, notify=True):
        return self.call_tool("create_shipment", {"order_id": int(order_id), "carrier_code": carrier_code, "carrier_title": carrier_title, "tracking_number": tracking_number, "notify": notify})

    def add_tracking(self, shipment_id, carrier_code, carrier_title, tracking_number):
        return self.call_tool("add_shipment_track", {"shipment_id": int(shipment_id), "carrier_code": carrier_code, "title": carrier_title, "track_number": tracking_number})

    def apply_coupon(self, cart_id, coupon_code):
        return self.call_tool("apply_coupon", {"cart_id": str(cart_id), "coupon_code": str(coupon_code)})
mcp = MagentoMCPClient()
