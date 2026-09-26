"""Download only fixed official weights; verify before atomic replacement."""
import hashlib
import json
from pathlib import Path
from urllib.request import Request, urlopen
from model_config import MODEL_FILES, OPENCV_REVISION, VOICE_REVISION

ROOT = Path(__file__).resolve().parent

def checked(data, spec):
    if "sha256" in spec:
        return hashlib.sha256(data).hexdigest() == spec["sha256"]
    return hashlib.sha1(b"blob " + str(len(data)).encode() + b"\0" + data).hexdigest() == spec["gitsha1"]

def main():
    manifest = {"opencvRevision": OPENCV_REVISION, "voiceRevision": VOICE_REVISION, "files": {}}
    for relative, spec in MODEL_FILES.items():
        path = ROOT / "models" / relative
        data = path.read_bytes() if path.exists() else b""
        if not checked(data, spec):
            print(f"Downloading {relative}", flush=True)
            with urlopen(Request(spec["url"], headers={"User-Agent": "Acceso-ADA/1.0"}), timeout=180) as response:
                data = response.read(150 * 1024 * 1024)
            if not checked(data, spec):
                raise RuntimeError(f"Checksum mismatch: {relative}")
            path.parent.mkdir(parents=True, exist_ok=True)
            part = path.with_suffix(path.suffix + ".part")
            part.write_bytes(data)
            part.replace(path)
        manifest["files"][relative] = {"sha256": hashlib.sha256(data).hexdigest(), "bytes": len(data), "source": spec["url"]}
        print(f"Verified {relative}", flush=True)
    (ROOT / "models.lock.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")

if __name__ == "__main__":
    main()
