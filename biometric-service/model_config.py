"""Pinned, official model sources. No participant data belongs here."""
OPENCV_REVISION = "47534e27c9851bb1128ccc0102f1145e27f23f98"
VOICE_REVISION = "0f99f2d0ebe89ac095bcc5903c4dd8f72b367286"
MODEL_FILES = {
    "face/yunet.onnx": {
        "url": f"https://media.githubusercontent.com/media/opencv/opencv_zoo/{OPENCV_REVISION}/models/face_detection_yunet/face_detection_yunet_2023mar.onnx",
        "sha256": "8f2383e4dd3cfbb4553ea8718107fc0423210dc964f9f4280604804ed2552fa4",
    },
    "face/sface.onnx": {
        "url": f"https://media.githubusercontent.com/media/opencv/opencv_zoo/{OPENCV_REVISION}/models/face_recognition_sface/face_recognition_sface_2021dec.onnx",
        "sha256": "0ba9fbfa01b5270c96627c4ef784da859931e02f04419c829e83484087c34e79",
    },
}
for name, digest, kind in [
    ("embedding_model.ckpt", "0575cb64845e6b9a10db9bcb74d5ac32b326b8dc90352671d345e2ee3d0126a2", "sha256"),
    ("classifier.ckpt", "fd9e3634fe68bd0a427c95e354c0c677374f62b3f434e45b78599950d860d535", "sha256"),
    ("mean_var_norm_emb.ckpt", "1978c5e7f20d5ffd14c8a932a7e85a09816f3da9", "gitsha1"),
    ("hyperparams.yaml", "70e4cd0beb74ca08a2df9de6bd79d938670a4d15", "gitsha1"),
    ("label_encoder.txt", "72c6fa7b170cbfd169df5e09326cd97febdf086a", "gitsha1"),
]:
    MODEL_FILES[f"voice/{name}"] = {"url": f"https://huggingface.co/speechbrain/spkrec-ecapa-voxceleb/resolve/{VOICE_REVISION}/{name}", kind: digest}

# Initial experimental values, not calibrated population performance.
THRESHOLDS = {"face": 0.45, "voice": 0.65}
VERSIONS = {"face": f"yunet-sface:{OPENCV_REVISION}:rgb-align112-v1", "voice": f"ecapa:{VOICE_REVISION}:mono16k-v1"}
THRESHOLD_VERSIONS = {method: f"cosine-mean-{threshold}-experimental-v1" for method, threshold in THRESHOLDS.items()}
