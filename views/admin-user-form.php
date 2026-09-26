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

<script type="module">
  import { AdminUsers } from '/assets/js/admin-users.js';

  document.addEventListener('DOMContentLoaded', async () => {
    const isNew = <?= $isNew ? 'true' : 'false' ?>;
    const userId = <?= $isNew ? 'null' : json_encode($userId) ?>;
    const form = document.getElementById('user-form');
    const alertBox = document.getElementById('alert-container');
    const btnSave = document.getElementById('btn-save');
    
    const showAlert = (msg, type = 'error') => {
      alertBox.textContent = msg;
      alertBox.className = `alert alert-${type}`;
    };

    if (!isNew) {
      // Load existing user data
      const res = await AdminUsers.getUser(userId);
      if (res.ok) {
        document.getElementById('identifier').value = res.data.identifier;
        document.getElementById('identifier').disabled = true; // No se edita id
        document.getElementById('name').value = res.data.name;
        document.getElementById('role').value = res.data.role;
        document.getElementById('active').checked = res.data.active;
        
        renderModalities(res.data.modalities || {});
      } else {
        showAlert(res.error?.message || 'Error al cargar usuario');
      }
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      btnSave.disabled = true;
      alertBox.classList.add('hidden');
      
      const data = {
        identifier: document.getElementById('identifier').value,
        name: document.getElementById('name').value,
        role: document.getElementById('role').value,
        active: document.getElementById('active').checked
      };

      let res;
      if (isNew) {
        res = await AdminUsers.createUser(data);
      } else {
        res = await AdminUsers.updateUser(userId, data);
        // Si cambia el estado activo/inactivo enviarlo también en su endpoint
        await AdminUsers.toggleStatus(userId, data.active);
      }

      btnSave.disabled = false;
      if (res.ok) {
        showAlert('Datos guardados correctamente', 'success');
        if (isNew) {
          setTimeout(() => window.location.href = `/admin/users/${res.data.id}`, 1000);
        }
      } else {
        showAlert(res.error?.message || 'Error al guardar');
      }
    });

    function renderModalities(mods) {
      const tbody = document.getElementById('modalities-tbody');
      const methods = [
        { id: 'face', name: 'Reconocimiento facial' },
        { id: 'voice', name: 'Reconocimiento de voz' },
        { id: 'webauthn', name: 'Huella digital' },
        { id: 'pattern', name: 'Patrón de acceso' }
      ];

      tbody.innerHTML = methods.map(m => {
        const isRegistered = mods[m.id];
        return `
          <tr>
            <td>${m.name}</td>
            <td>${isRegistered ? '<span class="badge badge-active">Registrado</span>' : '<span class="badge">Pendiente</span>'}</td>
            <td>
              ${isRegistered 
                ? `<button type="button" class="btn btn-secondary btn-revoke" data-method="${m.id}">Revocar</button>`
                : `<a href="/admin/users/${userId}/enroll?method=${m.id}" class="btn btn-primary" style="padding: 4px 12px; font-size: 14px; min-height:32px;">Registrar</a>`
              }
            </td>
          </tr>
        `;
      }).join('');

      document.querySelectorAll('.btn-revoke').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          if (confirm('¿Seguro que desea revocar esta credencial?')) {
            const method = e.target.dataset.method;
            const r = await AdminUsers.revokeCredential(userId, method);
            if (r.ok) location.reload();
            else showAlert(r.error?.message || 'Error al revocar');
          }
        });
      });
    }
  });
</script>
<?php
$content = ob_get_clean();
include 'layout.php';
?>
