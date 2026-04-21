content = open('/root/magento/fastapi-backend/main.py').read()

old = '    llm = OllamaLLM(model="llama3.2", base_url="http://localhost:11434")'

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
    llm = OllamaLLM(model="llama3.2", base_url="http://localhost:11434")'''

if old in content:
    content = content.replace(old, new)
    open('/root/magento/fastapi-backend/main.py', 'w').write(content)
    print('Done')
else:
    print('Pattern not found')
