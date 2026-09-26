"""Model inference in a single dedicated child process. All media stay in memory."""
import io
import json
import os
from pathlib import Path
import numpy as np
from model_config import MODEL_FILES, THRESHOLDS, VERSIONS, THRESHOLD_VERSIONS
from download_models import checked

ROOT = Path(__file__).resolve().parent
os.environ["HF_HUB_OFFLINE"] = "1"
os.environ["HF_HUB_DISABLE_TELEMETRY"] = "1"

class InvalidSample(Exception):
    pass

class Engine:
    def __init__(self):
        self.face = self.voice = None
        self.status = {"face": False, "voice": False}
        for method in self.status:
            try:
                for relative, spec in MODEL_FILES.items():
                    if relative.startswith(method + "/") and not checked((ROOT / "models" / relative).read_bytes(), spec):
                        raise RuntimeError("Model integrity failure")
                if method == "face":
                    import cv2
                    cv2.setNumThreads(2)
                    detector = cv2.FaceDetectorYN.create(str(ROOT / "models/face/yunet.onnx"), "", (320, 320), 0.9, 0.3, 5000)
                    recognizer = cv2.FaceRecognizerSF.create(str(ROOT / "models/face/sface.onnx"), "")
                    self.face = (detector, recognizer)
                else:
                    import torch
                    torch.set_num_threads(2)
                    from speechbrain.inference.classifiers import EncoderClassifier
                    from speechbrain.utils.fetching import LocalStrategy
                    from hyperpyyaml import load_hyperpyyaml
                    source = str(ROOT / "models/voice")
                    # Load the checksum-verified local YAML/weights directly. SpeechBrain
                    # 1.0.3 from_hparams tries to copy an absent optional custom.py on Windows.
                    with (ROOT / "models/voice/hyperparams.yaml").open(encoding="utf-8") as stream:
                        params = load_hyperpyyaml(stream, {"pretrained_path": source.replace("\\", "/")})
                    params["pretrainer"].collect_files(default_source=source, local_strategy=LocalStrategy.NO_LINK)
                    params["pretrainer"].load_collected()
                    self.voice = EncoderClassifier(params["modules"], params, run_opts={"device": "cpu"})
                self.status[method] = True
            except Exception as exc:
                # Class only: never print media, references, service tokens or participant data.
                print(f"Model {method} unavailable: {type(exc).__name__}", flush=True)

    @staticmethod
    def normalized(vector):
        vector = np.asarray(vector, dtype=np.float32).reshape(-1)
        norm = np.linalg.norm(vector)
        if not np.isfinite(vector).all() or norm < 1e-8:
            raise InvalidSample("Invalid embedding")
        return vector / norm

    def face_vector(self, data):
        import cv2
        from PIL import Image
        try:
            with Image.open(io.BytesIO(data)) as image:
                width, height = image.size
                if image.format not in {"JPEG", "PNG", "WEBP"} or min(width, height) < 160 or max(width, height) > 4096 or width * height > 4_000_000:
                    raise InvalidSample("Image dimensions")
                image.verify()
            frame = cv2.imdecode(np.frombuffer(data, np.uint8), cv2.IMREAD_COLOR)
            if frame is None:
                raise InvalidSample("Cannot decode image")
            detector, recognizer = self.face
            detector.setInputSize((frame.shape[1], frame.shape[0]))
            _, faces = detector.detect(frame)
            if faces is None or len(faces) != 1 or min(faces[0][2:4]) < 80:
                raise InvalidSample("Exactly one clear face required")
            aligned = recognizer.alignCrop(frame, faces[0])
            if cv2.Laplacian(cv2.cvtColor(aligned, cv2.COLOR_BGR2GRAY), cv2.CV_64F).var() < 20:
                raise InvalidSample("Image blurred")
            return self.normalized(recognizer.feature(aligned))
        except InvalidSample:
            raise
        except Exception as exc:
            raise InvalidSample("Invalid image") from exc

    def voice_vector(self, data):
        import av
        import torch
        try:
            chunks = []
            length = 0
            with av.open(io.BytesIO(data)) as container:
                if len(container.streams.audio) != 1 or len(container.streams.video):
                    raise InvalidSample("Audio only")
                resampler = av.AudioResampler(format="fltp", layout="mono", rate=16000)
                for frame in container.decode(audio=0):
                    for converted in resampler.resample(frame):
                        chunk = converted.to_ndarray().reshape(-1)
                        length += len(chunk)
                        if length > 12 * 16000:
                            raise InvalidSample("Audio too long")
                        chunks.append(chunk)
                for converted in resampler.resample(None):
                    chunks.append(converted.to_ndarray().reshape(-1))
            waveform = np.concatenate(chunks).astype(np.float32) if chunks else np.empty(0)
            if not 3 * 16000 <= len(waveform) <= 12 * 16000 or not np.isfinite(waveform).all():
                raise InvalidSample("Audio duration")
            if np.sqrt(np.mean(waveform ** 2)) < 0.005 or np.mean(np.abs(waveform) >= 0.99) > 0.2:
                raise InvalidSample("Silent or clipped audio")
            with torch.inference_mode():
                vector = self.voice.encode_batch(torch.from_numpy(waveform).unsqueeze(0)).squeeze().cpu().numpy()
            return self.normalized(vector)
        except InvalidSample:
            raise
        except Exception as exc:
            raise InvalidSample("Invalid audio") from exc

    def process(self, method, purpose, samples, reference):
        if not self.status.get(method):
            return {"status": 503, "ok": False}
        extract = self.face_vector if method == "face" else self.voice_vector
        vectors = [extract(sample) for sample in samples]
        if purpose == "enroll":
            similarities = [float(np.dot(vectors[i], vectors[j])) for i in range(3) for j in range(i)]
            if min(similarities) < THRESHOLDS[method]:
                raise InvalidSample("Enrollment samples inconsistent")
            return {"status": 200, "ok": True, "template": {"modelVersion": VERSIONS[method], "thresholdVersion": THRESHOLD_VERSIONS[method], "vectors": [v.tolist() for v in vectors]}}
        if not reference:
            return {"status": 200, "ok": True, "match": False}
        if reference.get("modelVersion") != VERSIONS[method] or reference.get("thresholdVersion") != THRESHOLD_VERSIONS[method]:
            return {"status": 503, "ok": False}
        refs = np.asarray(reference.get("vectors"), dtype=np.float32)
        expected = 128 if method == "face" else 192
        if refs.shape != (3, expected) or not np.isfinite(refs).all():
            return {"status": 503, "ok": False}
        scores = [float(np.dot(vectors[0], self.normalized(ref))) for ref in refs]
        score = float(np.mean(scores))
        return {"status": 200, "ok": True, "match": score >= THRESHOLDS[method], "score": score}

def worker(inbox, outbox):
    engine = Engine()
    outbox.put({"ok": True, **engine.status})
    while True:
        task = inbox.get()
        if task is None:
            return
        try:
            result = engine.process(*task)
        except InvalidSample:
            result = {"status": 422, "ok": False}
        except Exception:
            result = {"status": 503, "ok": False}
        outbox.put(result)
