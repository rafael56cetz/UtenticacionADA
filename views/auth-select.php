<?php ob_start(); ?>
<div class="auth-container">
  <div class="card">
    <h1>Iniciar sesión</h1>
    <p>Seleccione un método de autenticación para continuar.</p>

    <div id="alert-container" class="alert alert-error hidden" role="alert" aria-live="assertive"></div>

    <div class="method-grid" id="method-grid" role="radiogroup" aria-label="Métodos de autenticación">
      <button class="method-card" data-method="face" type="button" role="radio" aria-checked="false" disabled>
        <svg class="method-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11h.01M15 11h.01M12 16c1.5 0 2.5-1 2.5-1H9.5s1 1 2.5 1z"/><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/></svg>
        <div class="method-title">Reconocimiento facial</div>
        <div class="method-desc">Utiliza la cámara de tu dispositivo</div>
        <div class="method-status status-checking" id="status-face">Comprobando disponibilidad...</div>
      </button>

      <button class="method-card" data-method="voice" type="button" role="radio" aria-checked="false" disabled>
        <svg class="method-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/></svg>
        <div class="method-title">Reconocimiento de voz</div>
        <div class="method-desc">Verifica tu identidad hablando</div>
        <div class="method-status status-checking" id="status-voice">Comprobando disponibilidad...</div>
      </button>

      <button class="method-card" data-method="webauthn" type="button" role="radio" aria-checked="false" disabled>
        <svg class="method-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
        <div class="method-title">Huella digital</div>
        <div class="method-desc">Mediante el dispositivo; puede ofrecer otro método de desbloqueo</div>
        <div class="method-status status-checking" id="status-webauthn">Comprobando disponibilidad...</div>
      </button>

      <button class="method-card" data-method="pattern" type="button" role="radio" aria-checked="false">
        <svg class="method-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="5" cy="5" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="19" cy="5" r="2"/><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/></svg>
        <div class="method-title">Patrón de acceso</div>
        <div class="method-desc">Dibuja tu secuencia secreta</div>
        <div class="method-status status-ready" id="status-pattern">Disponible</div>
      </button>
    </div>

    <form id="auth-form">
      <div class="form-group">
        <label for="identifier" class="form-label">Identificador de usuario</label>
        <input type="text" id="identifier" class="form-input" required autocomplete="username">
        <div id="identifier-error" class="form-error"></div>
      </div>
      <div style="display: flex; gap: var(--space-3); justify-content: flex-end;">
        <button type="submit" id="btn-next" class="btn btn-primary" disabled>Siguiente</button>
      </div>
    </form>
  </div>
</div>

<script type="module">
  import { DeviceCapabilities } from '/assets/js/device-capabilities.js';
  import { AuthFlow } from '/assets/js/auth-flow.js';

  document.addEventListener('DOMContentLoaded', async () => {
    const caps = await DeviceCapabilities.checkCapabilities();
    
    const updateStatus = (method, isAvailable, errorMsg = 'No disponible') => {
      const card = document.querySelector(`.method-card[data-method="${method}"]`);
      const status = document.getElementById(`status-${method}`);
      if (isAvailable) {
        card.disabled = false;
        status.textContent = 'Disponible';
        status.className = 'method-status status-ready';
      } else {
        card.disabled = true;
        status.textContent = errorMsg;
        status.className = 'method-status status-error';
      }
    };

    updateStatus('face', caps.face, 'Cámara no detectada');
    updateStatus('voice', caps.voice, 'Micrófono no detectado');
    updateStatus('webauthn', caps.webauthn, 'Autenticador de plataforma no compatible');
    
    // Pattern is always available, already set in HTML
    
    const cards = document.querySelectorAll('.method-card');
    const btnNext = document.getElementById('btn-next');
    AuthFlow.initSelection(cards, btnNext);

    document.getElementById('auth-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const identifier = document.getElementById('identifier').value;
      if (!AuthFlow.currentMethod) return;
      
      btnNext.disabled = true;
      btnNext.innerHTML = '<div class="spinner"></div> Procesando...';
      const alertBox = document.getElementById('alert-container');
      alertBox.classList.add('hidden');

      const res = await AuthFlow.beginAuth(identifier, AuthFlow.currentMethod);
      if (res.ok) {
        // En un entorno real, redirigir a verify guardando los datos necesarios, o cargar la vista verify aquí.
        // Asumiendo navegación SPA o guardar en sessionStorage para la siguiente vista.
        sessionStorage.setItem('auth_challengeId', res.data.challengeId);
        sessionStorage.setItem('auth_method', AuthFlow.currentMethod);
        if (res.data.publicKeyOptions) {
            sessionStorage.setItem('auth_pkOptions', JSON.stringify(res.data.publicKeyOptions));
        }
        window.location.href = '/verify';
      } else {
        alertBox.textContent = res.error?.message || 'Error al iniciar autenticación';
        alertBox.classList.remove('hidden');
        btnNext.disabled = false;
        btnNext.textContent = 'Siguiente';
      }
    });
  });
</script>
<?php
$content = ob_get_clean();
include 'layout.php';
?>
