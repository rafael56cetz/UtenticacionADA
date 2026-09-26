<?php
// $userId and $method are passed from the router
ob_start();
?>
<div class="auth-container">
  <div class="card">
    <h1 id="enroll-title">Registro de modalidad</h1>
    <p id="enroll-desc">Flujo de registro para el titular.</p>
    
    <div id="alert-container" class="alert hidden"></div>

    <div id="consent-container" style="margin-top: var(--space-5);">
      <p style="font-weight: 500;">Consentimiento informado</p>
      <p class="form-desc" style="font-size: var(--font-size-sm); margin-bottom: var(--space-4);">
        Al continuar, acepta que se recaben datos biométricos o credenciales locales asociados a su cuenta para fines de autenticación.
      </p>
      <button id="btn-accept-consent" class="btn btn-primary">Aceptar y continuar</button>
      <a href="/admin/users/<?= htmlspecialchars($userId) ?>" class="btn btn-secondary" style="margin-left: var(--space-2);">Cancelar</a>
    </div>

    <div id="capture-container" class="hidden" style="margin-top: var(--space-5);">
      <!-- Contenido dinámico de captura -->
    </div>

    <div id="progress-container" class="hidden" style="margin-top: var(--space-4); text-align: center; font-weight: 500;">
      <!-- Indicador de 3 capturas -->
    </div>

    <div id="action-container" class="hidden" style="margin-top: var(--space-5); display: flex; justify-content: space-between;">
      <a href="/admin/users/<?= htmlspecialchars($userId) ?>" class="btn btn-secondary">Cancelar</a>
      <button id="btn-finish" class="btn btn-primary" disabled>Finalizar registro</button>
    </div>
  </div>
</div>

<script type="module">
  import { AdminUsers } from '/assets/js/admin-users.js';
  import { CaptureFace } from '/assets/js/capture-face.js';
  import { CaptureVoice } from '/assets/js/capture-voice.js';
  import { PatternInput } from '/assets/js/pattern-input.js';
  import { WebAuthnClient } from '/assets/js/webauthn-client.js';

  document.addEventListener('DOMContentLoaded', () => {
    const userId = <?= json_encode($userId) ?>;
    const method = <?= json_encode($method) ?>;
    
    const consentDiv = document.getElementById('consent-container');
    const captureDiv = document.getElementById('capture-container');
    const actionDiv = document.getElementById('action-container');
    const progressDiv = document.getElementById('progress-container');
    const btnAccept = document.getElementById('btn-accept-consent');
    const btnFinish = document.getElementById('btn-finish');
    const alertBox = document.getElementById('alert-container');
    
    const showAlert = (msg, type = 'error') => {
      alertBox.textContent = msg;
      alertBox.className = `alert alert-${type}`;
    };

    let enrollData = null; // Result from begin()
    let samples = [];
    let currentPayload = {};
    let stepCount = method === 'face' || method === 'voice' ? 3 : 1;
    let patternTemp = null; // Para confirmar patrón

    const updateProgress = () => {
      if (stepCount > 1) {
        progressDiv.classList.remove('hidden');
        progressDiv.textContent = `Muestras capturadas: ${samples.length} / ${stepCount}`;
        if (samples.length >= stepCount) {
          btnFinish.disabled = false;
          captureDiv.innerHTML = '<div class="alert alert-success">Captura completada. Presione Finalizar.</div>';
        }
      }
    };

    btnAccept.addEventListener('click', async () => {
      btnAccept.disabled = true;
      btnAccept.innerHTML = '<div class="spinner"></div> Preparando...';
      const res = await AdminUsers.beginEnroll(userId, method);
      if (res.ok) {
        enrollData = res.data;
        consentDiv.classList.add('hidden');
        captureDiv.classList.remove('hidden');
        actionDiv.classList.remove('hidden');
        setupCapture();
      } else {
        showAlert(res.error?.message || 'Error al iniciar registro');
        btnAccept.disabled = false;
        btnAccept.textContent = 'Aceptar y continuar';
      }
    });

    const setupCapture = () => {
      if (method === 'face') {
        document.getElementById('enroll-title').textContent = 'Registro de Rostro';
        document.getElementById('enroll-desc').textContent = 'Se requieren 3 capturas con buena iluminación.';
        captureDiv.innerHTML = `
            <div class="capture-container">
                <video class="camera-preview" autoplay playsinline muted></video>
                <div class="capture-guide"></div>
            </div>
            <div style="text-align:center;">
                <button type="button" id="btn-action" class="btn btn-secondary">Tomar foto</button>
            </div>
        `;
        const video = captureDiv.querySelector('video');
        const btnAction = captureDiv.querySelector('#btn-action');
        
        CaptureFace.startCamera(video).then(r => {
          if(!r.ok) showAlert(r.error);
        });

        btnAction.addEventListener('click', async () => {
          const s = await CaptureFace.capture();
          if (s) {
            samples.push(s);
            updateProgress();
            if (samples.length >= stepCount) CaptureFace.stopCamera();
          }
        });
        updateProgress();
      } 
      else if (method === 'voice') {
        document.getElementById('enroll-title').textContent = 'Registro de Voz';
        document.getElementById('enroll-desc').textContent = 'Grabe 3 muestras de voz diferentes.';
        captureDiv.innerHTML = `
            <div class="voice-visualizer">
                <span class="voice-indicator hidden" id="voice-ind"></span>
                <span>Grabación ${samples.length + 1}</span>
            </div>
            <div style="text-align:center;">
                <button type="button" id="btn-action" class="btn btn-secondary">Iniciar grabación</button>
            </div>
        `;
        const btnAction = captureDiv.querySelector('#btn-action');
        const ind = captureDiv.querySelector('#voice-ind');
        let recording = false;

        btnAction.addEventListener('click', async () => {
          if (!recording) {
            const r = await CaptureVoice.startRecording(() => ind.classList.toggle('hidden'));
            if (r.ok) {
              recording = true;
              btnAction.textContent = 'Detener';
              ind.classList.remove('hidden');
            } else {
              showAlert(r.error);
            }
          } else {
            const s = await CaptureVoice.stopRecording();
            recording = false;
            ind.classList.add('hidden');
            if (s) {
              samples.push(s);
              updateProgress();
              if (samples.length < stepCount) {
                btnAction.textContent = 'Siguiente grabación';
                captureDiv.querySelector('span:not(.voice-indicator)').textContent = `Grabación ${samples.length + 1}`;
              }
            }
          }
        });
        updateProgress();
      }
      else if (method === 'pattern') {
        document.getElementById('enroll-title').textContent = 'Registro de Patrón';
        document.getElementById('enroll-desc').textContent = 'Dibuje su patrón de acceso.';
        captureDiv.innerHTML = `<div id="pattern-wrap" style="text-align:center;"></div><p id="pattern-msg" style="text-align:center;"></p>`;
        const msg = captureDiv.querySelector('#pattern-msg');

        const initPattern = () => {
          new PatternInput(document.getElementById('pattern-wrap'), (seq) => {
            if (seq.length < 6) {
              msg.textContent = 'El patrón debe tener al menos 6 puntos.';
              msg.style.color = 'var(--color-error)';
              setTimeout(initPattern, 1000);
            } else if (!patternTemp) {
              patternTemp = seq;
              msg.textContent = 'Dibuje el patrón nuevamente para confirmar.';
              msg.style.color = 'var(--color-text-main)';
              setTimeout(initPattern, 1000);
            } else {
              if (JSON.stringify(patternTemp) === JSON.stringify(seq)) {
                currentPayload = { sequence: seq };
                msg.textContent = 'Patrón confirmado.';
                msg.style.color = 'var(--color-success)';
                btnFinish.disabled = false;
              } else {
                msg.textContent = 'Los patrones no coinciden. Intente de nuevo.';
                msg.style.color = 'var(--color-error)';
                patternTemp = null;
                setTimeout(initPattern, 1000);
              }
            }
          });
        };
        initPattern();
      }
      else if (method === 'webauthn') {
        document.getElementById('enroll-title').textContent = 'Registro de Huella / Dispositivo';
        document.getElementById('enroll-desc').textContent = 'Toque el botón para registrar el autenticador.';
        captureDiv.innerHTML = `
          <div style="text-align:center;">
             <button id="btn-action" class="btn btn-primary">Registrar en dispositivo</button>
          </div>
        `;
        captureDiv.querySelector('#btn-action').addEventListener('click', async () => {
          if (!enrollData?.publicKeyOptions) {
             showAlert('No se recibieron opciones de servidor'); return;
          }
          // The API structure for creation is similar to get but uses navigator.credentials.create
          // For this integration contract, assume WebAuthnClient is extended or handle here.
          // Since it's a frontend simulation, I will implement the native create call here:
          try {
            const opts = JSON.parse(JSON.stringify(enrollData.publicKeyOptions));
            opts.challenge = WebAuthnClient.base64urlToBuffer(opts.challenge);
            opts.user.id = WebAuthnClient.base64urlToBuffer(opts.user.id);
            
            const cred = await navigator.credentials.create({ publicKey: opts });
            currentPayload = {
              id: cred.id,
              rawId: WebAuthnClient.bufferToBase64url(cred.rawId),
              type: cred.type,
              response: {
                attestationObject: WebAuthnClient.bufferToBase64url(cred.response.attestationObject),
                clientDataJSON: WebAuthnClient.bufferToBase64url(cred.response.clientDataJSON)
              }
            };
            btnFinish.disabled = false;
            captureDiv.innerHTML = '<div class="alert alert-success">Autenticador registrado localmente. Presione Finalizar.</div>';
          } catch(err) {
            showAlert('El registro fue cancelado o falló.');
          }
        });
      }
    };

    btnFinish.addEventListener('click', async () => {
      btnFinish.disabled = true;
      btnFinish.innerHTML = '<div class="spinner"></div> Guardando...';
      const res = await AdminUsers.finishEnroll(userId, method, currentPayload, samples);
      if (res.ok) {
        window.location.href = `/admin/users/${userId}`;
      } else {
        showAlert(res.error?.message || 'Error al guardar credencial');
        btnFinish.disabled = false;
        btnFinish.textContent = 'Finalizar registro';
      }
    });
  });
</script>
<?php
$content = ob_get_clean();
include 'layout.php';
?>
