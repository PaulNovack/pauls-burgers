import io
import os
import threading
from typing import Optional

from fastapi import FastAPI, HTTPException
from fastapi.responses import StreamingResponse, JSONResponse
from pydantic import BaseModel

# Piper (Python) API
#   Example usage:
#     import piper
#     voice = piper.PiperVoice.load("models/en_US-amy-low.onnx")
#     with open("x.wav","wb") as f: voice.synthesize("hello", f)
import piper  # type: ignore

APP_PORT = int(os.getenv("PORT", "8001"))
MODEL_PATH = os.getenv("PIPER_MODEL", "models/en_US-amy-low.onnx")
# Optional: knob defaults (these map to Piper CLI flags where supported)
DEFAULT_LENGTH_SCALE = float(os.getenv("PIPER_LENGTH_SCALE", "1.0"))  # <1.0 = faster
DEFAULT_NOISE_SCALE  = float(os.getenv("PIPER_NOISE_SCALE",  "0.667"))
DEFAULT_NOISE_W      = float(os.getenv("PIPER_NOISE_W",      "0.8"))

# Global voice instance (loaded once)
voice = None
# Serialize synthesis by default (you can relax this later)
synth_lock = threading.Lock()

app = FastAPI(title="Piper TTS HTTP Server")

class SpeakIn(BaseModel):
    text: str
    # Optional tweakables; omit if you want a fixed voice profile
    length_scale: Optional[float] = None
    noise_scale: Optional[float] = None
    noise_w: Optional[float] = None
    # output_format: "wav" | "mp3" later if you wire ffmpeg/pydub

@app.on_event("startup")
def load_model():
    global voice
    if not os.path.isfile(MODEL_PATH):
        raise RuntimeError(f"Piper model not found at {MODEL_PATH}")
    voice = piper.PiperVoice.load(MODEL_PATH)
    # Warm-up: do a tiny synthesis so the first real request is snappy
    _ = io.BytesIO()
    voice.synthesize("ready", _)

@app.get("/health")
def health():
    return {"ok": True, "model": os.path.basename(MODEL_PATH)}

@app.post("/speak")
def speak(payload: SpeakIn):
    if not payload.text or len(payload.text) > 2000:
        raise HTTPException(status_code=400, detail="text is required (<=2000 chars)")

    # Pick per-request params or defaults
    length_scale = payload.length_scale if payload.length_scale is not None else DEFAULT_LENGTH_SCALE
    noise_scale  = payload.noise_scale  if payload.noise_scale  is not None else DEFAULT_NOISE_SCALE
    noise_w      = payload.noise_w      if payload.noise_w      is not None else DEFAULT_NOISE_W

    # Generate audio bytes in memory (WAV)
    try:
        with synth_lock:
            buf = io.BytesIO()
            # Piper can stream to a file-like handle. Most models accept these kwargs.
            voice.synthesize(
                payload.text,
                buf,
                length_scale=length_scale,
                noise_scale=noise_scale,
                noise_w=noise_w,
            )
            buf.seek(0)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"synthesis failed: {e}")

    # Stream the result
    return StreamingResponse(buf, media_type="audio/wav")
    # If you add MP3 via pydub/ffmpeg, convert here and set media_type="audio/mpeg"
