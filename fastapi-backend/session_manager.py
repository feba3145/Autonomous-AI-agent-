import asyncio
import time
from dataclasses import dataclass, field
from typing import Optional


@dataclass
class VoiceSession:
    session_id: str
    customer_id: Optional[int] = None
    logged_in: bool = False
    conversation_history: list = field(default_factory=list)
    cart: list = field(default_factory=list)
    last_active: float = field(default_factory=time.time)
    current_address_label: Optional[str] = None
    pending_checkout: bool = False
    audio_buffer: list = field(default_factory=list)


_sessions: dict[str, VoiceSession] = {}
_lock = asyncio.Lock()


async def get_or_create(session_id: str) -> VoiceSession:
    async with _lock:
        if session_id not in _sessions:
            _sessions[session_id] = VoiceSession(session_id=session_id)
        sess = _sessions[session_id]
        sess.last_active = time.time()
        return sess


async def update(session_id: str, **kwargs) -> VoiceSession:
    async with _lock:
        sess = _sessions.get(session_id)
        if not sess:
            sess = VoiceSession(session_id=session_id)
            _sessions[session_id] = sess
        for k, v in kwargs.items():
            setattr(sess, k, v)
        sess.last_active = time.time()
        return sess


async def delete(session_id: str):
    async with _lock:
        _sessions.pop(session_id, None)


async def append_audio_frame(session_id: str, frame: bytes):
    async with _lock:
        sess = _sessions.get(session_id)
        if sess:
            sess.audio_buffer.append(frame)


async def clear_audio_buffer(session_id: str) -> list[bytes]:
    async with _lock:
        sess = _sessions.get(session_id)
        if not sess:
            return []
        frames = list(sess.audio_buffer)
        sess.audio_buffer = []
        return frames


async def cleanup_old_sessions(max_age_seconds: int = 3600):
    async with _lock:
        now = time.time()
        dead = [sid for sid, s in _sessions.items()
                if now - s.last_active > max_age_seconds]
        for sid in dead:
            del _sessions[sid]
