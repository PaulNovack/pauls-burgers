# piper-server.py
import io
import os
import json
import tempfile
import logging
import threading
import subprocess
from typing import Any, Union, Optional, Tuple

from fastapi import FastAPI, HTTPException, Response, Request, Body
from pydantic import BaseModel

import uvicorn
import piper  # Piper Python bindings

logger = logging.getLogger("piper-server")
logging.basicConfig(level=logging.INFO, format="%(levelname)s:%(name)s:%(message)s")

# ---------- Config ----------
APP_PORT = int(os.getenv("PORT", "8002"))
MODEL_PATH = os.path.abspath(os.getenv("PIPER_MODEL", "models/en_US-amy-low.onnx"))
ENV_SPEAKER = os.getenv("PIPER_SPEAKER")
DEFAULT_SPEAKER: Optional[int] = int(ENV_SPEAKER) if ENV_SPEAKER not in (None, "",) else None

# ---------- Globals ----------
app = FastAPI(title="Piper TTS HTTP Server")
voice: Optional[piper.PiperVoice] = None
num_speakers: Optional[int] = None
synth_lock = threading.Lock()
warmup_ok: bool = False
warmup_bytes: int = 0
last_probe_detail: str = ""
PIPER_BACKEND = os.getenv("PIPER_BACKEND", "auto").lower()  # auto|binding|cli
backend_last_used = "unknown"  # for /health reporting


class SpeakIn(BaseModel):
    text: str
    # Kept for forward-compat with other builds; unused in this binding
    length_scale: Optional[float] = None
    noise_scale: Optional[float] = None
    noise_w: Optional[float] = None
    speaker: Optional[int] = None
    # Use CLI fallback explicitly per-request if desired
    use_cli_fallback: Optional[bool] = False


# ---------- Helpers ----------
def _file_exists(path: str) -> Tuple[bool, int]:
    try:
        st = os.stat(path)
        return True, st.st_size
    except Exception:
        return False, 0


def _synthesize_binding(text: str, speaker: Optional[int]) -> bytes:
    """
    Try to synthesize with python bindings.
    Strategy:
      1) to real temp file (most compatible)
      2) to BytesIO (some builds support it)
    """
    assert voice is not None
    # Try temp file sink
    with tempfile.NamedTemporaryFile(delete=False, suffix=".wav") as f:
        tmp = f.name
        if speaker is not None:
            try:
                voice.synthesize(text, f, speaker)  # positional speaker
            except TypeError:
                # Some builds might not accept the 3rd arg; try without it
                voice.synthesize(text, f)
        else:
            voice.synthesize(text, f)

    try:
        with open(tmp, "rb") as r:
            data = r.read()
        if data:
            return data
    finally:
        try:
            os.unlink(tmp)
        except Exception:
            logger.warning("Temp cleanup failed for %s", tmp, exc_info=True)

    # Try BytesIO sink (some builds support writing to a binary buffer)
    try:
        buf = io.BytesIO()
        if speaker is not None:
            try:
                voice.synthesize(text, buf, speaker)
            except TypeError:
                voice.synthesize(text, buf)
        else:
            voice.synthesize(text, buf)
        data = buf.getvalue()
        if data:
            return data
    except Exception:
        logger.debug("BytesIO attempt failed", exc_info=True)

    return b""  # binding produced nothing


def _synthesize_cli(text: str, model_path: str) -> bytes:
    with tempfile.NamedTemporaryFile(delete=False, suffix=".wav") as f:
        out = f.name
    try:
        cmd = ["piper", "--model", model_path, "--output_file", out, "--text", text]
        subprocess.run(cmd, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        with open(out, "rb") as r:
            return r.read()
    finally:
        try:
            os.unlink(out)
        except Exception:
            pass


def coerce_text(payload: Union[str, dict, SpeakIn]) -> str:
    """
    Accepts:
      - proper object: {"text": "..."} or SpeakIn
      - raw string: "speak this"
      - stringified JSON: '{"text":"speak this"}'
    Returns the normalized text string.
    """
    # Proper Pydantic model
    if isinstance(payload, SpeakIn):
        return payload.text.strip()

    # Dict-like
    if isinstance(payload, dict):
        if "text" in payload and isinstance(payload["text"], str):
            return payload["text"].strip()
        # Fallback: stringify dict (not ideal, but avoids KeyErrors)
        return json.dumps(payload, ensure_ascii=False)

    # Raw string
    if isinstance(payload, str):
        s = payload.strip()
        # If it *looks* like JSON, try to parse and extract .text
        if s and s[0] in ('{', '['):
            try:
                obj = json.loads(s)
                if isinstance(obj, dict) and isinstance(obj.get("text"), str):
                    return obj["text"].strip()
            except Exception:
                pass
        return s

    # Last resort
    return str(payload).strip()


def _synthesize_to_bytes(text: str, speaker: Optional[int]) -> bytes:
    """
    Unified synth path.
    Tries binding first (when allowed), then CLI.
    Updates global backend_last_used on success.
    """
    global backend_last_used

    data = b""
    used = None

    if PIPER_BACKEND in ("auto", "binding"):
        try:
            data = _synthesize_binding(text, speaker=speaker)
        except Exception:
            logger.debug("Binding synth error", exc_info=True)
            data = b""
        if data:
            used = "binding"

    if not data and PIPER_BACKEND in ("auto", "cli"):
        try:
            data = _synthesize_cli(text, MODEL_PATH)
        except Exception:
            logger.debug("CLI synth error", exc_info=True)
            data = b""
        if data:
            used = "cli"

    if data and used:
        backend_last_used = used

    return data


def _probe_speakers() -> Optional[int]:
    """
    If multi-speaker, try speakers from DEFAULT_SPEAKER (if set) then 0..N-1.
    Return the first speaker index that produces audio, else None.
    """
    global last_probe_detail
    candidates = []
    if DEFAULT_SPEAKER is not None:
        candidates.append(DEFAULT_SPEAKER)

    if num_speakers and num_speakers > 0:
        candidates.extend([i for i in range(num_speakers) if i not in candidates])
    else:
        candidates.append(None)  # single-speaker or unknown — try without specifying

    for spk in candidates:
        try:
            data = _synthesize_binding("ready", spk)
            if data:
                last_probe_detail = f"binding OK (speaker={spk}) produced {len(data)} bytes"
                return spk if spk is not None else -1  # -1 = unspecified but working
        except Exception as e:
            last_probe_detail = f"binding error for speaker={spk}: {e}"

    # Try CLI last (if installed)
    try:
        data = _synthesize_cli("ready", MODEL_PATH)
        if data:
            last_probe_detail = f"CLI OK produced {len(data)} bytes"
            return -2  # indicates CLI works (no fixed speaker)
    except Exception as e:
        last_probe_detail = f"CLI failed: {e}"

    return None


# ---------- Lifespan (replaces deprecated on_event) ----------
@app.on_event("startup")
def startup():
    global voice, num_speakers, warmup_ok, warmup_bytes

    # Validate model + sidecar
    ok_model, size_model = _file_exists(MODEL_PATH)
    sidecar = os.path.splitext(MODEL_PATH)[0] + ".onnx.json"
    ok_json, size_json = _file_exists(sidecar)

    if not ok_model:
        raise RuntimeError(f"Model not found: {MODEL_PATH}")
    if not ok_json:
        raise RuntimeError(f"Sidecar JSON not found: {sidecar}")

    logger.info("Loading model: %s (size=%d), sidecar: %s (size=%d)",
                MODEL_PATH, size_model, sidecar, size_json)

    voice = piper.PiperVoice.load(MODEL_PATH)

    # Try to read num_speakers for probing
    try:
        ns = getattr(voice, "num_speakers", None)
        num_speakers = int(ns) if ns is not None else None
    except Exception:
        num_speakers = None

    spk_choice = _probe_speakers()
    if spk_choice is None:
        warmup_ok = False
        warmup_bytes = 0
        logger.warning("Warm-up failed: %s", last_probe_detail)
        # Keep serving: users can still hit /health and /speak; we'll try again in /speak
    else:
        warmup_ok = True
        # We already synthesized “ready”; do it again to record bytes (binding path)
        data = _synthesize_binding("ready", None if spk_choice in (-2, -1) else spk_choice)
        warmup_bytes = len(data)
        logger.info("Warm-up OK: %d bytes (%s)", warmup_bytes, last_probe_detail)


@app.on_event("shutdown")
def shutdown():
    # Nothing special; let GC handle
    pass


# ---------- Endpoints ----------
@app.get("/health")
def health():
    return {
        "ok": warmup_ok,
        "model": os.path.basename(MODEL_PATH),
        "model_path": MODEL_PATH,
        "num_speakers": num_speakers,
        "default_speaker_env": DEFAULT_SPEAKER,
        "warmup_bytes": warmup_bytes,
        "probe_detail": last_probe_detail,
        "backend_mode": PIPER_BACKEND,
        "backend_last_used": backend_last_used,
    }


@app.post("/speak")
async def speak(request: Request, raw: Any = Body(...)):
    # Normalize the incoming payload to a string to speak
    # (works for {"text":"..."}, "string", or '{"text":"..."}')
    text = coerce_text(raw)

    if not text or len(text) > 2000:
        raise HTTPException(status_code=400, detail="text is required (<=2000 chars)")

    logger.info("Requested synthesis: %r", text)

    # Choose speaker: prefer explicit speaker in body; else DEFAULT_SPEAKER
    speaker = DEFAULT_SPEAKER
    if isinstance(raw, dict) and isinstance(raw.get("speaker"), int):
        speaker = raw["speaker"]

    try:
        with synth_lock:
            data = _synthesize_to_bytes(text, speaker=speaker)
            if not data:
                raise RuntimeError("synthesis produced 0 bytes (both backends)")
            logger.info("Synthesized %d bytes via %s", len(data), backend_last_used)
    except Exception as e:
        logger.exception("Piper synthesis failed")
        raise HTTPException(status_code=500, detail=f"synthesis failed: {e}")

    return Response(
        content=data,
        media_type="audio/wav",
        headers={"Content-Disposition": 'inline; filename="speech.wav"'}
    )


# ---------- Entrypoint ----------
if __name__ == "__main__":
    uvicorn.run(
        "piper-server:app",
        host="0.0.0.0",
        port=APP_PORT,
        reload=False,
        workers=1
    )
