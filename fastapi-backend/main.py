from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
import requests
import psycopg2
import os
from pgvector.psycopg2 import register_vector
from sentence_transformers import SentenceTransformer
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

def get_magento_token():
    res = requests.post(
        f"{os.getenv('MAGENTO_URL')}/rest/V1/integration/admin/token",
        json={"username": os.getenv("MAGENTO_USER"), "password": os.getenv("MAGENTO_PASS")},
        verify=False
    )
    return res.json()

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

@app.get("/")
def root():
    return {"status": "AI Shopping Assistant API is running"}

@app.get("/health")
def health():
    return {"status": "ok"}

@app.post("/sync-products")
def sync_products():
    token = get_magento_token()
    headers = {"Authorization": f"Bearer {token}"}
    page = 1
    total_synced = 0
    conn = get_db()
    cur = conn.cursor()
    while True:
        res = requests.get(
            f"{os.getenv('MAGENTO_URL')}/rest/V1/products",
            params={"searchCriteria[pageSize]": 100, "searchCriteria[currentPage]": page},
            headers=headers,
            verify=False
        )
        items = res.json().get("items", [])
        if not items:
            break
        for p in items:
            desc = ""
            for attr in p.get("custom_attributes", []):
                if attr["attribute_code"] == "description":
                    desc = attr["value"]
                    break
            text = f"{p['name']} {desc}"
            embedding = model.encode(text).tolist()
            cur.execute("""
                INSERT INTO products (product_id, sku, name, description, price, embedding)
                VALUES (%s, %s, %s, %s, %s, %s)
                ON CONFLICT (sku) DO UPDATE
                SET name=EXCLUDED.name,
                    description=EXCLUDED.description,
                    price=EXCLUDED.price,
                    embedding=EXCLUDED.embedding
            """, (p["id"], p["sku"], p["name"], desc, p.get("price", 0), embedding))
        conn.commit()
        total_synced += len(items)
        page += 1
    cur.close()
    conn.close()
    return {"synced": total_synced}

@app.get("/search")
def search(q: str, limit: int = 5):
    embedding = model.encode(q).tolist()
    conn = get_db()
    cur = conn.cursor()
    cur.execute("""
        SELECT sku, name, price,
               1 - (embedding <=> %s::vector) AS similarity
        FROM products
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
