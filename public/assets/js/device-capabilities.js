export const DeviceCapabilities = {
  async checkCapabilities() {
    return {
      face: await this.hasCamera(),
      voice: await this.hasMicrophone(),
      webauthn: await this.hasWebAuthn(),
      pattern: true // Pattern is always available via UI
    };
  },

  async hasCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
      return false;
    }
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      return devices.some(device => device.kind === 'videoinput');
    } catch (e) {
      return false; // Perm denied or error
    }
  },

  async hasMicrophone() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
      return false;
    }
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      return devices.some(device => device.kind === 'audioinput');
    } catch (e) {
      return false;
    }
  },

  async hasWebAuthn() {
    if (window.PublicKeyCredential) {
      try {
        // Checking if a platform authenticator is available.
        return await window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
      } catch (e) {
        return false;
      }
    }
    return false;
  }
};
