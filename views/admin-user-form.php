<?php
// $userId is passed from the router, or null if new
$isNew = !isset($userId) || !$userId;
ob_start(); 
?>
<div class="container">
  <a href="/admin/users" class="btn btn-secondary" style="margin-bottom: var(--space-4);">&larr; Volver</a>
  <div class="card">
    <h1 id="form-title"><?= $isNew ? 'Alta de usuario' : 'Editar usuario' ?></h1>
    <div id="alert-container" class="alert hidden"></div>

    <form id="user-form">
      <div class="method-grid">
        <div class="form-group">
          <label for="identifier" class="form-label">Identificador</label>
          <input type="text" id="identifier" class="form-input" required>
        </div>
        <div class="form-group">
          <label for="name" class="form-label">Nombre completo</label>
          <input type="text" id="name" class="form-input" required>
        </div>
        <div class="form-group">
          <label for="role" class="form-label">Rol permitido</label>
          <select id="role" class="form-input" required>
            <option value="Gestor">Gestor</option>
            <option value="Administrator">Administrator</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Estado</label>
          <label style="display: flex; align-items: center; gap: 8px;">
            <input type="checkbox" id="active" checked> Activo
          </label>
        </div>
      </div>
      <div style="display: flex; justify-content: flex-end;">
        <button type="submit" class="btn btn-primary" id="btn-save">Guardar datos de cuenta</button>
      </div>
    </form>
  </div>

  <?php if (!$isNew): ?>
  <div class="card" style="margin-top: var(--space-5);">
    <h2>Modalidades registradas</h2>
    <p>Inicie el registro de modalidades para este usuario. La captura requiere el consentimiento y presencia del titular.</p>
    
    <div class="table-responsive" style="margin-top: var(--space-4);">
      <table class="table" id="modalities-table">
        <thead>
          <tr>
            <th>Modalidad</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="modalities-tbody">
          <tr><td colspan="3" style="text-align:center;">Cargando...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>


<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
