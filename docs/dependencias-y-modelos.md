# Dependencias, procedencia y límites

`composer.lock` fija lbuchs/webauthn 2.2.0 (MIT). `biometric-service/requirements.txt` fija el entorno Python probado; `requirements.in` conserva dependencias directas. No se incorpora vendor, venv ni pesos al repositorio. Conservar los avisos de licencia que acompañan las descargas; el equipo no entrenó estos modelos.

| Componente | Versión/revisión | Procedencia y licencia publicada |
|---|---|---|
| lbuchs/webauthn | 2.2.0 | [Repositorio oficial](https://github.com/lbuchs/WebAuthn), MIT |
| YuNet ONNX | face_detection_yunet_2023mar; opencv_zoo 47534e27c9851bb1128ccc0102f1145e27f23f98 | [Licencia de este modelo](https://github.com/opencv/opencv_zoo/blob/47534e27c9851bb1128ccc0102f1145e27f23f98/models/face_detection_yunet/LICENSE), MIT, Shiqi Yu |
| SFace ONNX | face_recognition_sface_2021dec; misma revisión | [Licencia de este modelo](https://github.com/opencv/opencv_zoo/blob/47534e27c9851bb1128ccc0102f1145e27f23f98/models/face_recognition_sface/LICENSE), Apache-2.0 |
| ECAPA VoxCeleb | speechbrain/spkrec-ecapa-voxceleb; 0f99f2d0ebe89ac095bcc5903c4dd8f72b367286 | [Model card oficial](https://huggingface.co/speechbrain/spkrec-ecapa-voxceleb/tree/0f99f2d0ebe89ac095bcc5903c4dd8f72b367286), Apache-2.0 |
| OpenCV Python headless | 4.13.0.92 | [OpenCV](https://github.com/opencv/opencv-python), distribución y avisos incluidos en la rueda; revisar también licencias de terceros |
| SpeechBrain | 1.0.3 | [SpeechBrain](https://github.com/speechbrain/speechbrain), Apache-2.0 |
| torch / torchaudio CPU | 2.8.0+cpu | [PyTorch](https://pytorch.org/), avisos BSD y de dependencias incluidos en las ruedas |
| PyAV | 16.1.0 | [PyAV](https://github.com/PyAV-Org/PyAV), BSD-3-Clause; usa FFmpeg incluido en la rueda Windows |

En la DLL realmente instalada, `av_version_info()` informa **FFmpeg 8.0.1** y `avutil_license()` informa **LGPL version 3 or later**. libavcodec 62.11.100, libavformat 62.3.100, libavutil 60.8.100 y libswresample 6.1.100. No se requiere un ejecutable `ffmpeg` adicional en PATH: PyAV decodifica los bytes recibidos. Si se redistribuyen binarios, incluir sus avisos y requisitos de distribución; este Git solo contiene el código y las instrucciones de descarga.

Los pesos se obtienen de URLs con revisiones fijas. `model_config.py` verifica SHA-256 de binarios LFS y Git blob SHA-1 de los archivos pequeños de la revisión; `models.lock.json` registra SHA-256, tamaño y origen de todos los archivos descargados. Cada arranque verifica otra vez los modelos antes de habilitarlos. No cargar YAML ni checkpoints de terceros arbitrarios: los checkpoints/configuraciones se consideran código de confianza fijado por el proyecto.

Preprocesamiento: rostro único, alineación SFace, vector normalizado L2 de 128 dimensiones; voz decodificada, mono 16 kHz, ECAPA 192 dimensiones normalizadas. Verificación 1:1 mediante coseno medio contra tres vectores de registro. Umbrales iniciales experimentales: rostro 0.45, voz 0.65; versión explícita en cada plantilla. Son decisiones iniciales del prototipo, no valores de precisión validados ni equivalentes a los benchmarks de los autores.

No hay prueba de vida, detección garantizada de deepfakes ni resistencia garantizada a grabaciones. Las plantillas no son anónimas; su tratamiento y revocación están descritos en el aviso de consentimiento y el manual. La evaluación humana está pendiente.
