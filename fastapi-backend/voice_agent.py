import re
import httpx
from typing import Optional

import session_manager as sm

BASE = "http://127.0.0.1:8002"


def _clean_for_tts(text: str) -> str:
    text = re.sub(r"\*\*?(.*?)\*\*?", r"\1", text)
    text = re.sub(r"`[^`]*`", "", text)
    text = re.sub(r"#+\s*", "", text)
    text = re.sub(r"\n{2,}", ". ", text)
    text = re.sub(r"\n", " ", text)
    text = re.sub(r"\s{2,}", " ", text)
    return text.strip()


async def _call_rag(query: str, session_id: str, customer_id: Optional[int]) -> dict:
    async with httpx.AsyncClient(timeout=20) as client:
        resp = await client.post(f"{BASE}/rag-chat", json={
            "query": query,
            "session_id": session_id,
            "customer_id": customer_id,
            "voice_mode": True,
        })
        return resp.json()


async def _do_checkout(session_id: str, customer_id: int) -> dict:
    async with httpx.AsyncClient(timeout=15) as client:
        resp = await client.post(f"{BASE}/checkout", json={
            "session_id": session_id,
            "customer_id": customer_id,
        })
        return resp.json()


async def process_utterance(
    transcript: str,
    session_id: str,
) -> tuple[str, list[str]]:
    sess = await sm.get_or_create(session_id)
    actions: list[str] = []

    if not transcript.strip():
        return "Sorry, I did not catch that. Could you say that again?", []

    try:
        rag_result = await _call_rag(transcript, session_id, sess.customer_id)
    except Exception:
        return "I am having trouble connecting right now. Please try again.", []

    raw_reply = (
        rag_result.get("answer") or
        rag_result.get("response") or
        rag_result.get("ai_response") or
        "How can I help?"
    )

    reply = _clean_for_tts(raw_reply)

    if rag_result.get("cart"):
        await sm.update(session_id, cart=rag_result["cart"])
        actions.append("cart_updated")

    checkout_triggers = [
        "place my order", "confirm order", "checkout",
        "buy it", "place order", "yes confirm"
    ]
    if any(t in transcript.lower() for t in checkout_triggers):
        if sess.logged_in and sess.customer_id:
            try:
                result = await _do_checkout(session_id, sess.customer_id)
                order_id = result.get("order_id", "")
                reply = (
                    f"Your order has been placed. "
                    f"{'Order ID ' + str(order_id) + '.' if order_id else ''} "
                    f"I will send you a confirmation."
                )
                actions.append("checkout_done")
                await sm.update(session_id, cart=[], pending_checkout=False)
            except Exception:
                reply = "I could not complete the checkout. Please try again."
        else:
            reply = "Please log in first, then I can place your order."

    if rag_result.get("requires_login"):
        reply = "You will need to log in first. Tap the account icon on the screen."
        actions.append("requires_login")

    if rag_result.get("requires_address") or rag_result.get("open_address_manager"):
        actions.append("open_address_manager")

    hist = sess.conversation_history[-10:]
    hist.append({"role": "user", "content": transcript})
    hist.append({"role": "assistant", "content": reply})
    await sm.update(session_id, conversation_history=hist)

    return reply, actions
