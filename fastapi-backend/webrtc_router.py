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
async def voice_ws(websocket: WebSocket, session_id: str):
    await websocket.accept()
    await sm.get_or_create(session_id)
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
        reply_text, actions = await voice_agent.process_utterance(
            transcript, session_id
        )
    except Exception as e:
        await send({"type": "error", "message": f"Agent failed: {e}"})
        return

    await send({"type": "reply_text", "text": reply_text})

    encoded = urllib.parse.quote(reply_text[:500])
    await send({
        "type": "audio_url",
        "url": f"/tts/speak?text={encoded}&voice=aura-asteria-en"
    })

    for action in actions:
        await send({"type": "action", "name": action, "data": {}})
