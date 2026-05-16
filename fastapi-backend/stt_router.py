import io
import wave
import tempfile
import os

from fastapi import APIRouter, Request, HTTPException
from fastapi.responses import JSONResponse
from faster_whisper import WhisperModel

router = APIRouter(prefix="/stt", tags=["stt"])

_model = None


def get_model():
    global _model
    if _model is None:
        _model = WhisperModel("tiny", device="cpu", compute_type="int8")
    return _model


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

    with tempfile.NamedTemporaryFile(suffix=".wav", delete=False) as f:
        f.write(wav_bytes)
        tmp_path = f.name

    try:
        model = get_model()
        segments, _ = model.transcribe(
            tmp_path,
            language="en",
            beam_size=1,
            vad_filter=True,
            vad_parameters={"min_silence_duration_ms": 300}
        )
        transcript = " ".join(seg.text.strip() for seg in segments).strip()
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        os.unlink(tmp_path)

    return JSONResponse({"transcript": transcript})
