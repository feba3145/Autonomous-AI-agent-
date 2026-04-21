#!/bin/bash
echo "==> Starting Docker containers..."
cd ~/magento
docker compose up -d --remove-orphans
docker compose -f docker-compose.ai.yml up -d
sleep 10

echo "==> Checking Ollama..."
curl -s http://localhost:11434/api/tags > /dev/null || (ollama serve & sleep 5)

echo "==> Checking Bold MCP..."
if [ -d "/root/magento/bold-mcp" ]; then
    echo "    Bold MCP found at /root/magento/bold-mcp"
    cd /root/magento/bold-mcp
    node -e "console.log('    Node.js OK:', process.version)"
else
    echo "    ERROR:Bold MCP not found! Run: git clone https://github.com/boldcommerce/magento2-mcp.git ~/magento/bold-mcp"
fi

echo "==> Testing MCP connection to Magento..."
cd ~/magento/fastapi-backend
python3 -c "
from dotenv import load_dotenv
load_dotenv()
from mcp_client import mcp
stock = mcp.get_product_stock('MH03-L-Black')
if stock.get('is_in_stock'):
    print('    MCP connected to Magento OK')
else:
    print('    MCP connection FAILED - check Magento is running')
" 2>/dev/null

echo "==> Starting FastAPI..."
fuser -k 8002/tcp 2>/dev/null
sleep 1
cd ~/magento/fastapi-backend
nohup uvicorn main:app --host 0.0.0.0 --port 8002 > /tmp/uvicorn.log 2>&1 &
sleep 6

echo "==> Health check..."
curl -s http://localhost:8002/health
echo ""
curl -s http://localhost:8002/product-count
echo ""
echo "✅ All systems ready!"
echo "   FastAPI:  http://localhost:8002"
echo "   Swagger:  http://localhost:8002/docs"
echo "   Magento:  https://magento.test"
echo "   Bold MCP: /root/magento/bold-mcp"
echo "   Logs:     cat /tmp/uvicorn.log"
