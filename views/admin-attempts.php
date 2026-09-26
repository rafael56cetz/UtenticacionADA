<?php ob_start(); ?>
<div class="container"><h1>Intentos de acceso</h1><a class="btn btn-secondary" href="/dashboard">Volver al panel</a>
<div id="alert-container" class="alert hidden"></div><div id="attempt-filters" class="filters"></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Fecha UTC</th><th>Cuenta</th><th>Método</th><th>Resultado</th><th>Referencia</th></tr></thead><tbody id="attempts-tbody"></tbody></table></div></div>
<?php $content=ob_get_clean();include __DIR__.'/layout.php'; ?>
