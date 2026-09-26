<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="app-base" content="<?= htmlspecialchars($config->base(), ENT_QUOTES) ?>">
<title><?= htmlspecialchars($title ?? 'Acceso') ?> · Autenticación multimodal</title>
<?php foreach(['tokens','app','admin','integration'] as $sheet): ?>
<link rel="stylesheet" href="<?= $config->url('/assets/css/'.$sheet.'.css') ?>">
<?php endforeach; ?></head><body>
<a href="#main-content" class="skip-link">Saltar al contenido</a>
<header class="header"><a class="header-brand" href="<?= $config->url('/') ?>">Acceso <small>Proyecto académico</small></a>
<?php if($user ?? null): ?><nav class="header-user" aria-label="Cuenta"><span class="badge"><?= htmlspecialchars($user['role']) ?></span><a class="btn btn-secondary" href="<?= $config->url('/dashboard') ?>">Mi panel</a><button type="button" id="logout" class="btn btn-secondary">Cerrar sesión</button></nav><?php endif; ?></header>
<main id="main-content" data-page="<?= htmlspecialchars($view ?? 'error') ?>" data-user-id="<?= (int)($userId ?? 0) ?>" data-method="<?= htmlspecialchars($enrollMethod ?? '') ?>">
<?= str_replace('href="/', 'href="'.htmlspecialchars($config->base(), ENT_QUOTES).'/', $content ?? '') ?>
</main><script type="module" src="<?= $config->url('/assets/js/app.js') ?>"></script></body></html>