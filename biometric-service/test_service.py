"""HTTP smoke tests: generated signals only; not evidence of biometric accuracy."""
import io
from pathlib import Path
import wave
import av
import httpx
import numpy as np
from PIL import Image

values = dict(line.split("=", 1) for line in (Path(__file__).resolve().parent.parent / ".env").read_text().splitlines() if "=" in line and not line.startswith("#"))
client = httpx.Client(base_url=values["BIOMETRIC_URL"], timeout=40, trust_env=False)
count = 0
def check(condition, label):
    global count
    assert condition, label
    count += 1
    print("PASS", label)

def wav(samples):
    output = io.BytesIO()
    with wave.open(output, "wb") as stream:
        stream.setnchannels(1)
        stream.setsampwidth(2)
        stream.setframerate(16000)
        stream.writeframes((samples * 32767).astype("<i2").tobytes())
    return output.getvalue()

check(client.get("/health").status_code == 403, "Service rejects anonymous calls")
check(client.get("/health", headers={b"Authorization": b"Bearer \xff"}).status_code == 403, "Malformed authorization rejected")
client.headers["Authorization"] = "Bearer " + values["BIOMETRIC_TOKEN"]
health = client.get("/health")
check(health.status_code == 200 and health.json() == {"ok": True, "face": True, "voice": True}, "Both real models loaded")
for method in ("face", "voice"):
    result = client.post("/verify/" + method, files={"sample": ("fake", b"not a media file" * 4)})
    check(result.status_code == 422, method + " rejects false media")
blank = io.BytesIO()
Image.new("RGB", (320, 320)).save(blank, format="PNG")
check(client.post("/verify/face", files={"sample": ("blank.png", blank.getvalue())}).status_code == 422, "No face rejected")
for label, signal in [("Silence", np.zeros(64000)), ("Too short", np.sin(np.arange(8000) / 10) * .15), ("Too long", np.sin(np.arange(208000) / 10) * .15)]:
    check(client.post("/verify/voice", files={"sample": ("signal.wav", wav(signal))}).status_code == 422, label + " rejected")
# Synthetic chirp exercises decoding and actual ECAPA inference, not speaker identity.
t = np.arange(64000) / 16000
sample = wav(.15 * np.sin(2 * np.pi * (180 * t + 50 * t * t)))
result = client.post("/verify/voice", files={"sample": ("signal.wav", sample)})
check(result.status_code == 200 and result.json().get("match") is False, "Inference without reference never authenticates")
result = client.post("/enroll/voice", files=[("samples[]", ("signal.wav", sample)) for _ in range(3)])
check(result.status_code == 200 and len(result.json()["template"]["vectors"][0]) == 192, "Real ECAPA embedding has 192 dimensions")
for container_format, codec, rate in [("webm", "libopus", 48000), ("ogg", "libopus", 48000), ("mp4", "aac", 48000)]:
    encoded = io.BytesIO()
    with av.open(encoded, "w", format=container_format) as output:
        stream = output.add_stream(codec, rate=rate)
        stream.layout = "mono"
        frame = av.AudioFrame.from_ndarray((.15 * np.sin(2 * np.pi * (180 * t + 50 * t * t))).astype(np.float32)[None, :], format="fltp", layout="mono")
        frame.sample_rate = 16000
        for packet in stream.encode(frame):
            output.mux(packet)
        for packet in stream.encode(None):
            output.mux(packet)
    result = client.post("/verify/voice", files={"sample": ("signal." + container_format, encoded.getvalue())})
    check(result.status_code == 200 and result.json().get("match") is False, container_format + " decoded and inferred without granting access")
print(f"{count} service checks passed. Human genuine/impostor trials remain pending.")
