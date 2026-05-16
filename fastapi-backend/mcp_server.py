from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from mcp_client import MagentoMCPClient
import uvicorn
app = FastAPI(title="MCP Server", version="1.0")
app.add_middleware(CORSMiddleware, allow_origins=["*"], allow_methods=["*"], allow_headers=["*"])
mcp = MagentoMCPClient()
class SearchRequest(BaseModel):
    query: str
    limit: int = 5

class StockRequest(BaseModel):
    sku: str
@app.get("/")
def root():
    return {"status": "MCP Server running on port 8004"}

@app.post("/tools/search_products")
def search_products(body: SearchRequest):
    result = mcp.search_products(body.query, body.limit)
    return {"tool": "search_products", "query": body.query, "result": result}

@app.post("/tools/check_stock")
def check_stock(body: StockRequest):
    result = mcp.get_product_stock(body.sku)
    return {"tool": "check_stock", "sku": body.sku, "result": result}
if __name__ == "__main__":
    uvicorn.run("mcp_server:app", host="0.0.0.0", port=8004, reload=True)
