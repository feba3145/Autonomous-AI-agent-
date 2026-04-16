from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
import requests
import psycopg2
import os
import time
import threading
from pgvector.psycopg2 import register_vector
from sentence_transformers import SentenceTransformer
from langchain_ollama import OllamaLLM
from dotenv import load_dotenv
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

def cleanup_sessions():
    while True:
        time.sleep(300)
        now = time.time()
        expired = [sid for sid, data in session_store.items()
                   if now - data["last_used"] > SESSION_TIMEOUT]
        for sid in expired:
            del session_store[sid]

threading.Thread(target=cleanup_sessions, daemon=True).start()
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

@app.get("/")
def root():
    return {"status": "AI Shopping Assistant API is running"}

@app.get("/health")
def health():
    return {"status": "ok"}
@app.get("/search")
def search(q: str, limit: int = 5):
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

@app.post("/chat")
def chat(payload: dict):
    query = payload.get("query", "")
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
@app.post("/rag-chat")
def rag_chat(payload: dict):
    query = payload.get("query", "")
    session_id = payload.get("session_id", "default")

    if session_id not in session_store:
        session_store[session_id] = {"history": [], "last_used": time.time()}
    session_store[session_id]["last_used"] = time.time()
    history = session_store[session_id]["history"]

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
