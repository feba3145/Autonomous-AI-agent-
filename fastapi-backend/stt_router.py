import io
import wave
import httpx
from fastapi import APIRouter, Request
from fastapi.responses import JSONResponse

router = APIRouter(prefix="/stt", tags=["stt"])
DEEPGRAM_API_KEY = "8c43f7782d8e9ab96fa4a41fe0bd0a601eae8e9e"

def pcm_to_wav(pcm_bytes: bytes, sample_rate: int = 16000) -> bytes:
    buf = io.BytesIO()
    with wave.open(buf, "wb") as wf:
        wf.setnchannels(1)
        wf.setsampwidth(2)
        wf.setframerate(sample_rate)
        wf.writeframes(pcm_bytes)
    return buf.getvalue()

@router.post("/transcribe")
async def transcribe(request: Request):
    pcm_bytes = await request.body()
    if len(pcm_bytes) < 640:
        return JSONResponse({"transcript": ""})
    wav_bytes = pcm_to_wav(pcm_bytes)
    async with httpx.AsyncClient(timeout=15) as client:
        resp = await client.post(
            "https://api.deepgram.com/v1/listen?model=nova-2&language=en",
            headers={
                "Authorization": f"Token {DEEPGRAM_API_KEY}",
                "Content-Type": "audio/wav"
            },
            content=wav_bytes
        )
        result = resp.json()
        transcript = result["results"]["channels"][0]["alternatives"][0]["transcript"]
    return JSONResponse({"transcript": transcript})
