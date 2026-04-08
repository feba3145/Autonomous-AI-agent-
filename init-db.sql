CREATE EXTENSION IF NOT EXISTS vector;
CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    product_id INTEGER,
    sku TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    price NUMERIC(10,2),
    embedding vector(384)
);
