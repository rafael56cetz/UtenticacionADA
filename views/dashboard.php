<?php
// PHP expects to pass $user array to this view
$userName = isset($user['name']) ? $user['name'] : 'Usuario';
$userRole = isset($user['role']) ? $user['role'] : 'Desconocido';
$methodUsed = isset($user['method_used']) ? $user['method_used'] : null;

ob_start(); 
?>
<div class="container">
  <div class="card" style="margin-top: var(--space-5);">
    <h1>¡Bienvenido, <?= htmlspecialchars($userName) ?>!</h1>
    <p>Rol: <?= htmlspecialchars($userRole) ?></p>
    <?php if ($methodUsed): ?>
      <p style="font-size: var(--font-size-sm); color: var(--color-text-sec);">Autenticado mediante: <?= htmlspecialchars($methodUsed) ?></p>
    <?php endif; ?>

    <div style="margin-top: var(--space-5);">
      <?php if ($userRole === 'Administrator'): ?>
        <h2>Panel de administración</h2>
        <div style="display: flex; gap: var(--space-3);">
          <a href="/admin/users" class="btn btn-primary">Administrar usuarios</a>
          <a href="/admin/attempts" class="btn btn-secondary">Intentos de acceso</a>
        </div>
      <?php elseif ($userRole === 'Gestor'): ?>
        <h2>Panel personal</h2>
        <p>No hay acciones pendientes.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
?>
