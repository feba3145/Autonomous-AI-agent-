content = open('/root/magento/fastapi-backend/main.py').read()

old = '''    THRESHOLD = 0.5
    if not products or products[0]["similarity"] < THRESHOLD:'''

new = '''    BUY_KEYWORDS = ["buy", "purchase", "order", "add to cart", "i want to buy", "i want this", "get this", "checkout"]
    is_buy_intent = any(kw in query.lower() for kw in BUY_KEYWORDS)
    if is_buy_intent and products:
        top = products[0]
        if session_id not in cart_store:
            cart_store[session_id] = []
        existing = next((i for i in cart_store[session_id] if i["sku"] == top["sku"]), None)
        if existing:
            existing["qty"] += 1
        else:
            cart_store[session_id].append({"sku": top["sku"], "name": top["name"], "price": top["price"], "qty": 1})
        cart = cart_store[session_id]
        total = sum(i["price"] * i["qty"] for i in cart)
        history.append({"role": "human", "content": query})
        history.append({"role": "assistant", "content": "Added " + top["name"] + " to your cart!"})
        return {
            "answer": "I have added " + top["name"] + " to your cart for $" + str(top["price"]) + ". Total: $" + str(round(total, 2)) + ". Would you like to checkout or continue shopping?",
            "products": products,
            "session_id": session_id,
            "cart": cart,
            "cart_total": round(total, 2)
        }

    THRESHOLD = 0.5
    if not products or products[0]["similarity"] < THRESHOLD:'''

content = open('/root/magento/fastapi-backend/main.py').read()

old = '    # Detect buy intent\n    is_buy_intent = any(kw in query.lower() for kw in BUY_KEYWORDS)\n    if is_buy_intent and products:'

new = '''    # Detect buy intent
    is_buy_intent = any(kw in query.lower() for kw in BUY_KEYWORDS)

    # Use last recommended products if buy intent detected
    if is_buy_intent:
        last_products = session_store[session_id].get("last_products", [])
        if last_products:
            products = last_products

    if is_buy_intent and products:'''

if old in content:
    content = content.replace(old, new)
    open('/root/magento/fastapi-backend/main.py', 'w').write(content)
    print('Done')
else:
    print('Pattern not found')
