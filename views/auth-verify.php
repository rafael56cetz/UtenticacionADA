<?php ob_start(); ?>
<div class="auth-container">
  <div class="card">
    <h1 id="verify-title">Verificación</h1>
    <p id="verify-desc">Complete el proceso de verificación.</p>

    <div id="alert-container" class="alert hidden" role="alert" aria-live="assertive"></div>

    <!-- Contenedor dinámico según método -->
    <div id="interactive-container"></div>

    <div style="display: flex; gap: var(--space-3); justify-content: space-between; margin-top: var(--space-5);">
      <button type="button" id="btn-cancel" class="btn btn-secondary">Cambiar método</button>
      <button type="button" id="btn-verify" class="btn btn-primary hidden">Verificar</button>
    </div>
  </div>
</div>

<script type="module">
  import { AuthFlow } from '/assets/js/auth-flow.js';
  import { CaptureFace } from '/assets/js/capture-face.js';
  import { CaptureVoice } from '/assets/js/capture-voice.js';
  import { WebAuthnClient } from '/assets/js/webauthn-client.js';
  import { PatternInput } from '/assets/js/pattern-input.js';

  document.addEventListener('DOMContentLoaded', async () => {
    // Restaurar estado
    AuthFlow.challengeId = sessionStorage.getItem('auth_challengeId');
    AuthFlow.currentMethod = sessionStorage.getItem('auth_method');
    const pkOptionsRaw = sessionStorage.getItem('auth_pkOptions');
    
    if (!AuthFlow.challengeId || !AuthFlow.currentMethod) {
      window.location.href = '/';
      return;
    }

    const titleEl = document.getElementById('verify-title');
    const descEl = document.getElementById('verify-desc');
    const container = document.getElementById('interactive-container');
    const btnVerify = document.getElementById('btn-verify');
    const btnCancel = document.getElementById('btn-cancel');
    const alertBox = document.getElementById('alert-container');

    const showAlert = (msg, type = 'error') => {
      alertBox.textContent = msg;
      alertBox.className = `alert alert-${type}`;
    };
    
    const hideAlert = () => alertBox.classList.add('hidden');

    let currentSample = null;
    let currentPayload = {};

    btnCancel.addEventListener('click', () => {
        // Stop captures
        if (AuthFlow.currentMethod === 'face') CaptureFace.stopCamera();
        if (AuthFlow.currentMethod === 'voice') CaptureVoice.cleanup();
        sessionStorage.clear();
        window.location.href = '/'; // Volver al inicio
    });

    const handleVerify = async () => {
        btnVerify.disabled = true;
        const originalText = btnVerify.textContent;
        btnVerify.innerHTML = '<div class="spinner"></div> Verificando...';
        hideAlert();

        const res = await AuthFlow.verifyAuth(currentPayload, currentSample);
        if (res.ok) {
            showAlert('Autenticación correcta', 'success');
            setTimeout(() => {
                window.location.href = res.data?.redirectTo || '/dashboard';
            }, 1000);
        } else {
            showAlert(res.error?.message || 'Acceso denegado');
            btnVerify.disabled = false;
            btnVerify.textContent = originalText;
        }
    };

    btnVerify.addEventListener('click', handleVerify);

    // Setup por método
    if (AuthFlow.currentMethod === 'face') {
        titleEl.textContent = 'Reconocimiento facial';
        descEl.textContent = 'Encuadre su rostro en la cámara y presione Capturar.';
        
        container.innerHTML = `
            <div class="capture-container">
                <video class="camera-preview" autoplay playsinline muted></video>
                <img class="capture-result hidden" />
                <div class="capture-guide"></div>
            </div>
            <div style="text-align:center;">
                <button type="button" id="btn-action-face" class="btn btn-secondary">Iniciar cámara</button>
            </div>
        `;
        const video = container.querySelector('video');
        const img = container.querySelector('img');
        const btnAction = container.querySelector('#btn-action-face');
        let state = 'init'; // init -> ready -> captured

        btnAction.addEventListener('click', async () => {
            if (state === 'init' || state === 'captured') {
                const res = await CaptureFace.startCamera(video);
                if (res.ok) {
                    state = 'ready';
                    img.classList.add('hidden');
                    btnAction.textContent = 'Capturar';
                    btnVerify.classList.add('hidden');
                    currentSample = null;
                    hideAlert();
                } else {
                    showAlert(res.error);
                }
            } else if (state === 'ready') {
                currentSample = await CaptureFace.capture();
                if (currentSample) {
                    const url = URL.createObjectURL(currentSample);
                    img.src = url;
                    img.classList.remove('hidden');
                    CaptureFace.stopCamera();
                    state = 'captured';
                    btnAction.textContent = 'Repetir';
                    btnVerify.classList.remove('hidden');
                }
            }
        });
    } 
    else if (AuthFlow.currentMethod === 'voice') {
        titleEl.textContent = 'Reconocimiento de voz';
        descEl.textContent = 'Compararemos esta grabación con tu registro de voz.';
        
        container.innerHTML = `
            <div class="voice-visualizer">
                <span class="voice-indicator hidden" id="voice-ind"></span>
                <span id="voice-time">00:00</span>
            </div>
            <div style="text-align:center;">
                <button type="button" id="btn-action-voice" class="btn btn-secondary">Iniciar grabación</button>
            </div>
        `;
        const btnAction = container.querySelector('#btn-action-voice');
        const ind = container.querySelector('#voice-ind');
        const timeDisplay = container.querySelector('#voice-time');
        let state = 'init';
        let startTime, timerId;

        const updateTime = () => {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            timeDisplay.textContent = `00:${elapsed.toString().padStart(2, '0')}`;
        };

        btnAction.addEventListener('click', async () => {
            if (state === 'init' || state === 'recorded') {
                const res = await CaptureVoice.startRecording(() => {
                    ind.classList.toggle('hidden');
                });
                if (res.ok) {
                    state = 'recording';
                    btnAction.textContent = 'Detener grabación';
                    btnVerify.classList.add('hidden');
                    ind.classList.remove('hidden');
                    startTime = Date.now();
                    timerId = setInterval(updateTime, 1000);
                    hideAlert();
                } else {
                    showAlert(res.error);
                }
            } else if (state === 'recording') {
                currentSample = await CaptureVoice.stopRecording();
                clearInterval(timerId);
                ind.classList.add('hidden');
                state = 'recorded';
                btnAction.textContent = 'Repetir';
                btnVerify.classList.remove('hidden');
            }
        });
    }
    else if (AuthFlow.currentMethod === 'pattern') {
        titleEl.textContent = 'Patrón de acceso';
        descEl.textContent = 'Dibuja la secuencia para acceder.';
        
        container.innerHTML = `<div id="pattern-wrap" style="text-align:center;"></div>`;
        btnVerify.classList.remove('hidden'); // Visible always, enable when drawn
        btnVerify.disabled = true;

        new PatternInput(document.getElementById('pattern-wrap'), (seq) => {
            if (seq.length >= 6) {
                currentPayload = { sequence: seq };
                btnVerify.disabled = false;
            } else {
                showAlert('El patrón debe tener al menos 6 puntos');
                btnVerify.disabled = true;
            }
        });
    }
    else if (AuthFlow.currentMethod === 'webauthn') {
        titleEl.textContent = 'Huella digital / Dispositivo';
        descEl.textContent = 'Verifique su identidad utilizando el sensor de su dispositivo.';
        
        container.innerHTML = `
            <div style="text-align:center; margin: var(--space-5) 0;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--color-primary); margin-bottom:var(--space-3)">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/>
                </svg>
                <br>
                <button type="button" id="btn-action-webauthn" class="btn btn-primary">Verificar con el dispositivo</button>
            </div>
        `;
        const btnAction = container.querySelector('#btn-action-webauthn');
        
        btnAction.addEventListener('click', async () => {
            btnAction.disabled = true;
            hideAlert();
            if (!pkOptionsRaw) {
                showAlert('No se recibieron opciones de WebAuthn');
                btnAction.disabled = false;
                return;
            }
            const opts = JSON.parse(pkOptionsRaw);
            const res = await WebAuthnClient.authenticate(opts);
            if (res.ok) {
                currentPayload = res.payload;
                // Auto verify
                handleVerify();
            } else {
                showAlert(res.error);
                btnAction.disabled = false;
            }
        });
    }
  });
</script>
<?php
$content = ob_get_clean();
include 'layout.php';
?>
