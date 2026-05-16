#!/bin/bash
echo "Starting containers..."
docker start pgvector-db magento-app-1 2>/dev/null
sleep 5

echo "Fixing DB auth..."
docker exec pgvector-db bash -c "echo 'host all all 0.0.0.0/0 trust' >> /var/lib/postgresql/data/pg_hba.conf && psql -U aiuser -d aidb -c 'SELECT pg_reload_conf();'" 2>/dev/null

echo "Fixing image paths..."
python3 -c "
import psycopg2
conn = psycopg2.connect(host='127.0.0.1',port=5432,dbname='aidb',user='aiuser',password='aipassword')
cur = conn.cursor()
cur.execute(\"UPDATE products SET image = REGEXP_REPLACE(image, 'http://[0-9.]+:[0-9]+', '') WHERE image LIKE 'http://%'\")
print('Image paths fixed:', cur.rowcount)
conn.commit()
cur.close()
conn.close()
"

echo "Killing old processes..."
lsof -ti:8002 | xargs kill -9 2>/dev/null
sleep 2

echo "Starting backend..."
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_NAME=aidb
export DB_USER=aiuser
export DB_PASSWORD=aipassword

cd /root/magento/fastapi-backend
python3 -m uvicorn main:app --host 0.0.0.0 --port 8002 --ws websockets
