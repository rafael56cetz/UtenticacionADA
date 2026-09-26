export const CaptureVoice = {
  stream: null,
  mediaRecorder: null,
  audioChunks: [],
  intervalId: null,

  async startRecording(onActivity = null) {
    try {
      this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      
      const mimeType = this.getSupportedMimeType();
      this.mediaRecorder = new MediaRecorder(this.stream, { mimeType });
      this.audioChunks = [];

      this.mediaRecorder.ondataavailable = e => {
        if (e.data.size > 0) this.audioChunks.push(e.data);
      };

      this.mediaRecorder.start();

      // Simple visualizer fake pulse if context not supported, or real activity
      if (onActivity) {
        this.intervalId = setInterval(onActivity, 200);
      }

      return { ok: true };
    } catch (err) {
      console.error('Mic error:', err);
      return { ok: false, error: err.name === 'NotAllowedError' ? 'Permiso denegado' : 'Micrófono no disponible' };
    }
  },

  async stopRecording() {
    return new Promise(resolve => {
      if (!this.mediaRecorder || this.mediaRecorder.state === 'inactive') {
        resolve(null);
        return;
      }

      this.mediaRecorder.onstop = () => {
        const mimeType = this.mediaRecorder.mimeType;
        const blob = new Blob(this.audioChunks, { type: mimeType });
        this.cleanup();
        resolve(blob);
      };
      
      this.mediaRecorder.stop();
    });
  },

  getSupportedMimeType() {
    const types = ['audio/webm', 'audio/ogg', 'audio/mp4', 'audio/wav'];
    for (const type of types) {
      if (MediaRecorder.isTypeSupported(type)) {
        return type;
      }
    }
    return ''; // fallback to browser default
  },

  cleanup() {
    if (this.stream) {
      this.stream.getTracks().forEach(track => track.stop());
      this.stream = null;
    }
    if (this.intervalId) {
      clearInterval(this.intervalId);
      this.intervalId = null;
    }
  }
};
