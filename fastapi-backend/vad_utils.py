import webrtcvad
import struct
import numpy as np

SAMPLE_RATE = 16000
FRAME_DURATION_MS = 20
FRAME_SIZE = int(SAMPLE_RATE * FRAME_DURATION_MS / 1000)
FRAME_BYTES = FRAME_SIZE * 2

vad = webrtcvad.Vad(2)


def is_speech(pcm_bytes: bytes) -> bool:
    if len(pcm_bytes) != FRAME_BYTES:
        return False
    try:
        return vad.is_speech(pcm_bytes, SAMPLE_RATE)
    except Exception:
        return False


def float32_to_pcm16(float_array: np.ndarray) -> bytes:
    clipped = np.clip(float_array, -1.0, 1.0)
    int16_array = (clipped * 32767).astype(np.int16)
    return int16_array.tobytes()


def split_into_frames(pcm_bytes: bytes) -> list[bytes]:
    frames = []
    for i in range(0, len(pcm_bytes) - FRAME_BYTES + 1, FRAME_BYTES):
        frames.append(pcm_bytes[i:i + FRAME_BYTES])
    return frames


def detect_speech_end(
    frames: list[bytes],
    speech_pad_frames: int = 8,
    silence_threshold_frames: int = 25
) -> tuple[bool, list[bytes]]:
    flags = [is_speech(f) for f in frames]
    first_speech = next((i for i, v in enumerate(flags) if v), None)
    if first_speech is None:
        return False, []
    last_speech = len(flags) - 1 - next(
        (i for i, v in enumerate(reversed(flags)) if v), 0
    )
    silence_after = len(flags) - 1 - last_speech
    if silence_after >= silence_threshold_frames:
        start = max(0, first_speech - speech_pad_frames)
        end = min(len(frames), last_speech + speech_pad_frames + 1)
        return True, frames[start:end]
    return False, []
