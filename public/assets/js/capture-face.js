/**
 * capture-face.js
 * Acceso a cámara, vista previa en <video> y captura de imagen como Blob.
 * La cámara se detiene al cancelar, navegar o completar la captura.
 */

let _stream = null;
let _videoEl = null;

/**
 * Inicia la cámara y muestra el stream en el elemento <video> dado.
 * @param {HTMLVideoElement} videoEl
 * @returns {{ ok: boolean, error?: string }}
 */
async function startCamera(videoEl) {
  // Detener stream previo si existía
  stopCamera();

  try {
    _stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
      audio: false,
    });
    _videoEl = videoEl;
    _videoEl.srcObject = _stream;
    await _videoEl.play();
    return { ok: true };
  } catch (err) {
    stopCamera();
    const msg = _cameraErrorMsg(err);
    return { ok: false, error: msg };
  }
}

/**
 * Captura el frame actual del <video> como Blob JPEG.
 * @returns {Blob|null}
 */
async function capture() {
  if (!_videoEl || !_stream) return null;

  const canvas = document.createElement('canvas');
  canvas.width  = _videoEl.videoWidth  || 640;
  canvas.height = _videoEl.videoHeight || 480;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(_videoEl, 0, 0, canvas.width, canvas.height);

  return new Promise(resolve => {
    canvas.toBlob(blob => resolve(blob), 'image/jpeg', 0.92);
  });
}

/**
 * Detiene todos los tracks de la cámara y limpia la referencia al video.
 */
function stopCamera() {
  if (_stream) {
    _stream.getTracks().forEach(t => t.stop());
    _stream = null;
  }
  if (_videoEl) {
    _videoEl.srcObject = null;
    _videoEl = null;
  }
}

function _cameraErrorMsg(err) {
  if (!err) return 'Error desconocido al acceder a la cámara.';
  switch (err.name) {
    case 'NotAllowedError':
    case 'PermissionDeniedError':
      return 'Permiso de cámara denegado. Revise la configuración del navegador.';
    case 'NotFoundError':
    case 'DevicesNotFoundError':
      return 'No se encontró ninguna cámara en este dispositivo.';
    case 'NotReadableError':
    case 'TrackStartError':
      return 'La cámara está siendo utilizada por otra aplicación.';
    case 'OverconstrainedError':
      return 'La cámara no cumple los requisitos solicitados.';
    default:
      return 'No se pudo acceder a la cámara: ' + (err.message || err.name);
  }
}

// Detener cámara si la página se cierra o navega
window.addEventListener('pagehide', stopCamera);
window.addEventListener('beforeunload', stopCamera);

export const CaptureFace = { startCamera, capture, stopCamera };
