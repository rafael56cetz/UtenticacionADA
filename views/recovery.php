<?php ob_start(); ?>
<div class="auth-container"><div class="card"><h1>Recuperación de acceso</h1>
<p>Usa la contraseña provisionada por el administrador. Este acceso también está sujeto al límite de intentos.</p>
<div id="alert-container" class="alert hidden" role="status"></div>
<form id="recovery-form" class="stack"><div><label class="form-label" for="identifier">Identificador</label><input class="form-input" id="identifier" autocomplete="username" required maxlength="100"></div>
<div><label class="form-label" for="password">Contraseña de recuperación</label><input class="form-input" type="password" id="password" autocomplete="current-password" required maxlength="128"></div>
<button class="btn btn-primary" type="submit">Entrar</button><a class="btn btn-secondary" href="/">Volver a los métodos</a></form></div></div>
<?php $content=ob_get_clean(); include __DIR__.'/layout.php'; ?>
