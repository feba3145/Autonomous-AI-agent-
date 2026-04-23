from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from typing import Optional
import psycopg2
import os
import requests
from pgvector.psycopg2 import register_vector

router = APIRouter(prefix="/addresses", tags=["Address Memory"])

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

def embed(text):
    r = requests.post("http://localhost:11434/api/embeddings", json={"model": "nomic-embed-text", "prompt": text})
    return r.json()["embedding"]

class AddressUpsert(BaseModel):
    customer_id: int
    label: str
    full_address: str
    street: Optional[str] = None
    city: Optional[str] = None
    state: Optional[str] = None
    postal_code: Optional[str] = None
    country: str = "IN"
    is_default: bool = False

class ResolveRequest(BaseModel):
    customer_id: int
    query: str

@router.post("/")
def upsert_address(body: AddressUpsert):
    embedding = embed(body.label)
    conn = get_db()
    cur = conn.cursor()
    cur.execute("""
        INSERT INTO customer_addresses
            (customer_id, label, full_address, street, city, state, postal_code, country, is_default, label_embedding)
        VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s::vector)
        ON CONFLICT (customer_id, label) DO UPDATE SET
            full_address=EXCLUDED.full_address, street=EXCLUDED.street,
            city=EXCLUDED.city, state=EXCLUDED.state, postal_code=EXCLUDED.postal_code,
            country=EXCLUDED.country, is_default=EXCLUDED.is_default,
            label_embedding=EXCLUDED.label_embedding, updated_at=NOW()
        RETURNING *
    """, (body.customer_id, body.label, body.full_address, body.street,
          body.city, body.state, body.postal_code, body.country, body.is_default, embedding))
    row = cur.fetchone()
    conn.commit()
    cur.close()
    conn.close()
    return {"id": row[0], "customer_id": row[1], "label": row[2], "full_address": row[3]}

@router.get("/{customer_id}")
def list_addresses(customer_id: int):
    conn = get_db()
    cur = conn.cursor()
    cur.execute("SELECT id, customer_id, label, full_address, city, state, postal_code, is_default FROM customer_addresses WHERE customer_id=%s", (customer_id,))
    rows = cur.fetchall()
    cur.close()
    conn.close()
    if not rows:
        raise HTTPException(status_code=404, detail="No addresses found")
    return [{"id": r[0], "customer_id": r[1], "label": r[2], "full_address": r[3], "city": r[4], "state": r[5], "postal_code": r[6], "is_default": r[7]} for r in rows]

@router.post("/resolve")
def resolve_address(body: ResolveRequest):
    query_vec = embed(body.query)
    conn = get_db()
    cur = conn.cursor()
    cur.execute("""
        SELECT id, customer_id, label, full_address, city, state, postal_code, is_default
        FROM customer_addresses
        WHERE customer_id=%s AND label_embedding IS NOT NULL
        ORDER BY label_embedding <=> %s::vector
        LIMIT 1
    """, (body.customer_id, query_vec))
    row = cur.fetchone()
    cur.close()
    conn.close()
    if not row:
        raise HTTPException(status_code=404, detail="No saved address found")
    return {"id": row[0], "customer_id": row[1], "label": row[2], "full_address": row[3], "city": row[4], "state": row[5], "postal_code": row[6], "is_default": row[7]}

@router.delete("/{customer_id}/{label}")
def delete_address(customer_id: int, label: str):
    conn = get_db()
    cur = conn.cursor()
    cur.execute("DELETE FROM customer_addresses WHERE customer_id=%s AND label=%s", (customer_id, label))
    conn.commit()
    cur.close()
    conn.close()
    return {"status": "deleted"}
