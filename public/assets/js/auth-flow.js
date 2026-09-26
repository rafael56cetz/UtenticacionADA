import { ApiClient } from './api-client.js';

export const AuthFlow = {
  currentMethod: null,
  challengeId: null,
  
  initSelection(cards, nextBtn) {
    cards.forEach(card => {
      card.addEventListener('click', () => {
        if (card.disabled) return;
        cards.forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        this.currentMethod = card.dataset.method;
        nextBtn.disabled = false;
      });
      // Keyboard support
      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          card.click();
        }
      });
    });
  },

  async beginAuth(identifier, method) {
    this.currentMethod = method;
    const res = await ApiClient.request('/api/auth/begin', {
      method: 'POST',
      body: { identifier, method }
    });
    
    if (res.ok) {
      this.challengeId = res.data.challengeId;
    }
    return res;
  },

  async verifyAuth(payload = {}, sampleBlob = null) {
    if (!this.challengeId || !this.currentMethod) {
      return { ok: false, error: { message: 'Falta reto de autenticación' } };
    }

    const formData = new FormData();
    formData.append('challengeId', this.challengeId);
    formData.append('method', this.currentMethod);
    formData.append('payload', JSON.stringify(payload));
    
    if (sampleBlob) {
      // Name of the file depends on the method, but keeping generic as requested
      const ext = this.currentMethod === 'face' ? 'jpg' : (sampleBlob.type.includes('webm') ? 'webm' : 'wav');
      formData.append('sample', sampleBlob, `sample.${ext}`);
    }

    const res = await ApiClient.request('/api/auth/verify', {
      method: 'POST',
      body: formData // ApiClient will NOT set Content-Type, allowing browser to set it with boundary
    });
    return res;
  }
};
