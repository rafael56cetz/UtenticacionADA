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


<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
