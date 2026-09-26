export const CaptureFace = {
  stream: null,
  videoElement: null,

  async startCamera(videoEl) {
    this.videoElement = videoEl;
    try {
      this.stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }
      });
      this.videoElement.srcObject = this.stream;
      await this.videoElement.play();
      return { ok: true };
    } catch (err) {
      console.error('Camera error:', err);
      return { ok: false, error: err.name === 'NotAllowedError' ? 'Permiso denegado' : 'Dispositivo no disponible' };
    }
  },

  async capture() {
    if (!this.videoElement) return null;
    const canvas = document.createElement('canvas');
    canvas.width = this.videoElement.videoWidth;
    canvas.height = this.videoElement.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(this.videoElement, 0, 0, canvas.width, canvas.height);
    
    return new Promise(resolve => {
      canvas.toBlob(blob => resolve(blob), 'image/jpeg', 0.9);
    });
  },

  stopCamera() {
    if (this.stream) {
      this.stream.getTracks().forEach(track => track.stop());
      this.stream = null;
    }
    if (this.videoElement) {
      this.videoElement.srcObject = null;
    }
  }
};
