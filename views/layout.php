<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title ?? 'Acceso') ?> · Autenticación multimodal</title>
  <link rel="stylesheet" href="/assets/css/tokens.css">
  <link rel="stylesheet" href="/assets/css/app.css">
  <meta name="theme-color" content="#F5F7FA">
</head>
<body>
  <header class="header">
    <div class="header-brand">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
        <circle cx="12" cy="12" r="3"></circle>
        <path d="M12 9v6M9 12h6"></path>
      </svg>
      Acceso <span style="font-weight: 400; color: var(--color-text-sec); font-size: var(--font-size-sm); margin-left: 8px;">Proyecto académico</span>
    </div>
    <?php if (isset($user) && $user): ?>
      <div class="header-user">
        <span class="badge <?= $user['role'] === 'Administrator' ? 'badge-admin' : 'badge-gestor' ?>">
          <?= htmlspecialchars($user['role']) ?>
        </span>
        <a href="/api/logout" class="btn btn-secondary" style="margin-left: var(--space-3); min-height: 32px; padding: 4px 12px; font-size: 14px;">Cerrar sesión</a>
      </div>
    <?php endif; ?>
  </header>

  <main>
    <?= $content ?? '' ?>
  </main>

  <!-- Global scripts, handled by modules where needed -->
  <script type="module">
    import { ApiClient } from '/assets/js/api-client.js';
    window.ApiClient = ApiClient; // Export for debugging/console access if needed
  </script>
</body>
</html>
