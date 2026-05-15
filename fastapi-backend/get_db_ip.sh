#!/bin/bash
IP=$(docker inspect pgvector-db | grep '"IPAddress"' | head -1 | grep -oE '([0-9]{1,3}\.){3}[0-9]{1,3}')
sed -i "s/DB_HOST=.*/DB_HOST=$IP/" /root/magento/fastapi-backend/.env
echo "DB_HOST set to $IP"
