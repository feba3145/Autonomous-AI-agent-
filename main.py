from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
import httpx
import psycopg2
from pgvector.psycopg2 import register_vector
from sentence_transformers import SentenceTransformer
import urllib3
import subprocess
import json
import os

urllib3.disable_warnings()

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)
MAGENTO_TOKEN = os.environ.get("MAGENTO_API_TOKEN", "")
MAGENTO_BASE_URL = os.environ.get("MAGENTO_BASE_URL", "https://172.17.0.1/rest/V1")
MAGENTO_MCP_PATH = os.environ.get("MAGENTO2_MCP_PATH", "/app/mcp-server.js")
MAGENTO_URL = "https://172.17.0.1"

DB_CONFIG = {
    "host": "172.17.0.1",
    "port": 5432,
    "database": "aidb",
    "user": "aiuser",
    "password": "aipassword"
}
model = SentenceTransformer("all-MiniLM-L6-v2")

def call_mcp_tool(tool_name: str, arguments: dict) -> dict:
    init_msg = json.dumps({
        "jsonrpc": "2.0", "id": 1, "method": "initialize",
        "params": {"protocolVersion": "2024-11-05", "capabilities": {}, "clientInfo": {"name": "fastapi", "version": "1.0"}}
    })
    tool_msg = json.dumps({
        "jsonrpc": "2.0", "id": 2, "method": "tools/call",
        "params": {"name": tool_name, "arguments": arguments}
    })
    payload = (init_msg + "\n" + tool_msg + "\n").encode()
    env = {
        **os.environ,
        "MAGENTO_BASE_URL": MAGENTO_BASE_URL,
        "MAGENTO_API_TOKEN": MAGENTO_TOKEN,
        "NODE_TLS_REJECT_UNAUTHORIZED": "0",
    }
    result = subprocess.run(
        ["node", MAGENTO_MCP_PATH],
        input=payload,
        capture_output=True,
        env=env,
        timeout=30
    )
    if result.returncode != 0 and not result.stdout:
        raise HTTPException(status_code=502, detail=f"MCP error: {result.stderr.decode()}")
    lines = [l for l in result.stdout.decode().splitlines() if l.strip().startswith("{")]
    if not lines:
        raise HTTPException(status_code=502, detail="No JSON response from MCP server")
    response = json.loads(lines[-1])
    if "error" in response:
        raise HTTPException(status_code=500, detail=response["error"])
    content = response.get("result", {}).get("content", [])
    if content and content[0].get("type") == "text":
        try:
            return json.loads(content[0]["text"])
        except json.JSONDecodeError:
            return {"raw": content[0]["text"]}
@app.get("/")
def root():
    return {"message": "AI Shopping Assistant API is running"}


@app.get("/health")
def health():
    return {"status": "ok"}


@app.get("/products")
async def get_products():
    all_items = []
    page = 1
    while True:
        data = call_mcp_tool("advanced_product_search", {
            "field": "status", "value": "1",
            "page_size": 100, "current_page": page,
            "sort_field": "entity_id", "sort_direction": "ASC"
        })
        items = data.get("items", [])
        if not items:
            break
        all_items.extend(items)
        if len(all_items) >= data.get("total_count", 0):
            break
        page += 1
    return {"total": len(all_items), "products": all_items}


@app.get("/product-count")
def product_count():
    data = call_mcp_tool("advanced_product_search", {
        "field": "status", "value": "1", "page_size": 1, "current_page": 1
    })
    total = data.get("total_count", 0)
    return {"total_products": total, "message": f"Your Magento store has {total} products"}


@app.get("/product/sku/{sku}")
def get_product_by_sku(sku: str):
    return call_mcp_tool("get_product_by_sku", {"sku": sku})


@app.get("/product/id/{product_id}")
def get_product_by_id(product_id: int):
    return call_mcp_tool("get_product_by_id", {"id": product_id})


@app.get("/stock/{sku}")
async def get_stock(sku: str):
    async with httpx.AsyncClient(verify=False, timeout=30.0) as client:
        response = await client.get(
            f"{MAGENTO_URL}/rest/V1/stockItems/{sku}",
            headers={"Authorization": f"Bearer {MAGENTO_TOKEN}"}
        )
    return response.json()


@app.get("/revenue")
def get_revenue(date_range: str = "this month", status: str = None):
    args = {"date_range": date_range}
    if status:
        args["status"] = status
    return call_mcp_tool("get_revenue", args)


@app.get("/order-count")
def get_order_count(date_range: str = "this month", status: str = None):
    args = {"date_range": date_range}
    if status:
        args["status"] = status
    return call_mcp_tool("get_order_count", args)


@app.get("/product-sales")
def get_product_sales(date_range: str = "this month", status: str = None):
    args = {"date_range": date_range}
    if status:
        args["status"] = status
    return call_mcp_tool("get_product_sales", args)


@app.get("/customer/orders")
def get_customer_orders(email: str):
    return call_mcp_tool("get_customer_ordered_products_by_email", {"email": email})


@app.post("/embed-products")
async def embed_products():
    all_products = []
    page = 1
    async with httpx.AsyncClient(verify=False, timeout=60.0) as client:
        while True:
            response = await client.get(
                f"{MAGENTO_URL}/rest/V1/products?searchCriteria[pageSize]=100&searchCriteria[currentPage]={page}",
                headers={"Authorization": f"Bearer {MAGENTO_TOKEN}"}
            )
            data = response.json()
            items = data.get("items", [])
            if not items:
                break
            all_products.extend(items)
            if len(all_products) >= data.get("total_count", 0):
                break
            page += 1
    conn = psycopg2.connect(**DB_CONFIG)
    register_vector(conn)
    cur = conn.cursor()
    cur.execute("DELETE FROM products")
    for product in all_products:
        name = product.get("name", "")
        sku = product.get("sku", "")
        price = product.get("price", 0)
        description = ""
        for attr in product.get("custom_attributes", []):
            if attr["attribute_code"] == "description":
                description = attr["value"]
                break
        text = f"{name}. {description}. Price: ${price}"
        embedding = model.encode(text).tolist()
        cur.execute(
            "INSERT INTO products (product_id, sku, name, description, price, embedding) VALUES (%s, %s, %s, %s, %s, %s)",
            (product["id"], sku, name, description, price, embedding)
        )
    conn.commit()
    cur.close()
    conn.close()
    return {"message": f"Embedded {len(all_products)} products successfully"}


@app.get("/search")
async def search_products(query: str):
    query_embedding = model.encode(query).tolist()
    conn = psycopg2.connect(**DB_CONFIG)
    register_vector(conn)
    cur = conn.cursor()
    cur.execute(
        "SELECT name, sku, price, description FROM products ORDER BY embedding <=> %s::vector LIMIT 5",
        (query_embedding,)
    )
    results = cur.fetchall()
    cur.close()
    conn.close()
    return [{"name": r[0], "sku": r[1], "price": r[2], "description": r[3]} for r in results]


@app.get("/chat")
async def chat(query: str):
    query_embedding = model.encode(query).tolist()
    conn = psycopg2.connect(**DB_CONFIG)
    register_vector(conn)
    cur = conn.cursor()
    cur.execute(
        "SELECT name, sku, price FROM products ORDER BY embedding <=> %s::vector LIMIT 3",
        (query_embedding,)
    )
    results = cur.fetchall()
    cur.close()
    conn.close()
    product_context = "\n".join([f"- {r[0]} (SKU: {r[1]}, Price: ${r[2]})" for r in results])
    async with httpx.AsyncClient(timeout=300.0) as client:
        response = await client.post(
            "http://host.docker.internal:11434/api/generate",
            json={
                "model": "llama3.2",
                "prompt": f"You are a helpful shopping assistant. The user asked: '{query}'\n\nAvailable products:\n{product_context}\n\nRecommend the best product in 2-3 sentences.",
                "stream": False,
                "options": {"num_predict": 300}
            }
        )
    return {
        "query": query,
        "products_found": [{"name": r[0], "sku": r[1], "price": r[2]} for r in results],
        "ai_response": response.json()["response"]
    }


@app.get("/negotiate")
def negotiate(product_sku: str, offered_price: float):
    product = call_mcp_tool("get_product_by_sku", {"sku": product_sku})
    original_price = float(product.get("price", 0))
    if original_price == 0:
        raise HTTPException(status_code=404, detail=f"Product {product_sku} not found or has no price")
    max_discount = 0.10
    min_price = round(original_price * (1 - max_discount), 2)
    if offered_price >= original_price:
        message = f"Great! The price is ${original_price}. No negotiation needed!"
        final_price = original_price
        status = "accepted"
    elif offered_price >= min_price:
        message = f"Deal! I can offer you this for ${offered_price}. That is {round((1 - offered_price/original_price)*100)}% off!"
        final_price = offered_price
        status = "accepted"
    elif offered_price >= min_price * 0.9:
        counter = round((offered_price + min_price) / 2, 2)
        message = f"I cannot go that low, but how about ${counter}? That is my best offer!"
        final_price = counter
        status = "counter"
    else:
        message = f"Sorry, I cannot go below ${min_price}. That is the lowest I can offer!"
        final_price = min_price
        status = "rejected"
    return {
        "sku": product_sku,
        "product_name": product.get("name", ""),
        "original_price": original_price,
        "offered_price": offered_price,
        "final_price": final_price,
        "status": status,
        "message": message
    }
