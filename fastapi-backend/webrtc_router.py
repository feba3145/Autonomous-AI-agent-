import asyncio
import base64
import json
import urllib.parse

import httpx
from fastapi import APIRouter, WebSocket, WebSocketDisconnect

import session_manager as sm
import vad_utils
import voice_agent

router = APIRouter(tags=["webrtc"])


@router.websocket("/ws/voice/{session_id}")
async def voice_ws(websocket: WebSocket, session_id: str, customer_id: int = 0):
    await websocket.accept()
    sess = await sm.get_or_create(session_id)
    if customer_id:
        await sm.update(session_id, customer_id=customer_id, logged_in=True)
        # Sync with main session_store
        from main import session_store, cart_store
        if session_id not in session_store:
            session_store[session_id] = {}
        session_store[session_id]["customer_id"] = customer_id
        session_store[session_id]["logged_in"] = True
    frames: list[bytes] = []

    async def send(obj: dict):
        try:
            await websocket.send_text(json.dumps(obj))
        except Exception:
            pass

    try:
        while True:
            raw = await websocket.receive_text()
            msg = json.loads(raw)
            mtype = msg.get("type")

            if mtype == "ping":
                await send({"type": "pong"})
                continue

            if mtype == "audio":
                pcm = base64.b64decode(msg.get("data", ""))
                new_frames = vad_utils.split_into_frames(pcm)
                frames.extend(new_frames)
                ended, speech_frames = vad_utils.detect_speech_end(frames)
                if ended:
                    frames = []
                    await _handle_speech(speech_frames, session_id, send)
                continue

            if mtype == "end_utterance":
                if frames:
                    speech_frames = frames
                    frames = []
                    await _handle_speech(speech_frames, session_id, send)
                continue

    except WebSocketDisconnect:
        pass
    except Exception as e:
        await send({"type": "error", "message": str(e)})


async def _handle_speech(speech_frames, session_id, send):
    if not speech_frames:
        return

    pcm_bytes = b"".join(speech_frames)

    try:
        async with httpx.AsyncClient(timeout=15, verify=False) as client:
            resp = await client.post(
                "https://172.21.249.153:8002/stt/transcribe",
                content=pcm_bytes,
                headers={"Content-Type": "application/octet-stream"}
            )
            transcript = resp.json().get("transcript", "").strip()
    except Exception as e:
        await send({"type": "error", "message": f"STT failed: {e}"})
        return

    if not transcript:
        return

    await send({"type": "transcript", "text": transcript})

    try:
        reply_text, actions, products = await voice_agent.process_utterance(
            transcript, session_id
        )
    except Exception as e:
        await send({"type": "error", "message": f"Agent failed: {e}"})
        return

    await send({"type": "reply_text", "text": reply_text})
    # Sync cart to frontend
    from main import cart_store
    sess = await sm.get_or_create(session_id)
    if sess.cart:
        cart_store[session_id] = sess.cart
        await send({"type": "sync_cart", "cart": sess.cart, "total": sum(i.get("price",0)*i.get("qty",1) for i in sess.cart)})
    if products:
        await send({"type": "products", "data": products})

    encoded = urllib.parse.quote(reply_text[:500])
    await send({
        "type": "audio_url",
        "url": f"/tts/speak?text={encoded}&voice=aura-hera-en"
    })

    for action in actions:
        if action == "cart_updated":
            from main import cart_store
            cart_store[session_id] = [
                {"sku": i.get("sku"), "name": i.get("name"), "price": i.get("price",0), "qty": i.get("qty",1)}
                for i in (await sm.get_or_create(session_id)).cart or []
            ]
        await send({"type": "action", "name": action, "data": {}})
