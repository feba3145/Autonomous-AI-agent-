import io

import edge_tts
from fastapi import APIRouter, Query
from fastapi.responses import StreamingResponse
from pydantic import BaseModel

router = APIRouter(prefix="/tts", tags=["tts"])

DEFAULT_VOICE = "en-US-AriaNeural"


class TTSRequest(BaseModel):
    text: str
    voice: str = DEFAULT_VOICE


async def _synth(text: str, voice: str) -> bytes:
    communicate = edge_tts.Communicate(text=text, voice=voice)
    buf = io.BytesIO()
    async for chunk in communicate.stream():
        if chunk["type"] == "audio":
            buf.write(chunk["data"])
    buf.seek(0)
    return buf.read()


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
    voices = await edge_tts.list_voices()
    english = [v for v in voices if v["Locale"].startswith("en-")]
    return {"voices": english}
