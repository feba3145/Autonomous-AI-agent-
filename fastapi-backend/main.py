from fastapi import FastAPI
from fastapi.staticfiles import StaticFiles
from fastapi.responses import FileResponse
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import requests
import psycopg2
import os
import time
import threading
from pgvector.psycopg2 import register_vector
from sentence_transformers import SentenceTransformer
from openai import OpenAI
import groq as groq_lib
import os
DEEPSEEK = OpenAI(api_key=os.getenv("DEEPSEEK_API_KEY"), base_url="https://api.deepseek.com")
GROQ = groq_lib.Groq(api_key=os.getenv("GROQ_API_KEY"))
from dotenv import load_dotenv
from address_router import router as address_router
from auth_router import router as auth_router
from checkout_router import router as checkout_router
from mcp_client import mcp
from cms_router import router as cms_router
from shipment_router import router as shipment_router
#from webhook_router import router as webhook_router
from admin_shipment_router import router as admin_shipment_router
from webrtc_router import router as webrtc_router
from stt_router    import router as stt_router
from tts_router    import router as tts_router
import urllib3
urllib3.disable_warnings()
load_dotenv()

app = FastAPI()
app.mount("/static", StaticFiles(directory="/root/magento/fastapi-backend/static"), name="static")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)
app.include_router(address_router)
app.include_router(auth_router)
app.include_router(checkout_router)
app.include_router(cms_router)
app.include_router(shipment_router, prefix="/shipment", tags=["Shipment"])
#app.include_router(webhook_router, tags=["Webhook"])
app.include_router(admin_shipment_router, tags=["Admin Shipment"])
app.include_router(webrtc_router)
app.include_router(stt_router)
app.include_router(tts_router)
model = SentenceTransformer("all-MiniLM-L6-v2")
session_store = {}
SESSION_TIMEOUT = 1800
cart_store = {}
wishlist_store = {}

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

# ─── KEYWORDS ───
BUY_KEYWORDS = ["buy", "purchase", "add to cart", "i want to buy", "get this", "add the", "add it", "add this", "put this", "put it"]
DELIVERY_KEYWORDS = ["deliver to", "send to", "ship to", "delivery to", "deliver at", "send it to my", "use my address", "deliver it to", "delivery to my", "send to my", "ship to my", "my home address", "my office address", "home address", "office address"]
WISHLIST_KEYWORDS = ["wishlist", "save for later", "favourite", "favorite", "add to wishlist"]
ADD_ADDRESS_KEYWORDS = ["add address", "save address", "new address", "add my address", "save my address"]
POLICY_KEYWORDS = ["shipping", "warranty", "privacy", "policy", "faq", "about us", "exchange", "delivery days", "how long", "return policy"]
TRACKING_KEYWORDS = ["where is my order", "track my order", "order status", "tracking", "shipment status", "where is my package", "track order"]
ORDER_HISTORY_KEYWORDS = ["my orders", "show my orders", "order history", "past orders", "previous orders", "what did i order"]
DELETE_ADDRESS_KEYWORDS = ["delete address", "remove address", "forget my address", "delete my"]
EDIT_ADDRESS_KEYWORDS = [
    "edit address", "update address", "change address", "modify address",
    "edit my", "update my address", "change my address"
]
LIST_ADDRESS_KEYWORDS = ["my addresses", "show addresses", "list addresses", "saved addresses", "what addresses"]
COUPON_KEYWORDS = ["coupon", "promo code", "discount code", "apply coupon", "use code", "voucher", "promo"]
CHECKOUT_KEYWORDS = ["checkout", "place order", "confirm order", "place my order", "order now", "buy now", "complete order", "proceed to checkout"]
CANCEL_KEYWORDS = ["cancel my order", "cancel order", "i want to cancel", "stop my order", "cancel this"]
RETURN_KEYWORDS = ["return", "refund", "i want to return", "return my order", "get refund", "money back"]
REVIEW_KEYWORDS = ["review", "rate this", "give review", "write review", "feedback", "rate product"]

# ─── SYSTEM PROMPT ───
SYSTEM_PROMPT = """You are an intelligent shopping assistant.
Recommend products ONLY from the retrieved context below.
Include product name and price in every recommendation.
Never make up products not in the context.
Be friendly, concise and helpful.
If the customer mentions a trip or activity (like hiking, himalaya, beach, gym),
understand what kind of products they need (jackets, thermals, hoodies for cold trips etc.)
and recommend the most relevant products from the context.
If asked to buy, add to cart or place order, process it immediately."""

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

def aria_say(situation, data=""):
    """Make Aria respond naturally using LLM."""
    try:
        prompt = f"""You are Aria, a warm and friendly AI shopping assistant for ShopAI.
Respond in 1-2 short friendly sentences. Be natural, helpful and positive.
Situation: {situation}
Data: {data}
Aria:"""
        return llm_chat(prompt)
    except:
        return data

def aria_say(situation, data=""):
    """Make Aria respond naturally using LLM."""
    try:
        prompt = f"""You are Aria, a warm and friendly AI shopping assistant for ShopAI.
Respond in 1-2 short friendly sentences. Be natural, helpful and positive.
Situation: {situation}
Data: {data}
Aria:"""
        return llm_chat(prompt)
    except:
        return data

def llm_chat(prompt):
    try:
        r = GROQ.chat.completions.create(model="llama-3.3-70b-versatile", messages=[{"role":"user","content":prompt}])
        return r.choices[0].message.content
    except Exception as e:
        print(f"[GROQ ERROR] {e}")
        return ""


def extract_filters_llm(query, history_text=""):
    """Use LLM to extract filters from query."""
    try:
        prompt = f"""Extract shopping filters from this query. Reply ONLY with JSON, nothing else.
Query: "{query}"
Previous context: "{history_text}"
Reply format: {{"max_price": null or number, "min_price": null or number, "color": null or "colorname", "size": null or "size"}}
Examples:
"show black jackets under $60" -> {{"max_price": 60, "min_price": null, "color": "black", "size": null}}
"affordable red tops" -> {{"max_price": 50, "min_price": null, "color": "red", "size": null}}
"show me medium size hoodies" -> {{"max_price": null, "min_price": null, "color": null, "size": "medium"}}
"cheap ones" -> {{"max_price": 30, "min_price": null, "color": null, "size": null}}
JSON:"""
        result = llm_chat(prompt)
        import json, re
        match = re.search(r'\{.*\}', result, re.DOTALL)
        if match:
            return json.loads(match.group())
    except:
        pass
    return {"max_price": None, "min_price": None, "color": None, "size": None}


def llm_rerank(query, products, history_text=""):
    try:
        prod_list = "\n".join([f"{i+1}. {p['name']} - ${p['price']}" for i,p in enumerate(products)])
        prompt = f"""From this product list, select TOP 5 most relevant for the query.
Consider color, size, price preferences.
Query: "{query}"
Context: "{history_text}"
Products:
{prod_list}
Reply ONLY with JSON array of numbers like [3,1,5,2,4]
JSON:"""
        result = llm_chat(prompt)
        import json, re
        match = re.search(r'\[.*\]', result, re.DOTALL)
        if match:
            indices = json.loads(match.group())
            reranked = [products[i-1] for i in indices if 1 <= i <= len(products)]
            return reranked if reranked else products[:5]
    except Exception as e:
        print(f"[RERANK ERROR] {e}")
    return products[:5]


def extract_filters(query):
    filters = {}
    q = query.lower()
    import re
    price_match = re.search(r'under\s+[$₹]?(\d+)', q)
    if price_match:
        filters["max_price"] = float(price_match.group(1))
    return filters


def extract_colors(query):
    colors = ["black","white","red","blue","green","yellow","pink","gray","grey","brown","orange","purple"]
    q = query.lower()
    for c in colors:
        if c in q:
            return c
    return None


def extract_size(query):
    sizes = ["xs","small","medium","large","xl","xxl"]
    q = query.lower()
    for s in sizes:
        if f" {s} " in f" {q} ":
            return s.upper()
    return None


def apply_filters(products, query):
    filters = extract_filters(query)
    color = extract_colors(query)
    size = extract_size(query)
    result = []
    for p in products:
        name = p.get("name","").lower()
        price = float(p.get("price", 0))
        if "max_price" in filters and price > filters["max_price"]:
            continue
        if color and color not in name:
            continue
        if size and f'-{size}-' not in p.get("name","").upper():
            continue
        result.append(p)
    return result if result else products[:5]

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

# ─── BASIC ENDPOINTS ───
@app.get("/")
def root():
    return FileResponse("/root/shopai.html")

@app.get("/health")
def health():
    return {"status": "ok"}

@app.get("/coupons")
def get_coupons():
    conn = psycopg2.connect(host=os.getenv("DB_HOST"), port=os.getenv("DB_PORT"), dbname=os.getenv("DB_NAME"), user=os.getenv("DB_USER"), password=os.getenv("DB_PASSWORD"))
    cur = conn.cursor()
    cur.execute("SELECT code, description, discount_type, discount_amount, min_order_amount FROM coupons WHERE is_active=true ORDER BY discount_amount DESC")
    rows = cur.fetchall()
    cur.close()
    conn.close()
    return [{"code": r[0], "description": r[1], "discount_type": r[2], "discount_amount": float(r[3]), "min_order_amount": float(r[4])} for r in rows]

# ─── SEARCH ───
@app.get("/search")
def search(q: str = "jacket", limit: int = 5):
    embedding = model.encode(q).tolist()
    conn = get_db()
    cur = conn.cursor()
    cur.execute("""
        SELECT sku, name, price, image,
               1 - (embedding <=> %s::vector) AS similarity
        FROM products
        WHERE price > 0
        ORDER BY embedding <=> %s::vector
        LIMIT %s
    """, (embedding, embedding, limit))
    rows = cur.fetchall()
    cur.close()
    conn.close()
    return [{"sku": r[0], "name": r[1], "price": float(r[2] or 0), "similarity": float(r[4]), "image": r[3] if r[3] else None} for r in rows]

# ─── CHAT ───
@app.post("/chat")
def chat(payload: ChatRequest):
    query = payload.query
    embedding = model.encode(query).tolist()
    conn = get_db()
    cur = conn.cursor()
    cur.execute("""
        SELECT sku, name, price, image,
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
    _r_resp = llm_chat(prompt)
    res = type("R", (), {"json": lambda self: {"response": _r_resp}})()
    return {
        "response": res.json().get("response", ""),
        "products": [{"sku": r[0], "name": r[1], "price": float(r[2] or 0), "image": r[3] if r[3] else None} for r in rows]
    }

# ─── RAG CHAT ───
@app.post("/rag-chat")
def rag_chat(payload: ChatRequest):
    query = payload.query
    session_id = payload.session_id

    # ── Init session ──
    if session_id not in session_store:
        session_store[session_id] = {
            "history": [],
            "last_used": time.time(),
            "last_products": [],
            "wishlist_pending": False
        }
    session_store[session_id]["last_used"] = time.time()
    history = session_store[session_id].get("history", [])
    print(f"[RAG_START] query={query}")
    
  
  # ── Early tracking check — must come before buy intent ──
    import re
    order_match = re.search(r'\b0+\d+\b', query)
    is_tracking_early = any(kw in query.lower() for kw in TRACKING_KEYWORDS)
    if is_tracking_early or (order_match and any(kw in query.lower() for kw in ["track", "where", "status", "shipment"])):

        from mcp_client import mcp
        order_id = order_match.group() if order_match else session_store[session_id].get("last_order_id")
        if not order_id:
            # Auto-fetch latest order
            if not session_store[session_id].get("logged_in"):
                return {"answer": "Please login first to track your orders! 🔐", "products": [], "session_id": session_id, "requires_login": True}
            email = session_store[session_id].get("email")
            if email:
                orders = mcp.get_customer_orders(email)
                items = orders.get("items", [])
                if items:
                    order_id = items[0].get("increment_id")
                    session_store[session_id]["last_order_id"] = order_id
            if not order_id:
                return {"answer": "Please provide your order number. Example: track order 000000001", "products": [], "session_id": session_id}
        session_store[session_id]["last_order_id"] = order_id
        result = mcp.get_tracking_info(order_id)
        tracks = result.get("tracks", [])
        if not tracks:
            return {"answer": "Order " + order_id + " is being processed. No shipment yet.", "products": [], "session_id": session_id}
        t = tracks[0]
        status = result.get("order_status","processing")
        status_map = {"pending":"🕐 Order Received","processing":"⚙️ Processing","complete":"✅ Delivered","shipped":"🚚 Shipped","canceled":"❌ Cancelled"}
        status_label = status_map.get(status.lower(), f"📦 {status.title()}")
        t = tracks[0] if tracks else {}
        carrier = t.get("carrier_title","")
        tracking_no = t.get("tracking_number","")
        ship_date = t.get("shipment_date","")[:10] if t.get("shipment_date") else ""
        answer = f"📦 Order **{order_id}**\n\nStatus: {status_label}\n"
        if carrier: answer += f"Carrier: {carrier}\n"
        if tracking_no: answer += f"Tracking No: **{tracking_no}**\n"
        if ship_date: answer += f"Shipped on: {ship_date}\n"
        natural = aria_say(f"Order {order_id} tracking info", answer)
        return {"answer": natural, "products": [], "session_id": session_id}

    is_buy_intent_early = any(kw in query.lower() for kw in BUY_KEYWORDS)
    is_cancel_early = any(kw in query.lower() for kw in CANCEL_KEYWORDS)
    is_coupon = any(kw in query.lower() for kw in COUPON_KEYWORDS)
    if is_coupon:
        import re
        coupon_match = re.search(r"\b[A-Z0-9]{4,15}\b", query.upper())
        if not coupon_match:
            return {"answer": "Please provide your coupon code. Example: apply coupon H20", "products": [], "session_id": session_id}
        coupon_code = coupon_match.group()
        cart = cart_store.get(session_id, [])
        if not cart:
            return {"answer": "Your cart is empty. Please add products first before applying a coupon.", "products": [], "session_id": session_id}
        session_store[session_id]["coupon"] = coupon_code
        return {"answer": "Coupon " + coupon_code + " has been applied to your cart! The discount will be applied at checkout.", "products": [], "session_id": session_id, "coupon": coupon_code}
    if is_cancel_early:
        import re
        order_match = re.search(r"\b0+\d+\b", query)
        order_id = order_match.group() if order_match else None
        if not order_id:
            return {"answer": "Please provide your order number. Example: cancel order 000000004", "products": [], "session_id": session_id}
        from mcp_client import mcp
        result = mcp.cancel_order(order_id)
        if result:
            return {"answer": "Order " + order_id + " has been cancelled successfully.", "products": [], "session_id": session_id}
        return {"answer": "Could not cancel order " + order_id + ". It may already be shipped.", "products": [], "session_id": session_id}
    is_order_history_early = any(kw in query.lower() for kw in ORDER_HISTORY_KEYWORDS)
    if is_order_history_early:
        from mcp_client import mcp
        if not session_store[session_id].get("logged_in", False):
            return {"answer": "Please login to view your orders.", "products": [], "session_id": session_id}
        email = session_store[session_id].get("email", "roni_cost@example.com")
        result = mcp.get_customer_orders(email)
        orders = result.get("items", [])
        if not orders:
            return {"answer": "No orders found for your account.", "products": [], "session_id": session_id}
        order_list = ", ".join(["Order " + o["increment_id"] + " - " + o["status"] + " - $" + str(o["grand_total"]) for o in orders[:5]])
    is_return_early = any(kw in query.lower() for kw in RETURN_KEYWORDS)
    if is_return_early:
        import re
        order_match = re.search(r"\b0+\d+\b", query)
        order_id = order_match.group() if order_match else None
        if not order_id:
            return {"answer": "Please provide your order number. Example: refund order 000000001", "products": [], "session_id": session_id}
        from mcp_client import mcp
        result = mcp.create_creditmemo(order_id)
        if result:
            return {"answer": "Refund initiated for order " + order_id + ". You will receive your money back in 3-5 business days.", "products": [], "session_id": session_id}
        return {"answer": "Could not process refund for order " + order_id + ". Please contact support.", "products": [], "session_id": session_id}
        logged_in = session_store[session_id].get("logged_in", False)
        if not logged_in:
            return {
                "answer": "To complete your purchase, please login first using POST /auth/login with your email and password",
                "products": [],
                "session_id": session_id,
                "requires_login": True
            }

    # ── RAG search ──
    try:
        _ir_resp = llm_chat(f"What product is the user looking for? Query: '{query}'. Reply with ONLY the product category (e.g. bag, jacket, shoes, tshirt). One or two words max. No explanation.")
        interpret_res = type("R", (), {"json": lambda self: {"response": _ir_resp}})()
        raw = interpret_res.json().get("response", query).strip()
        interpreted_query = " ".join(raw.split()[:3]).strip(".,") or query
        if len(interpreted_query) < 3: interpreted_query = query
    except:
        interpreted_query = query
    print(f"[RAG] keywords: {interpreted_query}")
    embedding = model.encode(interpreted_query).tolist()
    # Extract filters using LLM
    history_text = " ".join([m["content"] for m in history[-4:]])
    filters = extract_filters_llm(query, history_text)
    # Build dynamic query
    where_clauses = ["price > 0"]
    params = []
    if filters.get("max_price"): where_clauses.append("price <= %s"); params.append(filters["max_price"])
    if filters.get("min_price"): where_clauses.append("price >= %s"); params.append(filters["min_price"])
    color_filter = filters.get("color")
    if color_filter: where_clauses.append("name ILIKE %s"); params.append(f'%{color_filter}%')
    if filters.get("size"): where_clauses.append("name ILIKE %s"); params.append(f'%{filters["size"]}%')
    where_sql = " AND ".join(where_clauses)
    conn = get_db()
    cur = conn.cursor()
    cur.execute(f"""
        SELECT sku, name, price, image,
               1 - (embedding <=> %s::vector) AS similarity
        FROM products
        WHERE {where_sql}
        ORDER BY embedding <=> %s::vector
        LIMIT 15
    """, [embedding] + params + [embedding])
    rows = cur.fetchall()
    # Fallback: if no results with color filter, try without color
    if not rows and color_filter:
        cur.execute(f"""
            SELECT sku, name, price, image,
                   1 - (embedding <=> %s::vector) AS similarity
            FROM products
            WHERE price > 0
            ORDER BY embedding <=> %s::vector
            LIMIT 5
        """, [embedding, embedding])
        rows = cur.fetchall()
    cur.close()
    conn.close()

    products = [{"sku": r[0], "name": r[1], "price": float(r[2] or 0), "similarity": float(r[4]), "image": r[3] if r[3] else None} for r in rows]

    # ── LLM Rerank ──
    history_text = " ".join([m["content"] for m in history[-4:]])
    saved_prods = session_store[session_id].get("last_products", [])
    products = llm_rerank(query, products, history_text)

    # ── Check real-time stock via MCP ──
    in_stock = []
    for p in products:
        try:
            stock = mcp.get_product_stock(p["sku"])
            if stock.get("is_in_stock", False):
                in_stock.append(p)
        except:
            in_stock.append(p)
    products = in_stock if in_stock else products

    # ✅ FIX: Save products to session EARLY so all intent checks below can use them
    existing_prods = session_store[session_id].get("last_products", [])
    if not saved_prods:
        products = apply_filters(products, query)
        session_store[session_id]["last_products"] = products
    # ── Delivery intent (smart — also handles add+deliver in one message) ──
    llm_index = 0
    llm_intent = "other"
    is_delivery_intent = any(kw in query.lower() for kw in DELIVERY_KEYWORDS)
    is_also_buy = any(kw in query.lower() for kw in BUY_KEYWORDS) and not any(kw in query.lower() for kw in ["home address", "office address", "my address", "deliver it"])
    if is_delivery_intent:
        cid = session_store[session_id].get("customer_id", 0)
        if not cid:
            return {
                "answer": "Please login first so I can find your saved addresses! 🔐",
                "products": [], "session_id": session_id, "requires_login": True
            }
        # ── If user also wants to add to cart, do that first ──
        if is_also_buy:
            last_products = session_store[session_id].get("last_products", [])
            if last_products:
                chosen_idx = max(0, min(llm_index, len(last_products) - 1))
                top = last_products[chosen_idx]
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
        # ── Now resolve delivery address ──
        try:
            from address_router import resolve_address, ResolveRequest
            addr = resolve_address(ResolveRequest(customer_id=cid, query=query))
            cart = cart_store.get(session_id, [])
            total = sum(i["price"] * i["qty"] for i in cart)
            item_msg = f"Added **{top['name']}** to cart. " if is_also_buy and last_products else ""
            return {
                "answer": f"{item_msg}I'll deliver to your **{addr['display_label']}**: {addr['full_address']}. Total: **${round(total,2)}**. Say **yes** to place the order!",
                "products": session_store[session_id].get("last_products", []),
                "session_id": session_id,
                "cart": cart,
                "cart_total": round(total, 2),
                "resolved_address": addr
            }
            session_store[session_id]["resolved_address"] = addr
            # Show coupons before confirming
            try:
                conn2 = get_db()
                cur2 = conn2.cursor()
                cur2.execute("SELECT code, description, discount_type, discount_amount, min_order_amount FROM coupons WHERE is_active=true ORDER BY discount_amount DESC LIMIT 3")
                coupons2 = cur2.fetchall()
                cur2.close()
                conn2.close()
                if coupons2:
                    session_store[session_id]["pending_checkout"] = True
                    session_store[session_id]["available_coupons"] = [{"code":c[0],"description":c[1],"discount_type":c[2],"discount_amount":float(c[3]),"min_order":float(c[4])} for c in coupons2]
                    coupon_list = "\n".join([f"{i+1}. **{c[0]}** — {c[1]}" for i,c in enumerate(coupons2)])
                    cart = cart_store.get(session_id, [])
                    total = sum(i["price"]*i["qty"] for i in cart)
                    return {"answer": f"I'll deliver to your **{addr['display_label']}**: {addr['full_address']}. Total: **${round(total,2)}**\n\n🎟️ Available offers:\n{coupon_list}\n\nSay **apply SAVE10** or **skip** to place order!", "products": [], "session_id": session_id, "cart": cart, "cart_total": round(total,2)}
            except:
                pass
        except Exception as e:
            err = str(e)
            if "404" in err or "No saved address" in err or "matched" in err:
                label_hint = ""
                for kw in DELIVERY_KEYWORDS:
                    if kw in query.lower():
                        rest = query.lower().split(kw, 1)[-1].strip()
                        label_hint = rest.split()[0] if rest else ""
                        break
                return {
                    "answer": f"I don't have a saved address for **{label_hint or 'that location'}** yet. Please click the 📍 button above to add this address!",
                    "products": [],
                    "session_id": session_id,
                    "requires_address": True,
                    "suggested_label": label_hint
                }
            pass
    # ── LLM Intent Detection ──
    last_products = session_store[session_id].get("last_products", [])
    try:
        product_list_text = ", ".join([f"{i+1}. {p['name']}" for i, p in enumerate(last_products)]) if last_products else "none"
        intent_prompt = f"""Classify intent. Products: {product_list_text}. Message: "{query}"
Reply JSON only: {{"intent":"add_to_cart","index":0}} or {{"intent":"search","index":-1}} or {{"intent":"other","index":-1}}
index=0 for first, 1 for second, 2 for third product.
add_to_cart ONLY if user explicitly says: add, buy, purchase, put in cart, order this, take this, i'll take, give me the [specific product].
DO NOT use add_to_cart for: need, want, show, find, looking for, suggest, what about, i need, give me options, what size."""
        _int_resp = llm_chat(intent_prompt)
        intent_res = type("R", (), {"json": lambda self: {"response": _int_resp}})()
        import json as _json, re as _re
        raw_intent = intent_res.json().get("response", "{}").strip()
        json_match = _re.search(r'\{.*\}', raw_intent, _re.DOTALL)
        intent_data = _json.loads(json_match.group()) if json_match else {}
    except Exception as _e:
        print(f"[INTENT ERROR] {_e}")
        intent_data = {}

    llm_intent = intent_data.get("intent", "other")
    llm_index = int(intent_data.get("index", 0))
    # Restore existing products if user is adding to cart
    existing_prods = session_store[session_id].get("last_products", [])
    if existing_prods and llm_intent == "add_to_cart":
        products = existing_prods
    import re as _re2
    ordinal_match = _re2.search(r'\b(1st|first|2nd|second|3rd|third|4th|fourth|5th|fifth)\b', query.lower())
    if ordinal_match:
        ordinal_map = {"1st":0,"first":0,"2nd":1,"second":1,"3rd":2,"third":2,"4th":3,"fourth":3,"5th":4,"fifth":4}
        llm_index = ordinal_map.get(ordinal_match.group(), 0)
        if any(kw in query.lower() for kw in BUY_KEYWORDS + DELIVERY_KEYWORDS):
            llm_intent = "add_to_cart"

    print(f"[DESC_PRE] reached desc check for: {query}")
    # ── Product description intent (before buy intent) ──
    _ql = query.lower()
    _desc_intent = any(p in _ql for p in ["tell me about","tellme about","describe","what is the","details of","more info","product info","about the","info about","features of","specs of"])
    if _desc_intent:
        print(f"[DESC] triggered for: {query}")
        last_prods = session_store[session_id].get("last_products", [])
        query_words = [w.lower() for w in query.split() if len(w)>3]
        def _score(p): return sum(len(w) for w in query_words if w in p["name"].lower())
        best = max(last_prods, key=_score) if last_prods else None
        if not best or _score(best) == 0:
            conn0 = get_db(); cur0 = conn0.cursor()
            cur0.execute("SELECT sku,name,price,image FROM products WHERE name ILIKE %s LIMIT 1",(f"%{' '.join(query_words)}%",))
            r0 = cur0.fetchone(); cur0.close(); conn0.close()
            if r0: best = {"sku":r0[0],"name":r0[1],"price":float(r0[2]),"image":r0[3]}
        if best:
            conn=get_db(); cur=conn.cursor()
            cur.execute("SELECT description FROM products WHERE sku=%s",(best["sku"],))
            row=cur.fetchone(); cur.close(); conn.close()
            import re as _re3
            desc = _re3.sub('<[^<]+?>',' ',row[0] or '').strip() if row and row[0] else "No description available."
            session_store[session_id]["last_products"] = [best]
            return {"answer": f"**{best['name']}** — ${best['price']}\n\n{desc}", "products": [best], "session_id": session_id}
    is_checkout_only = any(kw in query.lower() for kw in CHECKOUT_KEYWORDS)
    # ── Buy intent ──
    if session_store[session_id].get("pending_checkout"):
        q = query.lower().strip()
        if any(w in q for w in ["skip","no","continue","later"]):
            session_store[session_id]["pending_checkout"] = False
            return {"answer": "Proceeding to checkout!", "products": [], "session_id": session_id, "open_checkout": True}
        import re as _rec
        query_upper = query.upper().replace(' ', '')
        code_match = _rec.search(r'\b[A-Z0-9]{4,15}\b', query.upper()) or _rec.search(r'[A-Z0-9]{4,15}', query_upper)
        coupons = session_store[session_id].get("available_coupons", [])
        if "first" in q:
            chosen = coupons[0] if coupons else None
        elif "second" in q:
            chosen = coupons[1] if len(coupons)>1 else None
        elif code_match:
            code = code_match.group()
            chosen = next((c for c in coupons if c["code"]==code), None)
        elif any(w in q for w in ["yes","apply","use","ok"]):
            chosen = coupons[0] if coupons else None
        else:
            chosen = None
        if chosen:
            cart = cart_store.get(session_id, [])
            total = sum(i["price"]*i["qty"] for i in cart)
            if chosen["discount_type"] == "percent":
                discount = round(total * chosen["discount_amount"] / 100, 2)
            else:
                discount = min(chosen["discount_amount"], total)
            final = round(total - discount, 2)
            session_store[session_id]["coupon"] = chosen["code"]
            session_store[session_id]["coupon_discount"] = discount
            session_store[session_id]["pending_checkout"] = False
            return {"answer": f"Coupon **{chosen['code']}** applied!\n\nSubtotal: ${total}\nDiscount: -${discount}\nFinal Total: **${final}**\n\nSay **yes** to place the order!", "products": [], "session_id": session_id}
        else:
            return {"answer": "Coupon not found. Say **skip** to continue or try another code.", "products": [], "session_id": session_id}

    # ── Yes to place order ──
    _qs = query.lower().strip().rstrip("!.")
    if _qs in ["yes","yeah","yep","ok","okay","confirm","place it","do it","yess","yesss","sure","proceed","go ahead","place order"]:
        resolved = session_store[session_id].get("resolved_address")
        if not resolved:
            return {"answer": "Opening checkout!", "products": [], "session_id": session_id, "open_checkout": True}
        if resolved:
            cart = cart_store.get(session_id, [])
            if cart:
                try:
                    from checkout_router import place_order, CheckoutRequest
                    email = session_store[session_id].get("email","")
                    firstname = session_store[session_id].get("firstname","Customer")
                    lastname = session_store[session_id].get("lastname","")
                    req = CheckoutRequest(
                        session_id=session_id,
                        email=email,
                        firstname=firstname,
                        lastname=lastname,
                        street=resolved.get("street", resolved.get("full_address","")),
                        city=resolved.get("city","Kochi"),
                        postcode=resolved.get("postal_code","682001"),
                        telephone=session_store[session_id].get("telephone","9999999999"),
                        region_code="KL",
                        country_id="IN"
                    )
                    result = place_order(req)
                    cart_store[session_id] = []
                    session_store[session_id]["resolved_address"] = None
                    return {"answer": f"Order placed! Delivering to **{resolved.get('display_label','Home')}**: {resolved.get('full_address','')}. Thank you for shopping with ShopAI!", "products": [], "session_id": session_id, "cart": [], "cart_total": 0}
                except Exception as e:
                    return {"answer": f"Could not place order: {str(e)}", "products": [], "session_id": session_id}

    # ── Size filter on existing products ──
    print(f"[SIZE CHECK] query={query}, has_prods={bool(session_store[session_id].get('last_products'))}")
    sizes = ["-xs-","xs ","x-small"," s "," small ","-m-","m ","medium","-l-","l ","large","-xl-","xl ","xxl","2xl"]
    if any(sz in query.lower() for sz in sizes) and session_store[session_id].get("last_products"):
        existing = session_store[session_id]["last_products"]
        size_word = next((sz.strip("-").strip() for sz in sizes if sz in query.lower()), None)
        if size_word:
            filtered = [p for p in existing if size_word.upper() in p["name"].upper() or f"-{size_word.upper()}-" in p["name"].upper()]
            if filtered:
                session_store[session_id]["last_products"] = filtered
                return {"answer": llm_chat(f"Customer wants {size_word} size. Show these options naturally: {[p['name'] for p in filtered]}"), "products": filtered, "session_id": session_id}
    is_buy_intent = (llm_intent == "add_to_cart" or any(kw in query.lower() for kw in BUY_KEYWORDS)) and not is_checkout_only
    is_delivery_also = any(kw in query.lower() for kw in DELIVERY_KEYWORDS)
    if is_buy_intent:
        if not session_store[session_id].get("logged_in", False) and is_delivery_also:
            return {
                "answer": "Please login to continue with delivery and order processing! 🔐",
                "products": session_store[session_id].get("last_products", []),
                "session_id": session_id,
                "requires_login": True
            }
        last_products = session_store[session_id].get("last_products", [])
        if not last_products:
            last_products = products

        # ✅ FIX: if no usable last_products, fall back to products fetched this request
        if not last_products:
            if not products or products[0]["similarity"] < 0.3:
                return {
                    "answer": "Please search for a product first, then say add to cart! For example: i need tote bag",
                    "products": [],
                    "session_id": session_id
                }
            last_products = products
        session_store[session_id]["last_products"] = products

        # Try best name match - score each product by how many query words match
        query_words = [w.lower() for w in query.split() if len(w)>3]
        best_score = 0
        best_match = None
        for p in last_products:
            score = sum(1 for w in query_words if w in p["name"].lower())
            if score > best_score:
                best_score = score
                best_match = p
        if best_match and best_score >= 2:
            top = best_match
        else:
            chosen_idx = max(0, min(llm_index, len(last_products) - 1))
            top = last_products[chosen_idx]
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
            "answer": f"Added {top['name']} to your cart for ${top['price']}. Total: ${round(total, 2)}. Say deliver to my home or office address to place order with Cash on Delivery!",
            "products": last_products,
            "session_id": session_id,
            "cart": cart,
            "cart_total": round(total, 2)
        }

    # ── Wishlist intent: handle numeric reply ──
    if session_store[session_id].get("wishlist_pending") and query.strip().isdigit():
        idx = int(query.strip()) - 1
        last_products = session_store[session_id].get("last_products", [])
        if 0 <= idx < len(last_products):
            chosen = last_products[idx]
            if session_id not in wishlist_store:
                wishlist_store[session_id] = []
            existing = next((i for i in wishlist_store[session_id] if i["sku"] == chosen["sku"]), None)
            if existing:
                return {
                    "answer": f"{chosen['name']} is already in your wishlist!",
                    "products": last_products,
                    "session_id": session_id
                }
            wishlist_store[session_id].append({
                "sku": chosen["sku"],
                "name": chosen["name"],
                "price": chosen["price"]
            })
            session_store[session_id]["wishlist_pending"] = False
            return {
                "answer": f"Saved {chosen['name']} to your wishlist! Want to add more? Just say the number.",
                "products": last_products,
                "session_id": session_id,
                "wishlist": wishlist_store[session_id]
            }

    # ── Wishlist intent ──
    is_wishlist_intent = any(kw in query.lower() for kw in WISHLIST_KEYWORDS)
    if is_wishlist_intent:
        last_products = session_store[session_id].get("last_products", [])
        if not last_products:
            return {
                "answer": "Please search for a product first, then I can save it to your wishlist!",
                "products": [],
                "session_id": session_id
            }
        session_store[session_id]["wishlist_pending"] = True
        product_list = "\n".join([f"{i+1}. {p['name']} — ${p['price']}" for i, p in enumerate(last_products)])
        return {
            "answer": f"Which product would you like to add to your wishlist?\n\n{product_list}\n\nJust reply with the number!",
            "products": last_products,
            "session_id": session_id
        }

    # ── Similarity threshold ──
    # ── Tracking intent ──
    is_tracking = any(kw in query.lower() for kw in TRACKING_KEYWORDS)
    if is_tracking:
        order_id = session_store[session_id].get("last_order_id")
        if not order_id:
            if not session_store[session_id].get("logged_in"):
                return {"answer": "Please login first to track your orders! 🔐", "products": [], "session_id": session_id, "requires_login": True}
            email = session_store[session_id].get("email")
            if email:
                from mcp_client import mcp as _mcp
                from mcp_client import mcp as _mcp
                orders = _mcp.get_customer_orders(email)
                items = orders.get("items",[])
                if items: order_id = items[0].get("increment_id")
            if not order_id:
                return {"answer": "No orders found for your account.", "products": [], "session_id": session_id}
        if result.get("error") or not result:
            return {
                "answer": f"I couldn't find tracking info for order {order_id}. Please check your order number.",
                "products": [],
                "session_id": session_id
            }
        tracks = result.get("tracks", [])
        if not tracks:
            return {
                "answer": f"Your order {order_id} is {result.get('order_status', 'being processed')}. No shipment has been created yet.",
                "products": [],
                "session_id": session_id
            }
        t = tracks[0]
        return {
            "answer": f"Your order {order_id} status: {result.get('order_status')}.\nCarrier: {t.get('carrier_title')}\nTracking Number: {t.get('tracking_number')}\nShipped on: {t.get('shipment_date', '')[:10]}",
            "products": [],
            "session_id": session_id
        }

    # ── Order number in query ──
    import re
    order_match = re.search(r'\b0+\d+\b', query)
    if order_match and any(kw in query.lower() for kw in ["track", "order", "where", "status"]):
        order_id = order_match.group()
        session_store[session_id]["last_order_id"] = order_id
        from mcp_client import mcp
        result = mcp.get_tracking_info(order_id)
        if result.get("error") or not result:
            return {
                "answer": f"I couldn't find order {order_id}. Please check your order number.",
                "products": [],
                "session_id": session_id
            }
        tracks = result.get("tracks", [])
        if not tracks:
            return {
                "answer": f"Your order {order_id} is currently {result.get('order_status', 'being processed')}. No shipment created yet.",
                "products": [],
                "session_id": session_id
            }
        t = tracks[0]
        return {
            "answer": f"Your order {order_id} status: {result.get('order_status')}.\nCarrier: {t.get('carrier_title')}\nTracking Number: {t.get('tracking_number')}\nShipped on: {t.get('shipment_date', '')[:10]}",
            "products": [],
            "session_id": session_id
        }

    # ── Order history intent ──
    is_order_history = any(kw in query.lower() for kw in ORDER_HISTORY_KEYWORDS)
    if is_order_history:
        email = session_store[session_id].get("email", "roni_cost@example.com")
        from mcp_client import mcp
        result = mcp.get_customer_orders(email)
        orders = result.get("items", [])
        if not orders:
            return {
                "answer": "I couldn't find any orders for your account.",
                "products": [],
                "session_id": session_id
            }
        order_list = "\n".join([
            f"• Order {o['increment_id']} — {o['status']} — ${o['grand_total']}"
            for o in orders[:5]
        ])
        natural = aria_say(f"Customer asked for order history. They have {len(orders)} orders.", f"Orders: {order_list}. Tell them their orders naturally and offer to track any of them.")
        return {
            "answer": natural,
            "products": [],
            "session_id": session_id
        }

    is_cancel = any(kw in query.lower() for kw in CANCEL_KEYWORDS)
    if is_cancel:
        from mcp_client import mcp
        import re
        order_match = re.search(r"\b0+\d+\b", query)
        order_id = order_match.group() if order_match else session_store[session_id].get("last_order_id")
        if not order_id:
            return {"answer": "Please provide your order number. Example: cancel order 000000004", "products": [], "session_id": session_id}
        result = mcp.cancel_order(order_id)
        if result and result.get("message") != "You do not have permission":
            return {"answer": "Order " + order_id + " has been cancelled successfully.", "products": [], "session_id": session_id}
        return {"answer": "Could not cancel order " + order_id + ". It may already be shipped or completed.", "products": [], "session_id": session_id}

    is_return = any(kw in query.lower() for kw in RETURN_KEYWORDS)
    if is_return:
        from mcp_client import mcp
        import re
        order_match = re.search(r"\b0+\d+\b", query)
        order_id = order_match.group() if order_match else session_store[session_id].get("last_order_id")
        if not order_id:
            return {"answer": "Please provide your order number. Example: refund order 000000001", "products": [], "session_id": session_id}
        result = mcp.create_creditmemo(order_id)
        if result and not result.get("error"):
            return {"answer": "Refund initiated for order " + order_id + ". You will receive your money back in 3-5 business days.", "products": [], "session_id": session_id}
        return {"answer": "Could not process refund for order " + order_id + ". Please contact support.", "products": [], "session_id": session_id}
    is_policy = any(kw in query.lower() for kw in POLICY_KEYWORDS)
    is_review = any(kw in query.lower() for kw in REVIEW_KEYWORDS)
    if is_review:
        last_products = session_store[session_id].get("last_products", [])
        if not last_products:
            return {"answer": "Please search for a product first, then I can help you review it.", "products": [], "session_id": session_id}
        session_store[session_id]["awaiting_review"] = True
        top = last_products[0]
        return {"answer": "Please rate " + top["name"] + " out of 5 and write your review. Reply like: 5 stars - Great product!", "products": last_products, "session_id": session_id}
    if session_store[session_id].get("awaiting_review") and re.search(r"\d", query):
        from mcp_client import mcp
        rating_match = re.search(r"[1-5]", query)
        rating = int(rating_match.group()) if rating_match else 5
        top = session_store[session_id].get("last_products", [{}])[0]
        nickname = session_store[session_id].get("firstname", "Customer")
        result = mcp.submit_review(top.get("product_id", 1), rating, query, nickname)
        session_store[session_id]["awaiting_review"] = False
        return {"answer": "Thank you for your " + str(rating) + " star review! Your feedback has been submitted.", "products": [], "session_id": session_id}
    if is_policy:
        from cms_router import answer_policy, PolicyRequest
        result = answer_policy(PolicyRequest(query=query))
        return {"answer": result["title"] + ":\n" + result["answer"], "products": [], "session_id": session_id}
    is_list_address = any(kw in query.lower() for kw in LIST_ADDRESS_KEYWORDS)
    if is_list_address:
        cid = session_store[session_id].get("customer_id", 0)
        from address_router import list_addresses
        try:
            addrs = list_addresses(cid)
            lines = []
            for a in addrs:
                default_tag = " ⭐" if a["is_default"] else ""
                lines.append(f"• **{a['display_label']}**{default_tag}: {a['full_address']}")
            addr_text = "\n".join(lines)
            return {
                "answer": f"Your saved addresses:\n\n{addr_text}\n\nSay **'deliver to my [label]'** to use one!",
                "products": [], "session_id": session_id
            }
        except:
            return {
                "answer": "You have no saved addresses yet. Click the 📍 button to add one!",
                "products": [], "session_id": session_id
            }
    is_add_address = any(kw in query.lower() for kw in ADD_ADDRESS_KEYWORDS)
    if is_add_address:
        session_store[session_id]["awaiting_address"] = True
        return {"answer": "Please share your address like this:\nlabel: home\nstreet: 123 MG Road\ncity: Kochi\nstate: Kerala\npostal_code: 682001", "products": [], "session_id": session_id}
    if any(kw in query.lower() for kw in DELETE_ADDRESS_KEYWORDS): session_store[session_id]["awaiting_address"] = False
    if session_store[session_id].get("awaiting_address"):
        try:
            lines = {l.split(":")[0].strip(): l.split(":",1)[1].strip() for l in query.split("\n") if ":" in l}
            from address_router import upsert_address, AddressUpsert
            cid = session_store[session_id].get("customer_id", 0)
            upsert_address(AddressUpsert(customer_id=cid, label=lines.get("label","home"), full_address=", ".join(filter(None,[lines.get("street"),lines.get("city"),lines.get("state"),lines.get("postal_code")])), street=lines.get("street"), city=lines.get("city"), state=lines.get("state"), postal_code=lines.get("postal_code")))
            session_store[session_id]["awaiting_address"] = False
            return {"answer": aria_say("Customer just saved a new address successfully", "Confirm address saved and tell them they can now use it for delivery"), "products": [], "session_id": session_id}
        except Exception as e:
            return {"answer": f"Could not save: {str(e)}", "products": [], "session_id": session_id}
    is_delete_address = any(kw in query.lower() for kw in DELETE_ADDRESS_KEYWORDS)
    if is_delete_address:
        cid = session_store[session_id].get("customer_id", 0)
        from address_router import list_addresses, delete_address
        try:
            addrs = list_addresses(cid)
        except:
            addrs = []
        matched_label = None
        for a in addrs:
            if a["label"] in query.lower() or a["display_label"].lower() in query.lower():
                matched_label = a["label"]
                break
        if matched_label:
            delete_address(cid, matched_label)
            return {
                "answer": f"Your **{matched_label.title()}** address has been deleted.",
                "products": [], "session_id": session_id
            }
        else:
            label_list = ", ".join([f"'{a['display_label']}'" for a in addrs]) if addrs else "none saved"
            return {
                "answer": f"Which address to delete? Your saved labels: {label_list}. Say e.g. *delete my home address*.",
                "products": [], "session_id": session_id
            }
    is_edit_address = any(kw in query.lower() for kw in EDIT_ADDRESS_KEYWORDS)
    if is_edit_address:
        return {
            "answer": "To edit an address, click the 📍 button in the header, select the address card and click **Edit**.",
            "products": [], "session_id": session_id,
            "open_address_manager": True
        }
    # ── Auto Checkout intent ──
    is_checkout = any(kw in query.lower() for kw in CHECKOUT_KEYWORDS)
    is_deliver = any(kw in query.lower() for kw in DELIVERY_KEYWORDS)
    if is_checkout and not is_deliver:
        cart = cart_store.get(session_id, [])
        if not cart:
            return {"answer": "Your cart is empty! Please add products first.", "products": [], "session_id": session_id}
        conn = get_db()
        cur = conn.cursor()
        cur.execute("SELECT code, description, discount_type, discount_amount, min_order_amount FROM coupons WHERE is_active=true ORDER BY discount_amount DESC")
        coupons = cur.fetchall()
        cur.close()
        conn.close()
        total = sum(i["price"]*i["qty"] for i in cart)
        if coupons:
            coupon_list = "\n".join([f"{i+1}. **{c[0]}** — {c[1]}" for i,c in enumerate(coupons[:5])])
            session_store[session_id]["pending_checkout"] = True
            session_store[session_id]["available_coupons"] = [{"code":c[0],"description":c[1],"discount_type":c[2],"discount_amount":float(c[3]),"min_order":float(c[4])} for c in coupons]
            return {"answer": f"Your cart total: **${round(total,2)}**\n\nAvailable offers:\n{coupon_list}\n\nSay **apply SAVE10** or **skip** to continue.", "products": [], "session_id": session_id}
        else:
            return {"answer": "No offers available.", "products": [], "session_id": session_id, "open_checkout": True}

    if is_checkout or is_deliver:
        if not session_store[session_id].get("logged_in", False):
            return {"answer": "Please login first so I can place your order! Click the login button or say your email.", "products": [], "session_id": session_id, "requires_login": True}
        cart = cart_store.get(session_id, [])
        if not cart:
            return {"answer": "Your cart is empty! Tell me what you want to buy first.", "products": [], "session_id": session_id}
        cid = session_store[session_id].get("customer_id", 1)
        # Detect address preference
        addr_label = "home"
        if any(w in query.lower() for w in ["office", "work", "workplace", "letter"]):
            addr_label = "work"
        elif any(w in query.lower() for w in ["home", "house", "residence"]):
            addr_label = "home"
        # Fetch saved addresses
        try:
            from address_router import list_addresses
            addrs = list_addresses(cid)
            addr = next((a for a in addrs if a["label"].lower() == addr_label), addrs[0] if addrs else None)
        except:
            addr = None
        if not addr:
            return {"answer": "I could not find your saved address. Please say: add address\nlabel: home\nstreet: your street\ncity: your city\nstate: Kerala\npostal_code: 682001", "products": [], "session_id": session_id}
        # Place order via checkout router
        try:
            from checkout_router import place_order, CheckoutRequest
            email = session_store[session_id].get("email", "")
            firstname = session_store[session_id].get("firstname", "Customer")
            lastname = session_store[session_id].get("lastname", "")
            req = CheckoutRequest(
                session_id=session_id,
                email=email,
                firstname=firstname,
                lastname=lastname,
                street=addr.get("street", addr.get("full_address","")),
                city=addr.get("city", "Kochi"),
                postcode=addr.get("postal_code", "682001"),
                telephone=session_store[session_id].get("telephone", "9999999999"),
                region_code="KL",
                country_id="IN"
            )
            result = place_order(req)
            cart_store[session_id] = []
            items_text = ", ".join([i["name"] for i in result["items"]])
            return {
                "products": [],
                "session_id": session_id,
                "cart": [],
                "cart_total": 0
            }
        except Exception as e:
            return {"answer": f"Could not place order: {str(e)}. Please try again.", "products": [], "session_id": session_id}

    THRESHOLD = 0.3
    if llm_intent == "add_to_cart":
        pass
    elif not products or products[0]["similarity"] < THRESHOLD:
        return {
            "answer": "I'm sorry, I couldn't find any products matching your request in our catalog. Could you try describing what you're looking for differently? For example, try searching for jackets, hoodies, tees, or workout gear.",
            "products": [],
            "session_id": session_id
        }

    # ── Build prompt with history ──
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

    answer = llm_chat(prompt)

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
    cur.execute("SELECT sku, name, price, image FROM products WHERE price > 0 LIMIT %s OFFSET %s", (limit, offset))
    rows = cur.fetchall()
    cur.close()
    conn.close()
    return [{"sku": r[0], "name": r[1], "price": float(r[2]), "image": r[3] if r[3] else None} for r in rows]

@app.get("/product-count")
def product_count():
    conn = get_db()
    cur = conn.cursor()
    cur.execute("SELECT COUNT(*) FROM products WHERE price > 0")
    count = cur.fetchone()[0]
    cur.close()
    conn.close()
    return {"count": count}

@app.get("/product/detail/{sku}")
def get_product_detail(sku: str):
    conn = get_db()
    cur = conn.cursor()
    cur.execute("SELECT sku, name, price, image, description FROM products WHERE sku = %s", (sku,))
    row = cur.fetchone()
    cur.close()
    conn.close()
    if not row:
        return {"error": "Product not found"}
    import re
    desc = re.sub('<[^<]+?>', ' ', row[4] or '').strip() if row[4] else "No description available."
    return {"sku": row[0], "name": row[1], "price": float(row[2]), "image": row[3], "description": desc}

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

# ─── WISHLIST ENDPOINTS ───
@app.post("/wishlist/add")
def wishlist_add(item: CartItem):
    sid = item.session_id
    if sid not in wishlist_store:
        wishlist_store[sid] = []
    existing = next((i for i in wishlist_store[sid] if i["sku"] == item.sku), None)
    if existing:
        return {"message": "Already in wishlist", "wishlist": wishlist_store[sid]}
    product = mcp.get_product_by_sku(item.sku)
    wishlist_store[sid].append({
        "sku": item.sku,
        "name": product.get("name", item.sku),
        "price": product.get("price", 0)
    })
    return {"message": "Added to wishlist", "wishlist": wishlist_store[sid]}

@app.get("/wishlist/{session_id}")
def wishlist_view(session_id: str):
    wishlist = wishlist_store.get(session_id, [])
    return {"wishlist": wishlist, "item_count": len(wishlist)}

@app.delete("/wishlist/remove")
def wishlist_remove(item: CartRemove):
    sid = item.session_id
    if sid in wishlist_store:
        wishlist_store[sid] = [i for i in wishlist_store[sid] if i["sku"] != item.sku]
    return {"message": "Removed from wishlist", "wishlist": wishlist_store.get(sid, [])}

@app.post("/wishlist/move-to-cart")
def wishlist_move_to_cart(item: CartRemove):
    sid = item.session_id
    wishlist = wishlist_store.get(sid, [])
    product = next((i for i in wishlist if i["sku"] == item.sku), None)
    if not product:
        return {"message": "Item not found in wishlist"}
    wishlist_store[sid] = [i for i in wishlist if i["sku"] != item.sku]
    if sid not in cart_store:
        cart_store[sid] = []
    existing = next((i for i in cart_store[sid] if i["sku"] == item.sku), None)
    if existing:
        existing["qty"] += 1
    else:
        cart_store[sid].append({
            "sku": product["sku"],
            "name": product["name"],
            "price": product["price"],
            "qty": 1
        })
    return {
        "message": f"Moved {product['name']} to cart",
        "cart": cart_store[sid],
        "wishlist": wishlist_store[sid]
    }

from fastapi.responses import StreamingResponse, FileResponse
import httpx

@app.get("/media/{path:path}")
async def proxy_image(path: str):
    import os
    from fastapi.responses import FileResponse, Response
    local = f"/root/magento/fastapi-backend/static/media/{path}"
    if os.path.exists(local):
        return FileResponse(local)
    return Response(status_code=404)
