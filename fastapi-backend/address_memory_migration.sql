CREATE EXTENSION IF NOT EXISTS vector;
CREATE TABLE IF NOT EXISTS customer_addresses (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL,
    label TEXT NOT NULL,
    full_address TEXT NOT NULL,
    street TEXT,
    city TEXT,
    state TEXT,
    postal_code TEXT,
    country TEXT DEFAULT 'IN',
    is_default BOOLEAN DEFAULT FALSE,
    label_embedding vector (384),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(customer_id, label)
);
CREATE INDEX IF NOT EXISTS idx_customer_addresses_customer_id ON customer_addresses(customer_id);
CREATE INDEX IF NOT EXISTS idx_customer_addresses_embedding ON customer_addresses USING ivfflat (label_embedding vector_cosine_ops) WITH (lists = 10);
CREATE OR REPLACE FUNCTION update_updated_at_column() RETURNS TRIGGER AS $func$ BEGIN NEW.updated_at = NOW(); RETURN NEW; END; $func$ language 'plpgsql';
CREATE TRIGGER update_customer_addresses_updated_at BEFORE UPDATE ON customer_addresses FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
