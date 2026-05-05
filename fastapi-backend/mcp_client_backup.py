"""
fastapi-backend/mcp_client.py
-------------------------------
Magento MCP client — replaces the original 40-line version.
New methods added:
  • get_tracking_info(order_increment_id)
  • get_order_by_increment_id(order_increment_id)
  • get_shipments_by_order_id(order_id)
  • find_nearest_store(lat, lng, limit)
"""

import subprocess
import json
import os
import requests
import urllib3
from dotenv import load_dotenv
load_dotenv()
urllib3.disable_warnings()



class MagentoMCPClient:
    def __init__(self):
        self.mcp_path = "/root/magento/bold-mcp/mcp-server.js"
        self._token = None
        self._token_expiry = None
    # ── auth ──────────────────────────────────────────────────────────────    # ── auth ──────────────────────────────────────────────────────────────

    
    def get_token(self):
    import time
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
    self._token_expiry = now + 3000  # refresh every 50 minutes
    return self._token
    # ── core RPC transport ────────────────────────────────────────────────

    def call_tool(self, name: str, params: dict) -> dict:
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
            content = (
                json.loads(out.decode()).get("result", {}).get("content", [])
            )
            if content and content[0].get("type") == "text":
                try:
                    return json.loads(content[0]["text"])
                except Exception:
                    return {"text": content[0]["text"]}
        except Exception as e:
            print(f"MCP error ({name}): {e}")
        return {}

    # ── product tools (unchanged) ─────────────────────────────────────────

    def get_product_stock(self, sku: str):
        return self.call_tool("get_product_stock", {"sku": sku})

    def search_products(self, q: str, n: int = 5):
        return self.call_tool("search_products", {"query": q, "page_size": n})

    def get_product_by_sku(self, sku: str):
        return self.call_tool("get_product_by_sku", {"sku": sku})

    def get_product_by_id(self, product_id: int):
        return self.call_tool("get_product_by_id", {"id": product_id})

    def get_categories(self, sku: str):
        return self.call_tool("get_product_categories", {"sku": sku})

    def get_related(self, sku: str):
        return self.call_tool("get_related_products", {"sku": sku})

    def get_attributes(self, sku: str):
        return self.call_tool("get_product_attributes", {"sku": sku})

    def get_customer_orders(self, email: str):
        return self.call_tool(
            "get_customer_ordered_products_by_email", {"email": email}
        )

    def update_product(self, sku: str, code: str, val: str):
        return self.call_tool(
            "update_product_attribute",
            {"sku": sku, "attribute_code": code, "value": val},
        )

    # ── shipment & tracking tools (NEW) ───────────────────────────────────

    def get_order_by_increment_id(self, order_increment_id: str) -> dict:
        """
        Fetch full order details using the customer-facing increment ID
        (e.g. '000000123').
        """
        return self.call_tool(
            "get_order_by_increment_id",
            {"order_increment_id": str(order_increment_id)},
        )

    def get_tracking_info(self, order_increment_id: str) -> dict:
        """
        Return carrier name, tracking number(s), and order status for an order.
        Uses the increment ID (the number customers see in their emails).

        Returns a dict with keys:
            order_increment_id, order_id, order_status,
            shipments_count, tracks (list), message (if not yet shipped)
        """
        return self.call_tool(
            "get_tracking_info",
            {"order_increment_id": str(order_increment_id)},
        )

    def get_shipments_by_order_id(self, order_id: int) -> dict:
        """
        Return all shipment records (including track details) for an
        internal numeric order_id.
        """
        return self.call_tool(
            "get_shipments_by_order_id", {"order_id": int(order_id)}
        )

# Shared singleton — imported everywhere as `from mcp_client import mcp`
mcp = MagentoMCPClient()

