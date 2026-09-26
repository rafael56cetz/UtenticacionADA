<?php
$errorMsg = isset($message) ? $message : 'Ha ocurrido un error inesperado.';
$errorCode = isset($code) ? $code : 500;
ob_start(); 
?>
<div class="auth-container" style="text-align: center;">
  <div class="card">
    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--color-error); margin-bottom:var(--space-4)">
      <circle cx="12" cy="12" r="10"></circle>
      <line x1="12" y1="8" x2="12" y2="12"></line>
      <line x1="12" y1="16" x2="12.01" y2="16"></line>
    </svg>
    <h1 style="color: var(--color-error);"><?= htmlspecialchars($errorCode) ?> Error</h1>
    <p style="margin-bottom: var(--space-5);"><?= htmlspecialchars($errorMsg) ?></p>
    
    <a href="/" class="btn btn-primary">Volver al inicio</a>
  </div>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
?>
