from fastapi import APIRouter
from pydantic import BaseModel
import psycopg2, os
from pgvector.psycopg2 import register_vector
from sentence_transformers import SentenceTransformer

router = APIRouter(prefix="/cms", tags=["CMS"])
model = SentenceTransformer("all-MiniLM-L6-v2")

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

class PolicyRequest(BaseModel):
    query: str

@router.get("/pages")
def list_pages():
    conn = get_db()
    cur = conn.cursor()
    cur.execute("SELECT id, title, slug, page_type FROM cms_pages")
    rows = cur.fetchall()
    cur.close()
    conn.close()
    return [{"id": r[0], "title": r[1], "slug": r[2], "page_type": r[3]} for r in rows]

@router.get("/pages/{slug}")
def get_page(slug: str):
    conn = get_db()
    cur = conn.cursor()
    cur.execute("SELECT id, title, slug, content, page_type FROM cms_pages WHERE slug=%s", (slug,))
    row = cur.fetchone()
    cur.close()
    conn.close()
    if not row:
        return {"error": "Page not found"}
    return {"id": row[0], "title": row[1], "slug": row[2], "content": row[3], "page_type": row[4]}

@router.post("/policy-answer")
def answer_policy(body: PolicyRequest):
    embedding = model.encode(body.query).tolist()
    conn = get_db()
    cur = conn.cursor()
    cur.execute("""
        SELECT title, content, 1 - (embedding <=> %s::vector) AS similarity
        FROM cms_pages
        WHERE embedding IS NOT NULL
        ORDER BY embedding <=> %s::vector
        LIMIT 1
    """, (embedding, embedding))
    row = cur.fetchone()
    cur.close()
    conn.close()
    if not row:
        return {"answer": "I could not find relevant policy information."}
    return {"title": row[0], "answer": row[1], "similarity": float(row[2])}
