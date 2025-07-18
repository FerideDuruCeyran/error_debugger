<?php
// Fetch roles and departments from SQL
$roles = $conn->query("SELECT id, name FROM Roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$departments = $conn->query("SELECT id, name FROM Departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Add user
$userMsg = '';
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);
    $role_id = $_POST['role_id'] ?? '';
    $department_id = $_POST['department_id'] ?? null;
    if ($department_id === '') $department_id = null;
    if ($username && $password && $role_id) {
        // Check if username exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $conn->prepare("INSERT INTO Users (username, password, email, role_id, department_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $password, $email, $role_id, $department_id]);
            $userMsg = 'Kullanıcı başarıyla eklendi!';
        } else {
            $userMsg = 'Bu kullanıcı adı zaten var!';
        }
    } else {
        $userMsg = 'Kullanıcı adı, şifre ve rol zorunludur!';
    }
}
// Edit user
if (isset($_POST['edit_user_id'])) {
    $edit_id = intval($_POST['edit_user_id']);
    $edit_username = trim($_POST['edit_username']);
    $edit_email = trim($_POST['edit_email']);
    $edit_role_id = $_POST['edit_role_id'] ?? '';
    $edit_department_id = $_POST['edit_department_id'] ?? null;
    if ($edit_department_id === '') $edit_department_id = null;
    if ($edit_id && $edit_username && $edit_role_id) {
        // Check if username is unique (except for this user)
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Users WHERE username = ? AND id != ?");
        $stmt->execute([$edit_username, $edit_id]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $conn->prepare("UPDATE Users SET username = ?, email = ?, role_id = ?, department_id = ? WHERE id = ?");
            $stmt->execute([$edit_username, $edit_email, $edit_role_id, $edit_department_id, $edit_id]);
            $userMsg = 'Kullanıcı başarıyla güncellendi!';
        } else {
            $userMsg = 'Bu kullanıcı adı zaten var!';
        }
    } else {
        $userMsg = 'Kullanıcı adı ve rol zorunludur!';
    }
}
// List users
$sql = "SELECT u.id, u.username, u.email, r.name AS role, d.name AS department, u.last_login FROM Users u LEFT JOIN Roles r ON u.role_id = r.id LEFT JOIN Departments d ON u.department_id = d.id ORDER BY u.id DESC";
$users = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container">
    <h1 class="mb-4 fw-bold">Kullanıcılar ve Yetkiler</h1>
    <?php if ($userMsg): ?>
      <div class="alert alert-info text-center"> <?= htmlspecialchars($userMsg) ?> </div>
    <?php endif; ?>
    <form method="post" class="row g-3 mb-4 align-items-end">
        <div class="col-md-2">
            <input type="text" name="username" class="form-control" placeholder="Kullanıcı Adı" required>
        </div>
        <div class="col-md-2">
            <input type="password" name="password" class="form-control" placeholder="Şifre" required>
        </div>
        <div class="col-md-2">
            <input type="email" name="email" class="form-control" placeholder="E-posta">
        </div>
        <div class="col-md-2">
            <select name="role_id" class="form-select" required>
                <option value="">Rol Seç</option>
                <?php foreach ($roles as $role): ?>
                  <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="department_id" class="form-select">
                <option value="">Birim/Tür (isteğe bağlı)</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" name="add_user" class="btn btn-success">Ekle</button>
        </div>
    </form>
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-primary">
        <tr>
            <th>ID</th>
            <th>Kullanıcı Adı</th>
            <th>E-posta</th>
            <th>Rol</th>
            <th>Birim/Tür</th>
            <th>Son Giriş</th>
            <th>Düzenle</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['id']) ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['email'] !== '' ? $u['email'] : '-') ?></td>
                <td><?= htmlspecialchars($u['role']) ?></td>
                <td><?= htmlspecialchars($u['department'] ?? '') ?></td>
                <td><?= htmlspecialchars(($u['last_login'] ?? '') !== '' ? $u['last_login'] : '-') ?></td>
                <td><button class="btn btn-sm btn-primary edit-user-btn" data-user='<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'><i class="bi bi-pencil-square"></i> Düzenle</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<!-- User Edit Modal -->
<div id="userEditModal" class="modal" tabindex="-1" style="display:none; background:rgba(0,0,0,0.3); position:fixed; top:0; left:0; width:100vw; height:100vh; z-index:99999; align-items:center; justify-content:center;">
  <div class="modal-dialog" style="max-width:400px; width:90vw; background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.18); position:relative;">
    <div class="modal-content p-4" style="border-radius:12px;">
      <button type="button" id="closeUserEditModal" aria-label="Kapat" style="position:absolute; right:1rem; top:1rem; font-size:2rem; background:none; border:none; cursor:pointer; z-index:2;">
        <span id="closeUserEditModalIcon">&times;</span>
      </button>
      <h5 class="mb-3 fw-bold">Kullanıcıyı Düzenle</h5>
      <form id="userEditForm">
        <input type="hidden" name="edit_user_id" id="edit_user_id">
        <div class="mb-2">
          <label class="form-label">Kullanıcı Adı</label>
          <input type="text" name="edit_username" id="edit_username" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">E-posta</label>
          <input type="email" name="edit_email" id="edit_email" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Rol</label>
          <select name="edit_role_id" id="edit_role_id" class="form-select" required>
            <option value="">Rol Seç</option>
            <?php foreach ($roles as $role): ?>
              <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Birim/Tür</label>
          <select name="edit_department_id" id="edit_department_id" class="form-select">
            <option value="">Birim/Tür (isteğe bağlı)</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="d-grid mt-3">
          <button type="submit" id="userEditApplyBtn" class="btn btn-success" disabled>Uygula</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
// Modal open/close logic
const userEditModal = document.getElementById('userEditModal');
const closeUserEditModal = document.getElementById('closeUserEditModal');
document.querySelectorAll('.edit-user-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const user = JSON.parse(this.getAttribute('data-user'));
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role_id').value = user.role_id || '';
    document.getElementById('edit_department_id').value = user.department_id || '';
    userEditModal.style.display = 'flex';
    document.getElementById('userEditApplyBtn').disabled = true;
  });
});
closeUserEditModal.onmousedown = function() { userEditModal.style.display = 'none'; };
// Enable apply button on change
['edit_username','edit_email','edit_role_id','edit_department_id'].forEach(id => {
  document.getElementById(id).addEventListener('input', function() {
    document.getElementById('userEditApplyBtn').disabled = false;
  });
});
// Form submit
const userEditForm = document.getElementById('userEditForm');
userEditForm.onsubmit = function(e) {
  e.preventDefault();
  const formData = new FormData(userEditForm);
  fetch('main_admin.php?tab=kullanicilar', {
    method: 'POST',
    body: formData
  }).then(resp => resp.text()).then(html => {
    location.reload();
  });
};
// Modal click outside to close
userEditModal.addEventListener('mousedown', function(e) {
  if (e.target === userEditModal) userEditModal.style.display = 'none';
});
</script> 