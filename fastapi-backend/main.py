from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import requests
import psycopg2
import os
import time
import threading
from pgvector.psycopg2 import register_vector
from sentence_transformers import SentenceTransformer
from langchain_ollama import OllamaLLM
from dotenv import load_dotenv
from mcp_client import mcp
import urllib3
urllib3.disable_warnings()
load_dotenv()

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

model = SentenceTransformer("all-MiniLM-L6-v2")
session_store = {}
SESSION_TIMEOUT = 1800
cart_store = {}

# ─── MODELS ───
class ChatRequest(BaseModel):
    query: str
    session_id: str = "default"

class CartItem(BaseModel):
    session_id: str
    sku: str
    qty: int = 1

class CartUpdate(BaseModel):
    session_id: str
    sku: str
    qty: int

class CartRemove(BaseModel):
    session_id: str
    sku: str

# ─── SESSION CLEANUP ───
def cleanup_sessions():
    while True:
        time.sleep(300)
        now = time.time()
        expired = [sid for sid, data in session_store.items()
                   if now - data["last_used"] > SESSION_TIMEOUT]
        for sid in expired:
            del session_store[sid]

threading.Thread(target=cleanup_sessions, daemon=True).start()

# ─── DB ───
def get_db():
    conn = psycopg2.connect(
        host=os.getenv("DB_HOST"),
        port=os.getenv("DB_PORT"),
        dbname=os.getenv("DB_NAME"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASSWORD")
    )
    register_vector(conn)
    return conn

SYSTEM_PROMPT = """You are an intelligent shopping assistant.
Recommend products ONLY from the retrieved context below.
Include product name and price in every recommendation.
Never make up products not in the context.
Be friendly, concise and helpful.
If asked to buy, add to cart or place order, tell the customer that feature is coming soon."""

BUY_KEYWORDS = ["buy", "purchase", "order", "add to cart", "i want to buy", "i want this", "get this", "checkout"]

# ─── BASIC ENDPOINTS ───
@app.get("/")
def root():
    return {"status": "AI Shopping Assistant API is running"}

@app.get("/health")
def health():
    return {"status": "ok"}

# ─── SEARCH ───
@app.get("/search")
def search(q: str = "jacket", limit: int = 5):
    embedding = model.encode(q).tolist()
    conn = get_db()
    cur = conn.cursor()
    cur.execute("""
        SELECT sku, name, price,
               1 - (embedding <=> %s::vector) AS similarity
        FROM products
        WHERE price > 0
        ORDER BY embedding <=> %s::vector
        LIMIT %s
    """, (embedding, embedding, limit))
    rows = cur.fetchall()
    cur.close()
    conn.close()
    return [{"sku": r[0], "name": r[1], "price": float(r[2] or 0), "similarity": float(r[3])} for r in rows]

# ─── CHAT ───
@app.post("/chat")
def chat(payload: ChatRequest):
    query = payload.query
    embedding = model.encode(query).tolist()
    conn = get_db()
    cur = conn.cursor()
    cur.execute("""
        SELECT sku, name, price,
               1 - (embedding <=> %s::vector) AS similarity
        FROM products
        WHERE price > 0
        ORDER BY embedding <=> %s::vector
        LIMIT 5
    """, (embedding, embedding))
    rows = cur.fetchall()
    cur.close()
    conn.close()
    products_text = "\n".join([f"- {r[1]} (SKU: {r[0]}, Price: ${r[2]})" for r in rows])
    prompt = f"You are a helpful shopping assistant. Based on these products:\n{products_text}\n\nAnswer this query: {query}"
    res = requests.post(
        "http://localhost:11434/api/generate",
        json={"model": "llama3.2", "prompt": prompt, "stream": False}
    )
    return {"response": res.json().get("response", ""), "products": [{"sku": r[0], "name": r[1], "price": float(r[2] or 0)} for r in rows]}

# ─── RAG CHAT ───
@app.post("/rag-chat")
def rag_chat(payload: ChatRequest):
    query = payload.query
    session_id = payload.session_id

    # Init session
    if session_id not in session_store:
        session_store[session_id] = {
            "history": [],
            "last_used": time.time(),
            "last_products": []   # FIX: track last shown products
        }
    session_store[session_id]["last_used"] = time.time()
    history = session_store[session_id]["history"]

    # RAG search
    embedding = model.encode(query).tolist()
    conn = get_db()
    cur = conn.cursor()
    cur.execute("""
        SELECT sku, name, price,
               1 - (embedding <=> %s::vector) AS similarity
        FROM products
        WHERE price > 0
        ORDER BY embedding <=> %s::vector
        LIMIT 5
    """, (embedding, embedding))
    rows = cur.fetchall()
    cur.close()
    conn.close()

    products = [{"sku": r[0], "name": r[1], "price": float(r[2] or 0), "similarity": float(r[3])} for r in rows]

    # Check real-time stock via Bold MCP
    in_stock = []
    for p in products:
        try:
            stock = mcp.get_product_stock(p["sku"])
            if stock.get("is_in_stock", False):
                in_stock.append(p)
        except:
            in_stock.append(p)
    products = in_stock if in_stock else products

    # FIX: Detect buy intent BEFORE saving last_products
    is_buy_intent = any(kw in query.lower() for kw in BUY_KEYWORDS)

    if is_buy_intent:
        # FIX: use last shown products, not current search results
        last_products = session_store[session_id].get("last_products", [])
        if not last_products:
            return {
                "answer": "Please search for a product first, then I can add it to your cart!",
                "products": [],
                "session_id": session_id
            }
        top = last_products[0]
        if session_id not in cart_store:
            cart_store[session_id] = []
        existing = next((i for i in cart_store[session_id] if i["sku"] == top["sku"]), None)
        if existing:
            existing["qty"] += 1
        else:
            cart_store[session_id].append({
                "sku": top["sku"],
                "name": top["name"],
                "price": top["price"],
                "qty": 1
            })
        cart = cart_store[session_id]
        total = sum(i["price"] * i["qty"] for i in cart)
        history.append({"role": "human", "content": query})
        history.append({"role": "assistant", "content": f"Added {top['name']} to your cart!"})
        return {
            "answer": f"I have added {top['name']} to your cart for ${top['price']}. Total: ${round(total, 2)}. Would you like to checkout or continue shopping?",
            "products": last_products,
            "session_id": session_id,
            "cart": cart,
            "cart_total": round(total, 2)
        }

    # FIX: Save products to session AFTER buy intent check
    session_store[session_id]["last_products"] = products

    # Similarity threshold
    THRESHOLD = 0.5
    if not products or products[0]["similarity"] < THRESHOLD:
        return {
            "answer": "I'm sorry, I couldn't find any products matching your request in our catalog. Could you try describing what you're looking for differently? For example, try searching for jackets, hoodies, tees, or workout gear.",
            "products": [],
            "session_id": session_id
        }

    context = "\n".join([f"- {r['name']} (SKU: {r['sku']}, Price: ${r['price']:.2f})" for r in products])

    history_text = ""
    for msg in history[-6:]:
        role = "Customer" if msg["role"] == "human" else "Assistant"
        history_text += f"{role}: {msg['content']}\n"

    prompt = f"""{SYSTEM_PROMPT}
Retrieved Products:
{context}

Previous Conversation:
{history_text}
Customer: {query}
Assistant:"""

    llm = OllamaLLM(model="llama3.2", base_url="http://localhost:11434")
    answer = llm.invoke(prompt)

    history.append({"role": "human", "content": query})
    history.append({"role": "assistant", "content": answer})

    return {
        "answer": answer,
        "products": products,
        "session_id": session_id
    }

# ─── PRODUCT ENDPOINTS ───
@app.get("/products")
def get_products(limit: int = 20, page: int = 1):
    conn = get_db()
    cur = conn.cursor()
    offset = (page - 1) * limit
    cur.execute("SELECT sku, name, price FROM products WHERE price > 0 LIMIT %s OFFSET %s", (limit, offset))
    rows = cur.fetchall()
    cur.close()
    conn.close()
    return [{"sku": r[0], "name": r[1], "price": float(r[2])} for r in rows]

@app.get("/product-count")
def product_count():
    conn = get_db()
    cur = conn.cursor()
    cur.execute("SELECT COUNT(*) FROM products WHERE price > 0")
    count = cur.fetchone()[0]
    cur.close()
    conn.close()
    return {"count": count}

@app.get("/product/sku/{sku}")
def get_product_by_sku(sku: str):
    result = mcp.get_product_by_sku(sku)
    return result if result else {"error": "Product not found"}

@app.get("/product/id/{product_id}")
def get_product_by_id(product_id: int):
    result = mcp.get_product_by_id(product_id)
    return result if result else {"error": "Product not found"}

@app.get("/stock/{sku}")
def get_stock(sku: str):
    result = mcp.get_product_stock(sku)
    return result if result else {"error": "Stock info not found"}

# ─── CUSTOMER & ORDER ENDPOINTS ───
@app.get("/customer/orders")
def get_customer_orders(email: str):
    result = mcp.get_customer_orders(email)
    return result if result else {"error": "No orders found"}

@app.get("/order-count")
def get_order_count(date_range: str = "today"):
    result = mcp.get_order_count(date_range)
    return result if result else {"error": "Could not fetch order count"}

@app.get("/product-sales")
def get_product_sales(date_range: str = "this month"):
    result = mcp.get_product_sales(date_range)
    return result if result else {"error": "Could not fetch sales"}

# ─── CART ENDPOINTS ───
@app.post("/cart/add")
def cart_add(item: CartItem):
    sid = item.session_id
    if sid not in cart_store:
        cart_store[sid] = []
    existing = next((i for i in cart_store[sid] if i["sku"] == item.sku), None)
    if existing:
        existing["qty"] += item.qty
    else:
        product = mcp.get_product_by_sku(item.sku)
        cart_store[sid].append({
            "sku": item.sku,
            "name": product.get("name", item.sku),
            "price": product.get("price", 0),
            "qty": item.qty
        })
    return {"message": "Added to cart", "cart": cart_store[sid]}

@app.get("/cart/{session_id}")
def cart_view(session_id: str):
    cart = cart_store.get(session_id, [])
    total = sum(i["price"] * i["qty"] for i in cart)
    return {"cart": cart, "total": round(total, 2), "item_count": len(cart)}

@app.delete("/cart/remove")
def cart_remove(item: CartRemove):
    sid = item.session_id
    if sid in cart_store:
        cart_store[sid] = [i for i in cart_store[sid] if i["sku"] != item.sku]
    return {"message": "Removed from cart", "cart": cart_store.get(sid, [])}

@app.put("/cart/update")
def cart_update(item: CartUpdate):
    sid = item.session_id
    if sid in cart_store:
        for i in cart_store[sid]:
            if i["sku"] == item.sku:
                i["qty"] = item.qty
    return {"message": "Cart updated", "cart": cart_store.get(sid, [])}
