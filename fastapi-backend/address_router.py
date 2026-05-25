from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from typing import Optional, List
import psycopg2
import os
import requests
from pgvector.psycopg2 import register_vector

router = APIRouter(prefix="/addresses", tags=["Address Memory"])

# ── Predefined label icons for UI hints (returned in list response) ──
LABEL_ICONS = {
    "home": "home",
    "primary address": "home",
    "work location": "business",
    "office": "business",
    "family address": "family_restroom",
    "temporary stay": "hotel",
    "pickup location": "store",
    "guest address": "person",
    "vacation address": "beach_access",
    "saved location": "bookmark",
    "parent's house": "cottage",
    "hostel": "apartment",
    "apartment": "apartment",
    "hotel": "hotel",
    "friend's place": "group",
    "college": "school",
    "branch office": "corporate_fare",
    "warehouse": "warehouse",
    "holiday stay": "luggage",
}

def get_icon_for_label(label: str) -> str:
    return LABEL_ICONS.get(label.lower().strip(), "location_on")

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

def embed(text: str):
    try:
        r = requests.post(
            "http://localhost:11434/api/embeddings",
            json={"model": "nomic-embed-text", "prompt": text},
            timeout=30
        )
        return r.json()["embedding"]
    except Exception as e:
        print(f"[embed error] {e}")
        return None


# ── Models ──

class AddressUpsert(BaseModel):
    customer_id: int
    label: str                        # Any string — "home", "Parent's House", "Hotel", etc.
    full_address: str
    street: Optional[str] = None
    city: Optional[str] = None
    state: Optional[str] = None
    postal_code: Optional[str] = None
    country: str = "IN"
    is_default: bool = False

class AddressUpdate(BaseModel):
    full_address: Optional[str] = None
    street: Optional[str] = None
    city: Optional[str] = None
    state: Optional[str] = None
    postal_code: Optional[str] = None
    country: Optional[str] = None
    is_default: Optional[bool] = None

class ResolveRequest(BaseModel):
    customer_id: int
    query: str                        # e.g. "deliver to my work location"


# ── DB schema migration helper (run once) ──
# Ensure the table supports any label string, not just home/office/other.
# The existing schema already uses TEXT for label, so no migration needed.
# Just make sure the unique constraint is (customer_id, label).


# ── POST /addresses/upsert  (create or update by label) ──
@router.post("/upsert")
def upsert_address(body: AddressUpsert):
    label_clean = body.label.strip().lower()
    embedding = embed(label_clean)
    conn = get_db()
    cur = conn.cursor()

    if embedding:
        cur.execute("""
            INSERT INTO customer_addresses
                (customer_id, label, full_address, street, city, state,
                 postal_code, country, is_default, label_embedding)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s::vector)
            ON CONFLICT (customer_id, label) DO UPDATE SET
                full_address    = EXCLUDED.full_address,
                street          = EXCLUDED.street,
                city            = EXCLUDED.city,
                state           = EXCLUDED.state,
                postal_code     = EXCLUDED.postal_code,
                country         = EXCLUDED.country,
                is_default      = EXCLUDED.is_default,
                label_embedding = EXCLUDED.label_embedding,
                updated_at      = NOW()
            RETURNING id, customer_id, label, full_address, city, state, postal_code, is_default
        """, (body.customer_id, label_clean, body.full_address, body.street,
              body.city, body.state, body.postal_code, body.country,
              body.is_default, embedding))
    else:
        cur.execute("""
            INSERT INTO customer_addresses
                (customer_id, label, full_address, street, city, state,
                 postal_code, country, is_default)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)
            ON CONFLICT (customer_id, label) DO UPDATE SET
                full_address = EXCLUDED.full_address,
                street       = EXCLUDED.street,
                city         = EXCLUDED.city,
                state        = EXCLUDED.state,
                postal_code  = EXCLUDED.postal_code,
                country      = EXCLUDED.country,
                is_default   = EXCLUDED.is_default,
                updated_at   = NOW()
            RETURNING id, customer_id, label, full_address, city, state, postal_code, is_default
        """, (body.customer_id, label_clean, body.full_address, body.street,
              body.city, body.state, body.postal_code, body.country, body.is_default))

    row = cur.fetchone()
    conn.commit()
    cur.close()
    conn.close()
    return {
        "id": row[0],
        "customer_id": row[1],
        "label": row[2],
        "display_label": row[2].title(),
        "full_address": row[3],
        "city": row[4],
        "state": row[5],
        "postal_code": row[6],
        "is_default": row[7],
        "icon": get_icon_for_label(row[2])
    }


# ── POST /addresses/  (alias kept for backward compat) ──
@router.post("/")
def upsert_address_compat(body: AddressUpsert):
    return upsert_address(body)


# ── GET /addresses/{customer_id}  (list all) ──
@router.get("/{customer_id}")
def list_addresses(customer_id: int):
    conn = get_db()
    cur = conn.cursor()
    cur.execute("""
        SELECT id, customer_id, label, full_address, city, state,
               postal_code, country, is_default
        FROM customer_addresses
        WHERE customer_id = %s
        ORDER BY is_default DESC, updated_at DESC
    """, (customer_id,))
    rows = cur.fetchall()
    cur.close()
    conn.close()
    if not rows:
        raise HTTPException(status_code=404, detail="No addresses found")
    return [
        {
            "id": r[0],
            "customer_id": r[1],
            "label": r[2],
            "display_label": r[2].title(),
            "full_address": r[3],
            "city": r[4],
            "state": r[5],
            "postal_code": r[6],
            "country": r[7],
            "is_default": r[8],
            "icon": get_icon_for_label(r[2])
        }
        for r in rows
    ]


# ── GET /addresses/{customer_id}/{label}  (fetch single by label) ──
@router.get("/{customer_id}/{label}")
def get_address_by_label(customer_id: int, label: str):
    conn = get_db()
    cur = conn.cursor()
    cur.execute("""
        SELECT id, customer_id, label, full_address, street, city,
               state, postal_code, country, is_default
        FROM customer_addresses
        WHERE customer_id = %s AND label = %s
    """, (customer_id, label.strip().lower()))
    row = cur.fetchone()
    cur.close()
    conn.close()
    if not row:
        raise HTTPException(status_code=404, detail=f"No address with label '{label}' found")
    return {
        "id": row[0],
        "customer_id": row[1],
        "label": row[2],
        "display_label": row[2].title(),
        "full_address": row[3],
        "street": row[4],
        "city": row[5],
        "state": row[6],
        "postal_code": row[7],
        "country": row[8],
        "is_default": row[9],
        "icon": get_icon_for_label(row[2])
    }


# ── PATCH /addresses/{customer_id}/{label}  (edit fields) ──
@router.patch("/{customer_id}/{label}")
def edit_address(customer_id: int, label: str, body: AddressUpdate):
    conn = get_db()
    cur = conn.cursor()
    # Build dynamic SET clause
    fields = {}
    if body.full_address is not None: fields["full_address"] = body.full_address
    if body.street       is not None: fields["street"]       = body.street
    if body.city         is not None: fields["city"]         = body.city
    if body.state        is not None: fields["state"]        = body.state
    if body.postal_code  is not None: fields["postal_code"]  = body.postal_code
    if body.country      is not None: fields["country"]      = body.country
    if body.is_default   is not None: fields["is_default"]   = body.is_default

    if not fields:
        raise HTTPException(status_code=400, detail="No fields to update")

    set_clause = ", ".join([f"{k} = %s" for k in fields])
    values     = list(fields.values()) + [customer_id, label.strip().lower()]

    cur.execute(f"""
        UPDATE customer_addresses
        SET {set_clause}, updated_at = NOW()
        WHERE customer_id = %s AND label = %s
        RETURNING id, label, full_address, city, state, postal_code, is_default
    """, values)
    row = cur.fetchone()
    conn.commit()
    cur.close()
    conn.close()
    if not row:
        raise HTTPException(status_code=404, detail="Address not found")
    return {
        "id": row[0],
        "label": row[1],
        "display_label": row[1].title(),
        "full_address": row[2],
        "city": row[3],
        "state": row[4],
        "postal_code": row[5],
        "is_default": row[6],
        "icon": get_icon_for_label(row[1])
    }


# ── DELETE /addresses/{customer_id}/{label} ──
@router.delete("/{customer_id}/{label}")
def delete_address(customer_id: int, label: str):
    conn = get_db()
    cur = conn.cursor()
    cur.execute(
        "DELETE FROM customer_addresses WHERE customer_id = %s AND label = %s",
        (customer_id, label.strip().lower())
    )
    deleted = cur.rowcount
    conn.commit()
    cur.close()
    conn.close()
    if deleted == 0:
        raise HTTPException(status_code=404, detail="Address not found")
    return {"status": "deleted", "label": label}


# ── POST /addresses/resolve  (semantic search by user query) ──
@router.post("/resolve")
def resolve_address(body: ResolveRequest):
    query_vec = embed(body.query)
    conn = get_db()
    cur = conn.cursor()

    if query_vec:
        # Try vector similarity first
        cur.execute("""
            SELECT id, customer_id, label, full_address, city, state,
                   postal_code, is_default,
                   label_embedding <=> %s::vector AS dist
            FROM customer_addresses
            WHERE customer_id = %s AND label_embedding IS NOT NULL
            ORDER BY label_embedding <=> %s::vector
            LIMIT 1
        """, (query_vec, body.customer_id, query_vec))
        row = cur.fetchone()

        # If best match distance > 0.3, also try ILIKE fallback
        if row and row[8] > 0.3:
            row = None

    else:
        row = None

    # Fallback: keyword match
    if not row:
        cur.execute("""
            SELECT id, customer_id, label, full_address, city, state,
                   postal_code, is_default
            FROM customer_addresses
            WHERE customer_id = %s
              AND label ILIKE %s
            LIMIT 1
        """, (body.customer_id, f"%{body.query.lower().replace('deliver to my ','').replace('deliver to ','').replace(' address','').replace('office','work').replace('shop','work').strip()}%"))
        raw = cur.fetchone()
        row = raw + (None,) if raw else None  # pad to same width

    cur.close()
    conn.close()

    if not row:
        raise HTTPException(
            status_code=404,
            detail=f"No saved address matched '{body.query}'. Please save it first."
        )

    return {
        "id": row[0],
        "customer_id": row[1],
        "label": row[2],
        "display_label": row[2].title(),
        "full_address": row[3],
        "city": row[4],
        "state": row[5],
        "postal_code": row[6],
        "is_default": row[7],
        "icon": get_icon_for_label(row[2])
    }


# ── GET /addresses/labels/suggestions  (return default label list for UI) ──
@router.get("/labels/suggestions")
def label_suggestions():
    return {
        "default_labels": [
            {"label": "home",            "display": "Home",            "icon": "home"},
            {"label": "work location",   "display": "Work Location",   "icon": "business"},
            {"label": "family address",  "display": "Family Address",  "icon": "family_restroom"},
            {"label": "temporary stay",  "display": "Temporary Stay",  "icon": "hotel"},
            {"label": "pickup location", "display": "Pickup Location", "icon": "store"},
            {"label": "vacation address","display": "Vacation Address","icon": "beach_access"},
            {"label": "guest address",   "display": "Guest Address",   "icon": "person"},
            {"label": "saved location",  "display": "Saved Location",  "icon": "bookmark"},
        ],
        "custom_examples": [
            "Parent's House", "Hostel", "Apartment", "Hotel",
            "Friend's Place", "College", "Branch Office",
            "Warehouse", "Holiday Stay", "Gym", "Studio"
        ]
    }
