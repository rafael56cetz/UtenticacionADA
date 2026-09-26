<?php ob_start(); ?>
<div class="container">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
    <h1>Administración de usuarios</h1>
    <a href="/admin/users/new" class="btn btn-primary">Dar de alta usuario</a>
  </div>

  <div id="alert-container" class="alert hidden"></div>

  <div class="table-responsive">
    <table class="table" id="users-table">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Identificador</th>
          <th>Rol</th>
          <th>Estado</th>
          <th>Modalidades</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="users-tbody">
        <tr><td colspan="6" style="text-align: center;">Cargando usuarios...</td></tr>
      </tbody>
    </table>
  </div>
</div>


<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
