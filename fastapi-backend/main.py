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
def get_personalized_recommendations(customer_id: int, limit: int = 5):
    """Get product recommendations based on customer chat history."""
    try:
        conn = get_db()
        cur = conn.cursor()
        # Get products customer interacted with from chat history
        cur.execute("""
            SELECT message FROM chat_history
            WHERE customer_id = %s AND role = 'assistant'
            ORDER BY created_at DESC LIMIT 20
        """, (customer_id,))
        rows = cur.fetchall()
        cur.close(); conn.close()
        if not rows:
            return []
        # Extract product names from assistant messages
        all_text = " ".join([r[0] for r in rows])
        # Ask LLM to extract product categories/types customer liked
        extract_prompt = f"""From this shopping assistant conversation history, extract the product types/categories the customer was interested in.
Return ONLY a comma-separated list of product keywords. Max 5 keywords.
Example: "watches, analog, duffle bag"

History:
{all_text[:2000]}

Keywords:"""
        keywords_raw = llm_chat(extract_prompt)
        keywords = [k.strip() for k in keywords_raw.split(",") if k.strip()][:5]
        if not keywords:
            return []
        print(f"[RECOMMEND] Keywords for customer {customer_id}: {keywords}")
        # Search products using these keywords
        recommended = []
        seen_skus = set()
        conn2 = get_db()
        cur2 = conn2.cursor()
        for kw in keywords:
            embedding = model.encode(kw).tolist()
            cur2.execute("""
                SELECT sku, name, price, image,
                1 - (embedding <=> %s::vector) AS similarity
                FROM products
                WHERE 1 - (embedding <=> %s::vector) > 0.3
                ORDER BY similarity DESC LIMIT 3
            """, (embedding, embedding))
            for row in cur2.fetchall():
                if row[0] not in seen_skus:
                    seen_skus.add(row[0])
                    recommended.append({
                        "sku": row[0], "name": row[1],
                        "price": float(row[2]), "image": row[3],
                        "similarity": float(row[4])
                    })
        cur2.close(); conn2.close()
        # Sort by similarity and return top N
        recommended.sort(key=lambda x: x["similarity"], reverse=True)
        return recommended[:limit]
    except Exception as e:
        print(f"[RECOMMEND ERROR] {e}")
        return []

def summarize_and_save_session(session_id: str, customer_id: int):
    """Summarize a session and save to DB. Called when session goes idle."""
    try:
        conn = get_db()
        cur = conn.cursor()
        # Get all messages for this session
        cur.execute("""
            SELECT role, message FROM chat_history
            WHERE session_id = %s
            ORDER BY created_at ASC
        """, (session_id,))
        rows = cur.fetchall()
        if len(rows) < 2:
            cur.close(); conn.close()
            return
        # Check if summary already exists
        cur.execute("SELECT id FROM session_summaries WHERE session_id = %s", (session_id,))
        if cur.fetchone():
            cur.close(); conn.close()
            return
        # Build conversation text
        convo = "\n".join([f"{r[0]}: {r[1]}" for r in rows])
        # Ask LLM to summarize
        summary = llm_chat(f"""Summarize this shopping conversation in 2-3 sentences.
Focus on: what products customer looked at, what they liked/disliked, what they bought, delivery address used.
Be specific with product names and prices.

Conversation:
{convo}

Summary:""")
        # Save summary
        cur.execute("""
            INSERT INTO session_summaries (customer_id, session_id, summary)
            VALUES (%s, %s, %s)
        """, (customer_id, session_id, summary))
        conn.commit()
        cur.close(); conn.close()
        print(f"[SUMMARY] Saved for session {session_id}: {summary[:100]}")
    except Exception as e:
        print(f"[SUMMARY ERROR] {e}")

def send_order_alert(customer_email: str, items: list, total: float, address: str):
    """Send email alert to admin when order is placed."""
    try:
        import smtplib
        from email.mime.text import MIMEText
        from email.mime.multipart import MIMEMultipart
        import os
        admin_email = os.environ.get("ADMIN_EMAIL", "febatheresa2@gmail.com")
        smtp_user = os.environ.get("SMTP_USER", "")
        smtp_pass = os.environ.get("SMTP_PASS", "")
        if not smtp_user or not smtp_pass:
            print("[EMAIL] SMTP credentials not set — skipping alert")
            return
        items_text = "\n".join([f"  - {i.get('name','?')} x{i.get('qty',1)} @ ${i.get('price',0)}" for i in items])
        body = f"""New Order Placed on ShopAI!

Customer: {customer_email}
Delivery: {address}
Items:
{items_text}

Total: ${total:.2f}

— ShopAI Admin Alert"""
        msg = MIMEMultipart()
        msg['From'] = smtp_user
        msg['To'] = admin_email
        msg['Subject'] = f"New ShopAI Order — ${total:.2f}"
        msg.attach(MIMEText(body, 'plain'))
        with smtplib.SMTP_SSL('smtp.gmail.com', 465) as server:
            server.login(smtp_user, smtp_pass)
            server.send_message(msg)
        print(f"[EMAIL] Order alert sent to {admin_email}")
    except Exception as e:
        print(f"[EMAIL ERROR] {e}")

def save_chat_message(session_id: str, role: str, message: str, customer_id: int = 0):
    try:
        conn = get_db()
        cur = conn.cursor()
        cur.execute(
            "INSERT INTO chat_history (customer_id, session_id, role, message) VALUES (%s, %s, %s, %s)",
            (customer_id or 0, session_id, role, message)
        )
        conn.commit()
        cur.close()
        conn.close()
    except Exception as e:
        print(f"[CHAT_SAVE ERROR] {e}")

def cleanup_sessions():
    while True:
        time.sleep(300)
        now = time.time()
        for sid, data in list(session_store.items()):
            idle_time = now - data.get("last_used", now)
            cid = data.get("customer_id", 0)
            if idle_time > 600 and cid:
                summarize_and_save_session(sid, cid)
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

def llm_chat(prompt, history=None):
    try:
        messages = [{"role": "system", "content": SYSTEM_PROMPT}]
        if history:
            for h in history[-10:]:
                role = h.get("role", "user")
                if role == "human":
                    role = "user"
                messages.append({"role": role, "content": h.get("content", "")})
        messages.append({"role": "user", "content": prompt})
        r = GROQ.chat.completions.create(model="llama-3.3-70b-versatile", messages=messages)
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
    gender = extract_gender(query)
    result = []
    for p in products:
        name = p.get("name","").lower()
        sku = p.get("sku","").lower()
        price = float(p.get("price", 0))
        if "max_price" in filters and price > filters["max_price"]:
            continue
        if color and color not in name:
            continue
        if size and f'-{size}-' not in p.get("name","").upper():
            continue
        if gender == "male" and not (
            any(w in name for w in ["men","man","boy","male"]) or
            any(sku.startswith(p) for p in ["mj","mb","mh","mg","mo","mp"])
        ):
            continue
        if gender == "female" and not (
            any(w in name for w in ["women","woman","girl","female"]) or
            any(sku.startswith(p) for p in ["wj","wb","wh","wg","wo","wp"])
        ):
            continue
        result.append(p)
    return result if result else products[:5]


def extract_gender(query):
    q = query.lower()
    male = ["men","man","male","boy","boys","gents","his","he","gentleman"]
    female = ["women","woman","female","girl","girls","ladies","her","she","lady","womens","mens"]
    unisex = ["unisex","both","all","everyone"]
    for w in unisex:
        if w in q: return "unisex"
    for w in female:
        if w in q: return "female"
    for w in male:
        if w in q: return "male"
    return None


def extract_gender(query):
    q = query.lower()
    male = ["men","man","male","boy","boys","gents","his","he","gentleman"]
    female = ["women","woman","female","girl","girls","ladies","her","she","lady","womens","mens"]
    unisex = ["unisex","both","all","everyone"]
    for w in unisex:
        if w in q: return "unisex"
    for w in female:
        if w in q: return "female"
    for w in male:
        if w in q: return "male"
    return None

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

@app.get("/categories")
def get_categories():
    conn = get_db()
    cur = conn.cursor()
    cur.execute("SELECT id, name, slug, icon, keywords, product_count FROM product_categories WHERE is_active=true ORDER BY product_count DESC")
    rows = cur.fetchall()
    cur.close()
    conn.close()
    return [{"id":r[0],"name":r[1],"slug":r[2],"icon":r[3],"keywords":r[4].split(",") if r[4] else [],"product_count":r[5]} for r in rows]

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
    def _save_and_return(resp: dict) -> dict:
        try:
            cid = session_store.get(payload.session_id, {}).get("customer_id", 0)
            save_chat_message(payload.session_id, "assistant", resp.get("answer", ""), cid)
        except:
            pass
        return resp
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
    _cid = session_store.get(session_id, {}).get("customer_id", 0)
    # ── Load summary + last 10 messages from DB into context if history is empty ──
    if not history and _cid:
        try:
            _conn_h = get_db()
            _cur_h = _conn_h.cursor()
            _cur_h.execute("SELECT summary FROM session_summaries WHERE customer_id = %s ORDER BY created_at DESC LIMIT 1", (_cid,))
            _summary_row = _cur_h.fetchone()
            _summary = _summary_row[0] if _summary_row else None
            _cur_h.execute("SELECT role, message FROM chat_history WHERE customer_id = %s AND session_id != %s ORDER BY created_at DESC LIMIT 10", (_cid, session_id))
            _rows = _cur_h.fetchall()
            _cur_h.close(); _conn_h.close()
            if _summary:
                history.append({"role": "assistant", "content": f"[Previous session summary: {_summary}]"})
                print(f"[CONTEXT] Summary: {_summary[:80]}")
            for _role, _msg in reversed(_rows):
                history.append({"role": "human" if _role == "user" else "assistant", "content": _msg})
            session_store[session_id]["history"] = history
            print(f"[CONTEXT] Loaded {len(_rows)} past messages for customer {_cid}")
        except Exception as _he:
            print(f"[CONTEXT ERROR] {_he}")
    print(f"[RAG_START] query={query}")
    save_chat_message(session_id, "user", query, _cid)
    
  
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
                return _save_and_return({"answer": "Please login first to track your orders! 🔐", "products": [], "session_id": session_id, "requires_login": True})
            email = session_store[session_id].get("email")
            if email:
                orders = mcp.get_customer_orders(email)
                items = orders.get("items", [])
                if items:
                    order_id = items[0].get("increment_id")
                    session_store[session_id]["last_order_id"] = order_id
            if not order_id:
                return _save_and_return({"answer": "Please provide your order number. Example: track order 000000001", "products": [], "session_id": session_id})
        session_store[session_id]["last_order_id"] = order_id
        result = mcp.get_tracking_info(order_id)
        tracks = result.get("tracks", [])
        if not tracks:
            return _save_and_return({"answer": "Order " + order_id + " is being processed. No shipment yet.", "products": [], "session_id": session_id})
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
        return _save_and_return({"answer": natural, "products": [], "session_id": session_id})

    is_buy_intent_early = any(kw in query.lower() for kw in BUY_KEYWORDS)
    is_cancel_early = any(kw in query.lower() for kw in CANCEL_KEYWORDS)
    is_coupon = any(kw in query.lower() for kw in COUPON_KEYWORDS)
    if is_coupon:
        import re
        coupon_match = re.search(r"\b[A-Z0-9]{4,15}\b", query.upper())
        if not coupon_match:
            return _save_and_return({"answer": "Please provide your coupon code. Example: apply coupon H20", "products": [], "session_id": session_id})
        coupon_code = coupon_match.group()
        cart = cart_store.get(session_id, [])
        if not cart:
            return _save_and_return({"answer": "Your cart is empty. Please add products first before applying a coupon.", "products": [], "session_id": session_id})
        session_store[session_id]["coupon"] = coupon_code
        return _save_and_return({"answer": "Coupon " + coupon_code + " has been applied to your cart! The discount will be applied at checkout.", "products": [], "session_id": session_id, "coupon": coupon_code})
    if is_cancel_early:
        import re
        order_match = re.search(r"\b0+\d+\b", query)
        order_id = order_match.group() if order_match else None
        if not order_id:
            return _save_and_return({"answer": "Please provide your order number. Example: cancel order 000000004", "products": [], "session_id": session_id})
        from mcp_client import mcp
        result = mcp.cancel_order(order_id)
        if result:
            return _save_and_return({"answer": "Order " + order_id + " has been cancelled successfully.", "products": [], "session_id": session_id})
        return _save_and_return({"answer": "Could not cancel order " + order_id + ". It may already be shipped.", "products": [], "session_id": session_id})
    is_order_history_early = any(kw in query.lower() for kw in ORDER_HISTORY_KEYWORDS)
    if is_order_history_early:
        from mcp_client import mcp
        if not session_store[session_id].get("logged_in", False):
            return _save_and_return({"answer": "Please login to view your orders.", "products": [], "session_id": session_id})
        email = session_store[session_id].get("email", "roni_cost@example.com")
        result = mcp.get_customer_orders(email)
        orders = result.get("items", [])
        if not orders:
            return _save_and_return({"answer": "No orders found for your account.", "products": [], "session_id": session_id})
        order_list = ", ".join(["Order " + o["increment_id"] + " - " + o["status"] + " - $" + str(o["grand_total"]) for o in orders[:5]])
    is_return_early = any(kw in query.lower() for kw in RETURN_KEYWORDS)
    if is_return_early:
        import re
        order_match = re.search(r"\b0+\d+\b", query)
        order_id = order_match.group() if order_match else None
        if not order_id:
            return _save_and_return({"answer": "Please provide your order number. Example: refund order 000000001", "products": [], "session_id": session_id})
        from mcp_client import mcp
        result = mcp.create_creditmemo(order_id)
        if result:
            return _save_and_return({"answer": "Refund initiated for order " + order_id + ". You will receive your money back in 3-5 business days.", "products": [], "session_id": session_id})
        return _save_and_return({"answer": "Could not process refund for order " + order_id + ". Please contact support.", "products": [], "session_id": session_id})
        logged_in = session_store[session_id].get("logged_in", False)
        if not logged_in:
            return _save_and_return({
                "answer": "To complete your purchase, please login first using POST /auth/login with your email and password",
                "products": [],
                "session_id": session_id,
                "requires_login": True
            })

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
    # ── Delivery + optional add-to-cart intent (fully LLM-driven) ──
    import json as _json2, re as _re3
    llm_index = 0
    llm_intent = "other"
    llm_address_label = ""
    _last_prods_early = session_store[session_id].get("last_products", [])
    _prod_list_early = "\n".join([f"{i+1}. {p['name']} (${p['price']})" for i, p in enumerate(_last_prods_early)]) if _last_prods_early else "none"
    _early_intent_prompt = f"""You are a shopping assistant intent classifier.

Currently shown products:
{_prod_list_early}

User message: "{query}"

Return ONLY a JSON object:
- "intent": "add_to_cart", "search", "deliver", "add_and_deliver", or "other"
- "product_index": 0-based index of product user wants to add. -1 if none.
- "address_label": delivery location like "home", "office". Empty string if none.

Use "add_and_deliver" when user wants both add to cart AND deliver in one message.
Match product by name from the list above.
CRITICAL RULES:
- "need", "want", "show", "give me", "the one", "that one", "medium size", "previous" = ALWAYS "search"
- add_to_cart ONLY for: "add", "buy", "purchase", "put in cart", "order", "take this"
- When in doubt, use search"""

    try:
        _early_raw = llm_chat(_early_intent_prompt).strip()
        _early_match = _re3.search(r'\{.*\}', _early_raw, _re3.DOTALL)
        _early_data = _json2.loads(_early_match.group()) if _early_match else {}
    except:
        _early_data = {}

    llm_intent = _early_data.get("intent", "other")
    llm_index = int(_early_data.get("product_index", 0))
    llm_address_label = _early_data.get("address_label", "").strip().lower()
    print(f"[EARLY INTENT] {llm_intent} | index={llm_index} | address={llm_address_label}")

    # ── Filter from existing products if user refers to current list ──
    import re as _re_ref
    import re as _re_ref
    _ref_words = ["the one", "this one", "that one", "medium size", "small size", "large size", "xl size", "xs size", "the medium", "the small", "the large", "the blue", "the red", "the black", "the white", "the green", "previous", "that jacket", "that bag", "that watch", "that shirt"]
    _last_prods = session_store[session_id].get("last_products", [])
    if any(w in query.lower() for w in _ref_words) and _last_prods and llm_intent not in ("add_to_cart", "deliver", "add_and_deliver"):
        _size_map = {"xs":"XS","small":"S-","medium":"M-","large":"L-","xl":"XL","xxl":"XXL"}
        _color_words = ["blue","red","black","white","green","gray","grey","pink","yellow","orange","purple","brown"]
        _q_lower = query.lower()
        _filtered = list(_last_prods)
        for _col in _color_words:
            if _col in _q_lower:
                _col_f = [p for p in _filtered if _col.upper() in p["name"].upper()]
                if _col_f: _filtered = _col_f
                break
        for _sz_word, _sz_code in _size_map.items():
            if _re_ref.search(r"\b" + _sz_word + r"\b", _q_lower):
                _sz_f = [p for p in _filtered if f"-{_sz_code}" in p["name"]]
                if _sz_f: _filtered = _sz_f
                break
        print(f"[REF FILTER] last={len(_last_prods)} filtered={len(_filtered)}")
        if _filtered and len(_filtered) < len(_last_prods):
            session_store[session_id]["last_products"] = _filtered
            _ans = llm_chat(f"Customer wanted: {query}. Show these naturally: {[p['name'] for p in _filtered]}", history=history)
            return _save_and_return({"answer": _ans, "products": _filtered, "session_id": session_id})

    is_delivery_intent = llm_intent in ("deliver", "add_and_deliver")
    is_also_buy = llm_intent == "add_and_deliver"

    if is_delivery_intent:
        cid = session_store[session_id].get("customer_id", 0)
        if not cid:
            return _save_and_return({
                "answer": "Please login first so I can find your saved addresses! 🔐",
                "products": [], "session_id": session_id, "requires_login": True
            })
        # ── If user also wants to add to cart, do that first using LLM index ──
        if is_also_buy and _last_prods_early:
            top = _last_prods_early[llm_index] if 0 <= llm_index < len(_last_prods_early) else _last_prods_early[0]
            if session_id not in cart_store:
                cart_store[session_id] = []
            existing = next((i for i in cart_store[session_id] if i["sku"] == top["sku"]), None)
            if existing:
                existing["qty"] += 1
            else:
                cart_store[session_id].append({"sku": top["sku"], "name": top["name"], "price": top["price"], "qty": 1})
            print(f"[ADD+DELIVER] Added {top['name']} to cart")
        # ── Now resolve delivery address ──
        try:
            from address_router import resolve_address, ResolveRequest
            _addr_query = llm_address_label if llm_address_label else query
            addr = resolve_address(ResolveRequest(customer_id=cid, query=_addr_query))
            print(f"[RESOLVE] using query={_addr_query}")
            cart = cart_store.get(session_id, [])
            total = sum(i["price"] * i["qty"] for i in cart)
            item_msg = f"Added **{top['name']}** to cart. " if is_also_buy and _last_prods_early else ""
            return _save_and_return({
                "answer": f"{item_msg}I'll deliver to your **{addr['display_label']}**: {addr['full_address']}. Total: **${round(total,2)}**. Say **yes** to place the order!",
                "products": session_store[session_id].get("last_products", []),
                "session_id": session_id,
                "cart": cart,
                "cart_total": round(total, 2),
                "resolved_address": addr
            })
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
                    return _save_and_return({"answer": f"I'll deliver to your **{addr['display_label']}**: {addr['full_address']}. Total: **${round(total,2)}**\n\n🎟️ Available offers:\n{coupon_list}\n\nSay **apply SAVE10** or **skip** to place order!", "products": [], "session_id": session_id, "cart": cart, "cart_total": round(total,2)})
            except:
                pass
        except Exception as e:
            err = str(e)
            print(f"[ADDRESS ERROR] {err}")
            if "404" in err or "No saved address" in err or "matched" in err:
                return _save_and_return({
                    "answer": f"I don't have a saved address for **{llm_address_label or 'that location'}** yet. Please click the 📍 button above to add this address!",
                    "products": [],
                    "session_id": session_id,
                    "requires_address": True,
                    "suggested_label": llm_address_label
                })
            print(f"[ADDRESS UNEXPECTED ERROR] {err}")
            pass
    # ── LLM Intent Detection (fully LLM-driven, no hardcoding) ──
    import json as _json, re as _re
    last_products = session_store[session_id].get("last_products", [])
    product_list_text = "\n".join([f"{i+1}. {p['name']} (${p['price']})" for i, p in enumerate(last_products)]) if last_products else "none"

    intent_prompt = f"""You are a shopping assistant intent classifier. Given the user message and the list of currently shown products, return a JSON object.

Currently shown products:
{product_list_text}

User message: "{query}"

Return ONLY a JSON object with these fields:
- "intent": one of "add_to_cart", "search", "deliver", "other"
- "product_index": (integer, 0-based) index of the product the user is referring to from the list above. Use -1 if not referring to a specific product or if intent is search.
- "address_label": (string) delivery location label like "home", "office", "work" etc. Empty string if not a delivery intent.

Rules:
- "add_to_cart" ONLY when user explicitly says: add, buy, purchase, put in cart, order, take this, I'll take
- "search" when user says: need, want, show, find, looking for, suggest, what about, I need, give me, previous, the one, similar
- "deliver" when user wants to deliver or ship to an address
- "I need" and "I want" are ALWAYS "search", never "add_to_cart"
- Match product by name — pick the closest match from the list above
- If user names a product not in the list, set product_index to -1

Reply with ONLY the JSON, no explanation."""

    try:
        raw_intent = llm_chat(intent_prompt).strip()
        json_match = _re.search(r'{.*}', raw_intent, _re.DOTALL)
        intent_data = _json.loads(json_match.group()) if json_match else {}
    except Exception as _e:
        print(f"[INTENT ERROR] {_e}")
        intent_data = {}

    llm_intent = intent_data.get("intent", "other")
    llm_index = int(intent_data.get("product_index", 0))
    llm_address_label = intent_data.get("address_label", "").strip().lower()
    print(f"[INTENT] {llm_intent} | index={llm_index} | address={llm_address_label} | query={query}")

    # Restore existing products if user is adding to cart
    if last_products and llm_intent == "add_to_cart":
        products = last_products

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
            return _save_and_return({"answer": f"**{best['name']}** — ${best['price']}\n\n{desc}", "products": [best], "session_id": session_id})
    # ── Personalized recommendations intent ──
    _rec_keywords = ["recommend", "suggestion", "what should i buy", "what do you suggest", "based on my history", "what did i like", "personalized", "for me", "what would i like", "what suits me"]
    if any(kw in query.lower() for kw in _rec_keywords):
        _cid_rec = session_store[session_id].get("customer_id", 0)
        if _cid_rec:
            rec_products = get_personalized_recommendations(_cid_rec)
            if rec_products:
                session_store[session_id]["last_products"] = rec_products
                answer = llm_chat(f"Based on this customer browsing history, recommend these products naturally and explain why they might like them: {[p['name'] for p in rec_products]}", history=history)
                return _save_and_return({"answer": answer, "products": rec_products, "session_id": session_id})
        return _save_and_return({"answer": "Please login so I can give you personalized recommendations! 🔐", "products": [], "session_id": session_id})

    # ── Store categories intent ──
    if any(kw in query.lower() for kw in ["categories","what do you sell","what categories","what products","store have","can i buy","do you sell","what can i","available products","what all product","all product","all products","what product","products do you","you have","what you have","what items","all items"]):
        conn_cat = get_db()
        cur_cat = conn_cat.cursor()
        cur_cat.execute("SELECT name, icon, product_count FROM product_categories WHERE is_active=true ORDER BY product_count DESC")
        cats = cur_cat.fetchall()
        cur_cat.close()
        conn_cat.close()
        cat_list = "\n".join([f"{i+1}. {c[1]} **{c[0]}** — {c[2]} products" for i,c in enumerate(cats)])
        return _save_and_return({"answer": f"🛍️ Here are all our product categories:\n\n{cat_list}\n\nSay **show tops**, **show jackets** etc. to browse!", "products": [], "session_id": session_id})
    is_checkout_only = any(kw in query.lower() for kw in CHECKOUT_KEYWORDS)
    # ── Buy intent ──
    if session_store[session_id].get("pending_checkout"):
        q = query.lower().strip()
        if any(w in q for w in ["skip","no","continue","later"]):
            session_store[session_id]["pending_checkout"] = False
            return _save_and_return({"answer": "Proceeding to checkout!", "products": [], "session_id": session_id, "open_checkout": True})
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
            return _save_and_return({"answer": f"Coupon **{chosen['code']}** applied!\n\nSubtotal: ${total}\nDiscount: -${discount}\nFinal Total: **${final}**\n\nSay **yes** to place the order!", "products": [], "session_id": session_id})
        else:
            return _save_and_return({"answer": "Coupon not found. Say **skip** to continue or try another code.", "products": [], "session_id": session_id})

    # ── Yes to place order ──
    _qs = query.lower().strip().rstrip("!.")
    if _qs in ["yes","yeah","yep","ok","okay","confirm","place it","do it","yess","yesss","sure","proceed","go ahead","place order"]:
        resolved = session_store[session_id].get("resolved_address")
        if not resolved:
            return _save_and_return({"answer": "Opening checkout!", "products": [], "session_id": session_id, "open_checkout": True})
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
                    _order_items = cart_store.get(session_id, [])
                    _order_total = sum(i["price"]*i["qty"] for i in _order_items)
                    _cust_email = session_store[session_id].get("email", "unknown")
                    send_order_alert(_cust_email, _order_items, _order_total, resolved.get("full_address",""))
                    cart_store[session_id] = []
                    session_store[session_id]["resolved_address"] = None
                    return _save_and_return({"answer": f"Order placed! Delivering to **{resolved.get('display_label','Home')}**: {resolved.get('full_address','')}. Thank you for shopping with ShopAI!", "products": [], "session_id": session_id, "cart": [], "cart_total": 0})
                except Exception as e:
                    return _save_and_return({"answer": f"Could not place order: {str(e)}", "products": [], "session_id": session_id})

    # ── Size filter on existing products ──
    # Only triggers on explicit size words, skips if user is adding to cart
    import re as _re_size
    _size_match = _re_size.search(r'\b(x-small|small|medium|large|x-large|xxl|2xl|\bxs\b|\bxl\b)\b|\bsize\s+[smlx]{1,3}\b', query.lower())
    _is_cart_action = any(w in query.lower() for w in ["add", "buy", "cart", "order", "purchase"])
    if _size_match and not _is_cart_action and session_store[session_id].get("last_products"):
        existing = session_store[session_id]["last_products"]
        size_word = _size_match.group().strip().split()[-1].upper()
        filtered = [p for p in existing if size_word in p["name"].upper()]
        if filtered:
            session_store[session_id]["last_products"] = filtered
            return _save_and_return({"answer": llm_chat(f"Customer wants {size_word} size. Show these options naturally: {[p['name'] for p in filtered]}"), "products": filtered, "session_id": session_id})
    is_buy_intent = (llm_intent == "add_to_cart" or any(kw in query.lower() for kw in BUY_KEYWORDS)) and not is_checkout_only
    is_delivery_also = any(kw in query.lower() for kw in DELIVERY_KEYWORDS)
    if is_buy_intent:
        if not session_store[session_id].get("logged_in", False) and is_delivery_also:
            return _save_and_return({
                "answer": "Please login to continue with delivery and order processing! 🔐",
                "products": session_store[session_id].get("last_products", []),
                "session_id": session_id,
                "requires_login": True
            })
        last_products = session_store[session_id].get("last_products", [])
        if not last_products:
            last_products = products

        # ✅ FIX: if no usable last_products, fall back to products fetched this request
        if not last_products:
            if not products or products[0]["similarity"] < 0.3:
                return _save_and_return({
                    "answer": "Please search for a product first, then say add to cart! For example: i need tote bag",
                    "products": [],
                    "session_id": session_id
                })
            last_products = products
        session_store[session_id]["last_products"] = products

        # ── LLM already matched the product by name, just use its index ──
        if 0 <= llm_index < len(last_products):
            top = last_products[llm_index]
        else:
            top = last_products[0]
        print(f"[ADD TO CART] Picked: {top['name']} (index={llm_index})")
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
        return _save_and_return({
            "answer": f"Added {top['name']} to your cart for ${top['price']}. Total: ${round(total, 2)}. Say deliver to my home or office address to place order with Cash on Delivery!",
            "products": last_products,
            "session_id": session_id,
            "cart": cart,
            "cart_total": round(total, 2)
        })

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
                return _save_and_return({
                    "answer": f"{chosen['name']} is already in your wishlist!",
                    "products": last_products,
                    "session_id": session_id
                })
            wishlist_store[session_id].append({
                "sku": chosen["sku"],
                "name": chosen["name"],
                "price": chosen["price"]
            })
            session_store[session_id]["wishlist_pending"] = False
            return _save_and_return({
                "answer": f"Saved {chosen['name']} to your wishlist! Want to add more? Just say the number.",
                "products": last_products,
                "session_id": session_id,
                "wishlist": wishlist_store[session_id]
            })

    # ── Wishlist intent ──
    is_wishlist_intent = any(kw in query.lower() for kw in WISHLIST_KEYWORDS)
    if is_wishlist_intent:
        last_products = session_store[session_id].get("last_products", [])
        if not last_products:
            return _save_and_return({
                "answer": "Please search for a product first, then I can save it to your wishlist!",
                "products": [],
                "session_id": session_id
            })
        session_store[session_id]["wishlist_pending"] = True
        product_list = "\n".join([f"{i+1}. {p['name']} — ${p['price']}" for i, p in enumerate(last_products)])
        return _save_and_return({
            "answer": f"Which product would you like to add to your wishlist?\n\n{product_list}\n\nJust reply with the number!",
            "products": last_products,
            "session_id": session_id
        })

    # ── Similarity threshold ──
    # ── Tracking intent ──
    is_tracking = any(kw in query.lower() for kw in TRACKING_KEYWORDS)
    if is_tracking:
        order_id = session_store[session_id].get("last_order_id")
        if not order_id:
            if not session_store[session_id].get("logged_in"):
                return _save_and_return({"answer": "Please login first to track your orders! 🔐", "products": [], "session_id": session_id, "requires_login": True})
            email = session_store[session_id].get("email")
            if email:
                from mcp_client import mcp as _mcp
                from mcp_client import mcp as _mcp
                orders = _mcp.get_customer_orders(email)
                items = orders.get("items",[])
                if items: order_id = items[0].get("increment_id")
            if not order_id:
                return _save_and_return({"answer": "No orders found for your account.", "products": [], "session_id": session_id})
        if result.get("error") or not result:
            return _save_and_return({
                "answer": f"I couldn't find tracking info for order {order_id}. Please check your order number.",
                "products": [],
                "session_id": session_id
            })
        tracks = result.get("tracks", [])
        if not tracks:
            return _save_and_return({
                "answer": f"Your order {order_id} is {result.get('order_status', 'being processed')}. No shipment has been created yet.",
                "products": [],
                "session_id": session_id
            })
        t = tracks[0]
        return _save_and_return({
            "answer": f"Your order {order_id} status: {result.get('order_status')}.\nCarrier: {t.get('carrier_title')}\nTracking Number: {t.get('tracking_number')}\nShipped on: {t.get('shipment_date', '')[:10]}",
            "products": [],
            "session_id": session_id
        })

    # ── Order number in query ──
    import re
    order_match = re.search(r'\b0+\d+\b', query)
    if order_match and any(kw in query.lower() for kw in ["track", "order", "where", "status"]):
        order_id = order_match.group()
        session_store[session_id]["last_order_id"] = order_id
        from mcp_client import mcp
        result = mcp.get_tracking_info(order_id)
        if result.get("error") or not result:
            return _save_and_return({
                "answer": f"I couldn't find order {order_id}. Please check your order number.",
                "products": [],
                "session_id": session_id
            })
        tracks = result.get("tracks", [])
        if not tracks:
            return _save_and_return({
                "answer": f"Your order {order_id} is currently {result.get('order_status', 'being processed')}. No shipment created yet.",
                "products": [],
                "session_id": session_id
            })
        t = tracks[0]
        return _save_and_return({
            "answer": f"Your order {order_id} status: {result.get('order_status')}.\nCarrier: {t.get('carrier_title')}\nTracking Number: {t.get('tracking_number')}\nShipped on: {t.get('shipment_date', '')[:10]}",
            "products": [],
            "session_id": session_id
        })

    # ── Order history intent ──
    is_order_history = any(kw in query.lower() for kw in ORDER_HISTORY_KEYWORDS)
    if is_order_history:
        email = session_store[session_id].get("email", "roni_cost@example.com")
        from mcp_client import mcp
        result = mcp.get_customer_orders(email)
        orders = result.get("items", [])
        if not orders:
            return _save_and_return({
                "answer": "I couldn't find any orders for your account.",
                "products": [],
                "session_id": session_id
            })
        order_list = "\n".join([
            f"• Order {o['increment_id']} — {o['status']} — ${o['grand_total']}"
            for o in orders[:5]
        ])
        natural = aria_say(f"Customer asked for order history. They have {len(orders)} orders.", f"Orders: {order_list}. Tell them their orders naturally and offer to track any of them.")
        return _save_and_return({
            "answer": natural,
            "products": [],
            "session_id": session_id
        })

    is_cancel = any(kw in query.lower() for kw in CANCEL_KEYWORDS)
    if is_cancel:
        from mcp_client import mcp
        import re
        order_match = re.search(r"\b0+\d+\b", query)
        order_id = order_match.group() if order_match else session_store[session_id].get("last_order_id")
        if not order_id:
            return _save_and_return({"answer": "Please provide your order number. Example: cancel order 000000004", "products": [], "session_id": session_id})
        result = mcp.cancel_order(order_id)
        if result and result.get("message") != "You do not have permission":
            return _save_and_return({"answer": "Order " + order_id + " has been cancelled successfully.", "products": [], "session_id": session_id})
        return _save_and_return({"answer": "Could not cancel order " + order_id + ". It may already be shipped or completed.", "products": [], "session_id": session_id})

    is_return = any(kw in query.lower() for kw in RETURN_KEYWORDS)
    if is_return:
        from mcp_client import mcp
        import re
        order_match = re.search(r"\b0+\d+\b", query)
        order_id = order_match.group() if order_match else session_store[session_id].get("last_order_id")
        if not order_id:
            return _save_and_return({"answer": "Please provide your order number. Example: refund order 000000001", "products": [], "session_id": session_id})
        result = mcp.create_creditmemo(order_id)
        if result and not result.get("error"):
            return _save_and_return({"answer": "Refund initiated for order " + order_id + ". You will receive your money back in 3-5 business days.", "products": [], "session_id": session_id})
        return _save_and_return({"answer": "Could not process refund for order " + order_id + ". Please contact support.", "products": [], "session_id": session_id})
    is_policy = any(kw in query.lower() for kw in POLICY_KEYWORDS)
    is_review = any(kw in query.lower() for kw in REVIEW_KEYWORDS)
    if is_review:
        last_products = session_store[session_id].get("last_products", [])
        if not last_products:
            return _save_and_return({"answer": "Please search for a product first, then I can help you review it.", "products": [], "session_id": session_id})
        session_store[session_id]["awaiting_review"] = True
        top = last_products[0]
        return _save_and_return({"answer": "Please rate " + top["name"] + " out of 5 and write your review. Reply like: 5 stars - Great product!", "products": last_products, "session_id": session_id})
    if session_store[session_id].get("awaiting_review") and re.search(r"\d", query):
        from mcp_client import mcp
        rating_match = re.search(r"[1-5]", query)
        rating = int(rating_match.group()) if rating_match else 5
        top = session_store[session_id].get("last_products", [{}])[0]
        nickname = session_store[session_id].get("firstname", "Customer")
        result = mcp.submit_review(top.get("product_id", 1), rating, query, nickname)
        session_store[session_id]["awaiting_review"] = False
        return _save_and_return({"answer": "Thank you for your " + str(rating) + " star review! Your feedback has been submitted.", "products": [], "session_id": session_id})
    if is_policy:
        from cms_router import answer_policy, PolicyRequest
        result = answer_policy(PolicyRequest(query=query))
        return _save_and_return({"answer": result["title"] + ":\n" + result["answer"], "products": [], "session_id": session_id})
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
            return _save_and_return({
                "answer": f"Your saved addresses:\n\n{addr_text}\n\nSay **'deliver to my [label]'** to use one!",
                "products": [], "session_id": session_id
            })
        except:
            return _save_and_return({
                "answer": "You have no saved addresses yet. Click the 📍 button to add one!",
                "products": [], "session_id": session_id
            })
    is_add_address = any(kw in query.lower() for kw in ADD_ADDRESS_KEYWORDS)
    if is_add_address:
        session_store[session_id]["awaiting_address"] = True
        return _save_and_return({"answer": "Please share your address like this:\nlabel: home\nstreet: 123 MG Road\ncity: Kochi\nstate: Kerala\npostal_code: 682001", "products": [], "session_id": session_id})
    if any(kw in query.lower() for kw in DELETE_ADDRESS_KEYWORDS): session_store[session_id]["awaiting_address"] = False
    if session_store[session_id].get("awaiting_address"):
        try:
            lines = {l.split(":")[0].strip(): l.split(":",1)[1].strip() for l in query.split("\n") if ":" in l}
            from address_router import upsert_address, AddressUpsert
            cid = session_store[session_id].get("customer_id", 0)
            upsert_address(AddressUpsert(customer_id=cid, label=lines.get("label","home"), full_address=", ".join(filter(None,[lines.get("street"),lines.get("city"),lines.get("state"),lines.get("postal_code")])), street=lines.get("street"), city=lines.get("city"), state=lines.get("state"), postal_code=lines.get("postal_code")))
            session_store[session_id]["awaiting_address"] = False
            return _save_and_return({"answer": aria_say("Customer just saved a new address successfully", "Confirm address saved and tell them they can now use it for delivery"), "products": [], "session_id": session_id})
        except Exception as e:
            return _save_and_return({"answer": f"Could not save: {str(e)}", "products": [], "session_id": session_id})
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
            return _save_and_return({
                "answer": f"Your **{matched_label.title()}** address has been deleted.",
                "products": [], "session_id": session_id
            })
        else:
            label_list = ", ".join([f"'{a['display_label']}'" for a in addrs]) if addrs else "none saved"
            return _save_and_return({
                "answer": f"Which address to delete? Your saved labels: {label_list}. Say e.g. *delete my home address*.",
                "products": [], "session_id": session_id
            })
    is_edit_address = any(kw in query.lower() for kw in EDIT_ADDRESS_KEYWORDS)
    if is_edit_address:
        return _save_and_return({
            "answer": "To edit an address, click the 📍 button in the header, select the address card and click **Edit**.",
            "products": [], "session_id": session_id,
            "open_address_manager": True
        })
    # ── Auto Checkout intent ──
    is_checkout = any(kw in query.lower() for kw in CHECKOUT_KEYWORDS)
    is_deliver = any(kw in query.lower() for kw in DELIVERY_KEYWORDS)
    if is_checkout and not is_deliver:
        cart = cart_store.get(session_id, [])
        if not cart:
            return _save_and_return({"answer": "Your cart is empty! Please add products first.", "products": [], "session_id": session_id})
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
            return _save_and_return({"answer": f"Your cart total: **${round(total,2)}**\n\nAvailable offers:\n{coupon_list}\n\nSay **apply SAVE10** or **skip** to continue.", "products": [], "session_id": session_id})
        else:
            return _save_and_return({"answer": "No offers available.", "products": [], "session_id": session_id, "open_checkout": True})

    if is_checkout or is_deliver:
        if not session_store[session_id].get("logged_in", False):
            return _save_and_return({"answer": "Please login first so I can place your order! Click the login button or say your email.", "products": [], "session_id": session_id, "requires_login": True})
        cart = cart_store.get(session_id, [])
        if not cart:
            return _save_and_return({"answer": "Your cart is empty! Tell me what you want to buy first.", "products": [], "session_id": session_id})
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
            return _save_and_return({"answer": "I could not find your saved address. Please say: add address\nlabel: home\nstreet: your street\ncity: your city\nstate: Kerala\npostal_code: 682001", "products": [], "session_id": session_id})
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
            _o_items = cart_store.get(session_id, [])
            _o_total = sum(i["price"]*i["qty"] for i in _o_items)
            _o_email = session_store[session_id].get("email", "unknown")
            _o_addr = session_store[session_id].get("resolved_address", {})
            send_order_alert(_o_email, _o_items, _o_total, _o_addr.get("full_address","") if isinstance(_o_addr, dict) else "")
            cart_store[session_id] = []
            items_text = ", ".join([i["name"] for i in result["items"]])
            return {
                "products": [],
                "session_id": session_id,
                "cart": [],
                "cart_total": 0
            }
        except Exception as e:
            return _save_and_return({"answer": f"Could not place order: {str(e)}. Please try again.", "products": [], "session_id": session_id})

    THRESHOLD = 0.45
    if llm_intent == "add_to_cart":
        pass
    elif llm_intent == "other" and history:
        # ── No product search needed — let LLM answer from history context ──
        answer = llm_chat(query, history=history)
        history.append({"role": "human", "content": query})
        history.append({"role": "assistant", "content": answer})
        return _save_and_return({"answer": answer, "products": session_store[session_id].get("last_products", []), "session_id": session_id})
    elif not products or products[0]["similarity"] < 0.35:
        # Track not-found query
        try:
            _nf_conn = get_db(); _nf_cur = _nf_conn.cursor()
            _nf_cur.execute("INSERT INTO search_not_found (customer_id, session_id, query) VALUES (%s, %s, %s)",
                (_cid or 0, session_id, query))
            _nf_conn.commit(); _nf_cur.close(); _nf_conn.close()
        except: pass
        _na_answer = llm_chat(f"""The customer asked for "{query}" but we don't have this product in our catalog.
Politely tell them this specific product is not available.
Suggest they try: bags, watches, jackets, hoodies, tees, shorts, joggers, backpacks, sports gear.
Keep it short and friendly. Do NOT make up products.""", history=history)
        return _save_and_return({"answer": _na_answer, "products": [], "session_id": session_id})
    elif products[0]["similarity"] < THRESHOLD:
        # Partial match — show products but mention they may not be exact
        session_store[session_id]["last_products"] = products

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

    answer = llm_chat(prompt, history=history)

    history.append({"role": "human", "content": query})
    history.append({"role": "assistant", "content": answer})

    return _save_and_return({
        "answer": answer,
        "products": products,
        "session_id": session_id
    })

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
@app.get("/admin")
def admin_page():
    from fastapi.responses import FileResponse
    import os
    admin_path = os.path.join(os.path.dirname(__file__), "../admin.html")
    return FileResponse(admin_path)

@app.get("/admin/stats")
def admin_stats(token: str = ""):
    if token != "shopai_admin_2024":
        from fastapi import HTTPException
        raise HTTPException(status_code=401, detail="Unauthorized")
    try:
        conn = get_db()
        cur = conn.cursor()
        cur.execute("SELECT COUNT(*) FROM chat_history")
        total_messages = cur.fetchone()[0]
        cur.execute("SELECT COUNT(DISTINCT customer_id) FROM chat_history WHERE customer_id > 0")
        total_customers = cur.fetchone()[0]
        cur.execute("SELECT COUNT(DISTINCT session_id) FROM chat_history")
        total_sessions = cur.fetchone()[0]
        cur.execute("SELECT COUNT(*) FROM chat_history WHERE created_at > NOW() - INTERVAL '24 hours'")
        messages_today = cur.fetchone()[0]
        cur.execute("""
            SELECT customer_id, COUNT(*) as msg_count, MAX(created_at) as last_active
            FROM chat_history WHERE customer_id > 0
            GROUP BY customer_id ORDER BY last_active DESC LIMIT 10
        """)
        customers = [{"customer_id": r[0], "messages": r[1], "last_active": str(r[2])} for r in cur.fetchall()]
        cur.execute("""
            SELECT message, COUNT(*) as cnt FROM chat_history
            WHERE role = 'user' AND created_at > NOW() - INTERVAL '7 days'
            GROUP BY message ORDER BY cnt DESC LIMIT 10
        """)
        top_queries = [{"query": r[0], "count": r[1]} for r in cur.fetchall()]
        cur.execute("""
            SELECT session_id, customer_id, COUNT(*) as msgs,
            MIN(created_at) as started, MAX(created_at) as ended
            FROM chat_history GROUP BY session_id, customer_id
            ORDER BY ended DESC LIMIT 20
        """)
        sessions = [{"session_id": r[0], "customer_id": r[1], "messages": r[2],
                     "started": str(r[3]), "ended": str(r[4])} for r in cur.fetchall()]
        cur.execute("""
            SELECT DATE(created_at), COUNT(*) FROM chat_history
            WHERE created_at > NOW() - INTERVAL '7 days'
            GROUP BY DATE(created_at) ORDER BY DATE(created_at)
        """)
        daily_msgs = [{"date": str(r[0]), "count": r[1]} for r in cur.fetchall()]
        cur.execute("SELECT query, COUNT(*) as cnt FROM search_not_found GROUP BY query ORDER BY cnt DESC LIMIT 10")
        not_found = [{"query": r[0], "count": r[1]} for r in cur.fetchall()]
        cur.close(); conn.close()
        magento_stats = {"order_count": 0, "revenue": 0, "top_products": []}
        try:
            import requests as _req
            _admin_token = mcp.get_token()
            _r = _req.get(f"{mcp.base_url}/orders?searchCriteria[pageSize]=100&searchCriteria[sortOrders][0][field]=created_at&searchCriteria[sortOrders][0][direction]=DESC",
                headers={"Authorization": f"Bearer {_admin_token}"}, verify=False)
            if _r.status_code == 200:
                _orders = _r.json().get("items", [])
                magento_stats["order_count"] = len(_orders)
                magento_stats["revenue"] = round(sum(float(o.get("grand_total", 0)) for o in _orders), 2)
                _prod_counts = {}
                for o in _orders:
                    for item in o.get("items", []):
                        name = item.get("name", "?")
                        _prod_counts[name] = _prod_counts.get(name, 0) + int(item.get("qty_ordered", 1))
                magento_stats["top_products"] = sorted([{"name": k, "qty": v} for k, v in _prod_counts.items()], key=lambda x: -x["qty"])[:5]
        except Exception as _me:
            print(f"[MAGENTO STATS ERROR] {_me}")
        return {
            "total_messages": total_messages,
            "total_customers": total_customers,
            "total_sessions": total_sessions,
            "messages_today": messages_today,
            "top_customers": customers,
            "top_queries": top_queries,
            "recent_sessions": sessions,
            "daily_messages": daily_msgs,
            "not_found_queries": not_found,
            "magento_stats": magento_stats
        }
    except Exception as e:
        return {"error": str(e)}

@app.get("/admin/chat/{customer_id}")
def admin_customer_chat(customer_id: int, token: str = ""):
    if token != "shopai_admin_2024":
        from fastapi import HTTPException
        raise HTTPException(status_code=401, detail="Unauthorized")
    try:
        conn = get_db()
        cur = conn.cursor()
        cur.execute("""
            SELECT session_id, role, message, created_at
            FROM chat_history WHERE customer_id = %s
            ORDER BY created_at ASC
        """, (customer_id,))
        rows = cur.fetchall()
        cur.close(); conn.close()
        return {"history": [{"session_id": r[0], "role": r[1], "message": r[2], "time": str(r[3])} for r in rows]}
    except Exception as e:
        return {"error": str(e)}

@app.get("/chat-history/{customer_id}")
def get_chat_history(customer_id: int, limit: int = 50):
    try:
        conn = get_db()
        cur = conn.cursor()
        cur.execute("""
            SELECT session_id, role, message, created_at
            FROM chat_history
            WHERE customer_id = %s
            ORDER BY created_at DESC
            LIMIT %s
        """, (customer_id, limit))
        rows = cur.fetchall()
        cur.close(); conn.close()
        return {"history": [{"session_id": r[0], "role": r[1], "message": r[2], "time": str(r[3])} for r in rows]}
    except Exception as e:
        return {"error": str(e)}

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
