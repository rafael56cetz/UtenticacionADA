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


<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
