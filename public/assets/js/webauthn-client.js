/**
 * webauthn-client.js
 * Flujo WebAuthn real para autenticación (navigator.credentials.get).
 * Serializa la credencial en base64url para enviarla al servidor.
 * No acepta booleano del cliente como verificación — la validación es en PHP.
 */

/**
 * Convierte un ArrayBuffer o Uint8Array a base64url.
 */
function bufferToBase64url(buffer) {
  const bytes = new Uint8Array(buffer);
  let str = '';
  bytes.forEach(b => (str += String.fromCharCode(b)));
  return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

/**
 * Convierte una cadena base64url a ArrayBuffer.
 */
function base64urlToBuffer(b64url) {
  if (!b64url) throw new Error('base64url vacío');
  const b64 = b64url.replace(/-/g, '+').replace(/_/g, '/');
  const bin = atob(b64);
  const bytes = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
  return bytes.buffer;
}

/**
 * Deserializa las opciones de autenticación recibidas del servidor.
 * El servidor envía challenge y allowCredentials[].id como base64url.
 */
function _deserializeGetOptions(opts) {
  const pk = JSON.parse(JSON.stringify(opts)); // copia profunda
  pk.challenge = base64urlToBuffer(pk.challenge);
  if (Array.isArray(pk.allowCredentials)) {
    pk.allowCredentials = pk.allowCredentials.map(c => ({
      ...c,
      id: base64urlToBuffer(c.id),
    }));
  }
  return pk;
}

/**
 * Ejecuta la ceremonia de autenticación WebAuthn.
 * @param {object} publicKeyOptions — opciones serializadas del servidor (base64url)
 * @returns {{ ok: boolean, payload?: object, error?: string }}
 */
async function authenticate(publicKeyOptions) {
  if (!window.PublicKeyCredential) {
    return { ok: false, error: 'WebAuthn no está disponible en este navegador.' };
  }

  let pk;
  try {
    pk = _deserializeGetOptions(publicKeyOptions);
  } catch (e) {
    return { ok: false, error: 'Opciones de WebAuthn inválidas recibidas del servidor.' };
  }

  let cred;
  try {
    cred = await navigator.credentials.get({ publicKey: pk });
  } catch (err) {
    return { ok: false, error: _webauthnErrorMsg(err) };
  }

  if (!cred) {
    return { ok: false, error: 'No se recibió respuesta del autenticador.' };
  }

  // Serializar la credencial para enviarla al servidor
  const payload = {
    id:   cred.id,
    rawId: bufferToBase64url(cred.rawId),
    type: cred.type,
    response: {
      clientDataJSON:    bufferToBase64url(cred.response.clientDataJSON),
      authenticatorData: bufferToBase64url(cred.response.authenticatorData),
      signature:         bufferToBase64url(cred.response.signature),
    },
  };

  // userHandle es opcional
  if (cred.response.userHandle) {
    payload.response.userHandle = bufferToBase64url(cred.response.userHandle);
  }

  // Extensiones si las hay
  if (cred.getClientExtensionResults) {
    const ext = cred.getClientExtensionResults();
    if (ext && Object.keys(ext).length) payload.extensions = ext;
  }

  return { ok: true, payload };
}

/**
 * Deserializa opciones de creación (para registro/enrolamiento).
 */
function _deserializeCreateOptions(opts) {
  const pk = JSON.parse(JSON.stringify(opts));
  pk.challenge = base64urlToBuffer(pk.challenge);
  pk.user.id   = base64urlToBuffer(pk.user.id);
  if (Array.isArray(pk.excludeCredentials)) {
    pk.excludeCredentials = pk.excludeCredentials.map(c => ({
      ...c,
      id: base64urlToBuffer(c.id),
    }));
  }
  return pk;
}

/**
 * Ejecuta la ceremonia de registro (creación) WebAuthn.
 * @param {object} publicKeyOptions — opciones de creación del servidor
 * @returns {{ ok: boolean, payload?: object, error?: string }}
 */
async function register(publicKeyOptions) {
  if (!window.PublicKeyCredential) {
    return { ok: false, error: 'WebAuthn no está disponible en este navegador.' };
  }

  let pk;
  try {
    pk = _deserializeCreateOptions(publicKeyOptions);
  } catch (e) {
    return { ok: false, error: 'Opciones de registro WebAuthn inválidas.' };
  }

  let cred;
  try {
    cred = await navigator.credentials.create({ publicKey: pk });
  } catch (err) {
    return { ok: false, error: _webauthnErrorMsg(err) };
  }

  if (!cred) {
    return { ok: false, error: 'El registro fue cancelado.' };
  }

  const payload = {
    id:   cred.id,
    rawId: bufferToBase64url(cred.rawId),
    type: cred.type,
    response: {
      attestationObject: bufferToBase64url(cred.response.attestationObject),
      clientDataJSON:    bufferToBase64url(cred.response.clientDataJSON),
    },
  };

  if (cred.getClientExtensionResults) {
    const ext = cred.getClientExtensionResults();
    if (ext && Object.keys(ext).length) payload.extensions = ext;
  }

  return { ok: true, payload };
}

function _webauthnErrorMsg(err) {
  if (!err) return 'Error desconocido en el autenticador.';
  switch (err.name) {
    case 'NotAllowedError':
      return 'El registro o verificación fue cancelado o el tiempo expiró.';
    case 'InvalidStateError':
      return 'Este autenticador ya está registrado para esta cuenta.';
    case 'NotSupportedError':
      return 'El autenticador solicitado no es compatible con este dispositivo.';
    case 'SecurityError':
      return 'Error de seguridad: verifique que la conexión sea HTTPS.';
    case 'AbortError':
      return 'La operación fue abortada.';
    default:
      return 'Error en el autenticador: ' + (err.message || err.name);
  }
}

export const WebAuthnClient = {
  authenticate,
  register,
  bufferToBase64url,
  base64urlToBuffer,
};
