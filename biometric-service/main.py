"""Loopback-only biometric API. Start using start-service.ps1."""
import asyncio
from contextlib import asynccontextmanager
import hmac
import json
import multiprocessing as mp
from pathlib import Path
import queue
from fastapi import FastAPI, Request, HTTPException
from fastapi.responses import JSONResponse
from starlette.datastructures import UploadFile
from engine import worker

ROOT = Path(__file__).resolve().parent
MAX_FILE = 8 * 1024 * 1024

def config():
    values = {}
    for line in (ROOT.parent / ".env").read_text(encoding="utf-8").splitlines():
        if "=" in line and not line.startswith("#"):
            key, value = line.split("=", 1)
            values[key.strip()] = value.strip().strip('"\'')
    if len(values.get("BIOMETRIC_TOKEN", "")) < 32:
        raise RuntimeError("Service token not configured")
    return values

class Runtime:
    def __init__(self):
        self.process = self.inbox = self.outbox = None
        self.status = {"face": False, "voice": False}
        self.lock = asyncio.Lock()

    async def start(self):
        context = mp.get_context("spawn")
        self.inbox, self.outbox = context.Queue(1), context.Queue(1)
        self.process = context.Process(target=worker, args=(self.inbox, self.outbox), daemon=True)
        self.process.start()
        try:
            self.status = await asyncio.to_thread(self.outbox.get, True, 120)
        except queue.Empty:
            self.stop()

    def stop(self):
        if self.process and self.process.is_alive():
            self.process.terminate()
            self.process.join(timeout=5)
            if self.process.is_alive():
                self.process.kill()
        self.status = {"face": False, "voice": False}
        for channel in (self.inbox, self.outbox):
            if channel:
                channel.cancel_join_thread()
                channel.close()

runtime = Runtime()

@asynccontextmanager
async def lifespan(app):
    app.state.token = config()["BIOMETRIC_TOKEN"]
    await runtime.start()
    yield
    runtime.stop()

app = FastAPI(title="Acceso · servicio local", lifespan=lifespan, docs_url=None, redoc_url=None, openapi_url=None)

class RequestGuard:
    def __init__(self, app):
        self.inner = app

    async def __call__(self, scope, receive, send):
        if scope["type"] != "http":
            return await self.inner(scope, receive, send)
        headers = dict(scope.get("headers", []))
        token = getattr(app.state, "token", "")
        supplied = headers.get(b"authorization", b"")
        if scope.get("client", ("",))[0] not in {"127.0.0.1", "::1"} or not token or not hmac.compare_digest(supplied, ("Bearer " + token).encode("ascii")):
            return await JSONResponse({"ok": False}, status_code=403)(scope, receive, send)
        # Read with a total cap before Starlette parses multipart; no unbounded spool.
        body = bytearray()
        while True:
            try:
                message = await asyncio.wait_for(receive(), timeout=10)
            except asyncio.TimeoutError:
                return await JSONResponse({"ok": False}, status_code=408)(scope, receive, send)
            if message["type"] == "http.disconnect":
                return
            body.extend(message.get("body", b""))
            if len(body) > 25 * 1024 * 1024:
                return await JSONResponse({"ok": False}, status_code=413)(scope, receive, send)
            if not message.get("more_body", False):
                break
        sent = False
        async def limited_receive():
            nonlocal sent
            if not sent:
                sent = True
                return {"type": "http.request", "body": bytes(body), "more_body": False}
            return await receive()
        await self.inner(scope, limited_receive, send)

app.add_middleware(RequestGuard)

@app.get("/health")
async def health():
    return {"ok": True, "face": bool(runtime.status.get("face")), "voice": bool(runtime.status.get("voice"))}

@app.post("/{purpose}/{method}")
async def process(purpose: str, method: str, request: Request):
    if method not in {"face", "voice"} or purpose not in {"enroll", "verify"}:
        raise HTTPException(404)
    if not runtime.status.get(method) or runtime.lock.locked():
        raise HTTPException(503)
    async with runtime.lock:
        blobs = []
        async with request.form(max_files=3, max_fields=3, max_part_size=262144) as form:
            files = [(key, value) for key, value in form.multi_items() if isinstance(value, UploadFile)]
            if len(files) != (3 if purpose == "enroll" else 1):
                raise HTTPException(422)
            for key, upload in files:
                if (purpose == "verify" and key != "sample") or (purpose == "enroll" and key not in {"samples[0]", "samples[1]", "samples[2]", "samples[]"}):
                    raise HTTPException(422)
                blob = await upload.read(MAX_FILE + 1)
                if not 20 <= len(blob) <= MAX_FILE:
                    raise HTTPException(422)
                blobs.append(blob)
            raw_reference = form.get("reference", "{}")
            if not isinstance(raw_reference, str) or len(raw_reference) > 262144:
                raise HTTPException(422)
            try:
                reference = json.loads(raw_reference)
            except (TypeError, ValueError):
                raise HTTPException(422)
            if not isinstance(reference, dict):
                raise HTTPException(422)
        runtime.inbox.put_nowait((method, purpose, blobs, reference))
        try:
            result = await asyncio.to_thread(runtime.outbox.get, True, 30)
        except queue.Empty:
            runtime.stop()  # No timed-out inference continues accepting work.
            raise HTTPException(503)
        status = result.pop("status", 503)
        return JSONResponse(result, status_code=status)
