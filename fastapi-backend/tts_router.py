import httpx
import io
from fastapi import APIRouter, Query
from fastapi.responses import StreamingResponse
from pydantic import BaseModel

router = APIRouter(prefix="/tts", tags=["tts"])

DEEPGRAM_API_KEY = "8c43f7782d8e9ab96fa4a41fe0bd0a601eae8e9e"
DEFAULT_VOICE = "aura-asteria-en"

class TTSRequest(BaseModel):
    text: str
    voice: str = DEFAULT_VOICE

async def _synth(text: str, voice: str) -> bytes:
    async with httpx.AsyncClient(timeout=30) as client:
        resp = await client.post(
            "https://api.deepgram.com/v1/speak?model=aura-asteria-en",
            headers={
                "Authorization": f"Token {DEEPGRAM_API_KEY}",
                "Content-Type": "application/json"
            },
            json={"text": text}
        )
        resp.raise_for_status()
        return resp.content

@router.get("/speak")
async def speak_get(
    text: str = Query(...),
    voice: str = Query(DEFAULT_VOICE)
):
    audio = await _synth(text, voice)
    return StreamingResponse(
        io.BytesIO(audio),
        media_type="audio/mpeg",
        headers={"Cache-Control": "no-cache"}
    )

@router.post("/speak")
async def speak_post(req: TTSRequest):
    audio = await _synth(req.text, req.voice)
    return StreamingResponse(
        io.BytesIO(audio),
        media_type="audio/mpeg",
        headers={"Cache-Control": "no-cache"}
    )

@router.get("/voices")
async def list_voices():
    return {"voices": [
        {"ShortName": "aura-asteria-en", "FriendlyName": "Asteria (Female)"},
        {"ShortName": "aura-luna-en",    "FriendlyName": "Luna (Female)"},
        {"ShortName": "aura-zeus-en",    "FriendlyName": "Zeus (Male)"},
        {"ShortName": "aura-orion-en",   "FriendlyName": "Orion (Male)"},
    ]}
