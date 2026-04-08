import requests
import psycopg2
from sentence_transformers import SentenceTransformer
import re
import time

MAGENTO_TOKEN = "eyJraWQiOiIxIiwiYWxnIjoiSFMyNTYifQ.eyJ1aWQiOjQsInV0eXBpZCI6MiwiaWF0IjoxNzc1NjQwNjgyLCJleHAiOjE3NzU2NDQyODJ9.DuMsFG5TETuqg13QFfS01Sqdy_yU-Bl81ucgZc5pA6w"
MAGENTO_URL   = "https://magento.test/rest/V1"
PG_CONN       = "host=localhost port=5432 dbname=aidb user=aiuser password=aipassword"
PAGE_SIZE     = 100

def clean_html(text):
    if not text:
        return ""
    return re.sub(r'<[^>]+>', ' ', text).strip()

def fetch_all_products():
    headers = {"Authorization": f"Bearer {MAGENTO_TOKEN}"}
    all_items = []
    page = 1
    print("Fetching products from Magento...")
    while True:
        url = (
            f"{MAGENTO_URL}/products"
            f"?searchCriteria[pageSize]={PAGE_SIZE}"
            f"&searchCriteria[currentPage]={page}"
            f"&fields=items[id,sku,name,price,custom_attributes]"
        )
        resp = requests.get(url, headers=headers, verify=False, timeout=30)
        resp.raise_for_status()
        data = resp.json()
        items = data.get("items", [])
        if not items:
            break
        all_items.extend(items)
        print(f"  Page {page}: fetched {len(items)} products (total: {len(all_items)})")
        if len(items) < PAGE_SIZE:
            break
        page += 1
        time.sleep(0.2)
    print(f"Total products fetched: {len(all_items)}")
    return all_items

def get_description(custom_attributes):
    if not custom_attributes:
        return ""
    for attr in custom_attributes:
        if attr.get("attribute_code") in ("description", "short_description"):
            return clean_html(attr.get("value", ""))
    return ""

def embed_and_store(products, model, conn):
    cur = conn.cursor()
    skipped = 0
    print(f"\nEmbedding and storing {len(products)} products...")
    for i, p in enumerate(products, 1):
        sku  = p.get("sku", "").strip()
        name = p.get("name", "").strip()
        if not sku or not name:
            skipped += 1
            continue
        description = get_description(p.get("custom_attributes", []))
        price       = p.get("price", 0.0)
        product_id  = p.get("id")
        text_to_embed = f"{name}. {description}".strip()
        embedding = model.encode(text_to_embed).tolist()
        cur.execute("""
            INSERT INTO products (product_id, sku, name, description, price, embedding)
            VALUES (%s, %s, %s, %s, %s, %s::vector)
            ON CONFLICT (sku) DO UPDATE SET
                name        = EXCLUDED.name,
                description = EXCLUDED.description,
                price       = EXCLUDED.price,
                embedding   = EXCLUDED.embedding
        """, (product_id, sku, name, description, price, str(embedding)))
        if i % 50 == 0:
            conn.commit()
            print(f"  Progress: {i}/{len(products)} embedded...")
    conn.commit()
    cur.close()
    print(f"\nDone! Embedded: {len(products)-skipped}, Skipped: {skipped}")

def main():
    print("Loading all-MiniLM-L6-v2 model...")
    model = SentenceTransformer("all-MiniLM-L6-v2")
    print("Model loaded.\n")
    products = fetch_all_products()
    print("Connecting to pgvector...")
    conn = psycopg2.connect(PG_CONN)
    embed_and_store(products, model, conn)
    conn.close()
    print("\n✅ All products vectorized and stored in pgvector!")

if __name__ == "__main__":
    main()
