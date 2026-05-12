#!/bin/bash
echo "Stopping old processes..."
pkill -f "uvicorn main:app" 2>/dev/null
pkill -f "http.server 8080" 2>/dev/null
sleep 2

echo "Starting ShopAI..."
cd /root && python3 -m http.server 8080 &
echo "File server started on port 8080"

cd /root/magento/fastapi-backend
while true; do
    uvicorn main:app --host 0.0.0.0 --port 8002
    echo "Backend crashed, restarting in 3s..."
    sleep 3
done &
echo "Backend started on port 8002"

echo ""
echo "ShopAI is running!"
echo "Open: http://172.21.249.153:8080/shopai.html"
