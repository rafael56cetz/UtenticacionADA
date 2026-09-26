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

<script type="module">
  import { AdminUsers } from '/assets/js/admin-users.js';

  document.addEventListener('DOMContentLoaded', async () => {
    const tbody = document.getElementById('users-tbody');
    const alertBox = document.getElementById('alert-container');

    const showAlert = (msg, type = 'error') => {
      alertBox.textContent = msg;
      alertBox.className = `alert alert-${type}`;
    };

    const loadUsers = async () => {
      const res = await AdminUsers.listUsers(1);
      if (res.ok) {
        if (!res.data || res.data.length === 0) {
          tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No hay usuarios. <a href="/admin/users/new">Dar de alta usuario</a></td></tr>';
          return;
        }
        
        tbody.innerHTML = res.data.map(u => `
          <tr>
            <td>${u.name}</td>
            <td>${u.identifier}</td>
            <td><span class="badge ${u.role === 'Administrator' ? 'badge-admin' : 'badge-gestor'}">${u.role}</span></td>
            <td><span class="badge ${u.active ? 'badge-active' : ''}">${u.active ? 'Activo' : 'Inactivo'}</span></td>
            <td>
              <span style="font-size: 12px; color: var(--color-text-sec);">${(u.modalities || []).join(', ') || 'Ninguna'}</span>
            </td>
            <td>
              <a href="/admin/users/${u.id}" class="btn btn-secondary" style="min-height:32px; padding: 4px 8px; font-size:12px;">Gestionar</a>
            </td>
          </tr>
        `).join('');
      } else {
        showAlert(res.error?.message || 'Error al cargar usuarios');
      }
    };

    loadUsers();
  });
</script>
<?php
$content = ob_get_clean();
include 'layout.php';
?>
