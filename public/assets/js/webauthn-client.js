export const WebAuthnClient = {
  // Convert ArrayBuffer to Base64Url
  bufferToBase64url(buffer) {
    const bytes = new Uint8Array(buffer);
    let str = '';
    for (let charCode of bytes) {
      str += String.fromCharCode(charCode);
    }
    const base64 = window.btoa(str);
    return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
  },

  // Convert Base64Url to ArrayBuffer
  base64urlToBuffer(base64url) {
    const padding = '='.repeat((4 - (base64url.length % 4)) % 4);
    const base64 = (base64url + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray.buffer;
  },

  async authenticate(publicKeyOptionsBase64) {
    try {
      const options = { ...publicKeyOptionsBase64 };
      options.challenge = this.base64urlToBuffer(options.challenge);
      
      if (options.allowCredentials) {
        options.allowCredentials = options.allowCredentials.map(cred => ({
          ...cred,
          id: this.base64urlToBuffer(cred.id)
        }));
      }

      const credential = await navigator.credentials.get({
        publicKey: options
      });

      const payload = {
        id: credential.id,
        rawId: this.bufferToBase64url(credential.rawId),
        type: credential.type,
        response: {
          authenticatorData: this.bufferToBase64url(credential.response.authenticatorData),
          clientDataJSON: this.bufferToBase64url(credential.response.clientDataJSON),
          signature: this.bufferToBase64url(credential.response.signature),
          userHandle: credential.response.userHandle ? this.bufferToBase64url(credential.response.userHandle) : null,
        }
      };

      return { ok: true, payload };
    } catch (err) {
      console.error('WebAuthn Auth Error:', err);
      return { ok: false, error: 'La comprobación del dispositivo falló o fue cancelada.' };
    }
  }
};
