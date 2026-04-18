import subprocess, json, os, requests
import urllib3
urllib3.disable_warnings()

class MagentoMCPClient:
    def __init__(self):
        self.mcp_path = "/root/magento/bold-mcp/mcp-server.js"
        self._token = None

    def get_token(self):
        r = requests.post(os.getenv("MAGENTO_BASE_URL") + "/integration/admin/token",
            json={"username": os.getenv("MAGENTO_ADMIN_USER"), "password": os.getenv("MAGENTO_ADMIN_PASS")},
            verify=False)
        return r.json()

    def call_tool(self, name, params):
        try:
            env = {"MAGENTO_BASE_URL": os.getenv("MAGENTO_BASE_URL"), "MAGENTO_API_TOKEN": self.get_token(), "PATH": "/usr/bin:/usr/local/bin"}
            p = subprocess.Popen(["node", self.mcp_path], stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, env=env)
            req = {"jsonrpc": "2.0", "id": 1, "method": "tools/call", "params": {"name": name, "arguments": params}}
            out, _ = p.communicate(input=(json.dumps(req) + chr(10)).encode(), timeout=15)
            c = json.loads(out.decode()).get("result", {}).get("content", [])
            if c and c[0].get("type") == "text":
                try: return json.loads(c[0]["text"])
                except: return {"text": c[0]["text"]}
        except Exception as e:
            print(f"MCP error ({name}): {e}")
        return {}

    def get_product_stock(self, sku): return self.call_tool("get_product_stock", {"sku": sku})
    def search_products(self, q, n=5): return self.call_tool("search_products", {"query": q, "page_size": n})
    def get_product_by_sku(self, sku): return self.call_tool("get_product_by_sku", {"sku": sku})
    def get_product_by_id(self, id): return self.call_tool("get_product_by_id", {"id": id})
    def get_categories(self, sku): return self.call_tool("get_product_categories", {"sku": sku})
    def get_related(self, sku): return self.call_tool("get_related_products", {"sku": sku})
    def get_attributes(self, sku): return self.call_tool("get_product_attributes", {"sku": sku})
    def get_customer_orders(self, email): return self.call_tool("get_customer_ordered_products_by_email", {"email": email})
    def update_product(self, sku, code, val): return self.call_tool("update_product_attribute", {"sku": sku, "attribute_code": code, "value": val})

mcp = MagentoMCPClient()
