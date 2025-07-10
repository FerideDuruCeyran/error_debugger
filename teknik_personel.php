<?php
session_start();
require_once 'config.php';
$usersFile = 'users.json';
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
$currentUser = null;
foreach ($users as $u) {
    if ($u['username'] === ($_SESSION['user'] ?? '')) {
        $currentUser = $u;
        break;
    }
}
if (!$currentUser || $currentUser['role'] !== 'TeknikPersonel') {
    header('Location: login.php');
    exit;
}
$tab = $_GET['tab'] ?? 'assigned';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Teknik Personel Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="https://upload.wikimedia.org/wikipedia/tr/d/dc/Akdeniz_%C3%9Cniversitesi_logosu.IMG_0838.png" class="akdeniz-logo" alt="Akdeniz Üniversitesi">
      <span>Akdeniz Üniversitesi</span>
    </a>
    <a class="btn btn-outline-light ms-2" href="logout.php"><i class="bi bi-box-arrow-right"></i> Çıkış</a>
    <a class="btn btn-outline-light" href="messages.php"><i class="bi bi-chat-dots"></i> Mesajlar</a>
  </div>
</nav>
<div class="container mb-4">
  <div class="row align-items-center mb-3">
    <div class="col-md-8">
      <h2 class="mb-0">Teknik Personel Paneli</h2>
      <div class="text-muted small">Hoşgeldiniz, <b><?= htmlspecialchars($currentUser['username']) ?></b><?php if (!empty($currentUser['profession'])): ?> (<?= htmlspecialchars($currentUser['profession']) ?>)<?php endif; ?></div>
    </div>
    <div class="col-md-4 text-md-end mt-2 mt-md-0">
      <ul class="nav nav-tabs justify-content-end" style="border-bottom: 2px solid #e3f0fa;">
        <li class="nav-item"><a class="nav-link<?= $tab=='assigned'?' active':'' ?>" href="teknik_personel.php?tab=assigned"><i class="bi bi-list-task"></i> Atanan İşler</a></li>
        <li class="nav-item"><a class="nav-link<?= $tab=='profile'?' active':'' ?>" href="teknik_personel.php?tab=profile"><i class="bi bi-person"></i> Profil</a></li>
      </ul>
    </div>
  </div>
</div>
<div class="container">
<?php if ($tab=='assigned'): ?>
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h4 class="mb-3"><i class="bi bi-list-task"></i> Atanan İşlerim</h4>
        <div class="mb-3">
          <input type="text" id="tableSearch" class="form-control" placeholder="Tabloda ara...">
        </div>
        <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle mb-0" id="assignedTable">
            <thead class="table-primary">
            <tr><th>Takip No</th><th>Açıklama</th><th>Durum</th><th>Tarih</th><th>İletişim</th><th>Mesajlaş</th><th>Güncelle</th></tr>
            </thead>
            <tbody>
            <?php
            $logFile = PROBLEM_LOG_FILE;
            if (file_exists($logFile)) {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $entry = json_decode($line, true);
                    if ($entry && isset($entry['assignedTo']) && trim($entry['assignedTo']) === trim($currentUser['username'])) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($entry['trackingNo'] ?? '') . '</td>';
                        echo '<td>';
                        if (!empty($entry['description'])) {
                            echo '<span style="cursor:pointer; color:#0d6efd;" data-bs-toggle="tooltip" data-bs-title="' . htmlspecialchars($entry['description']) . '"><i class="bi bi-info-circle"></i></span>';
                        }
                        echo '</td>';
                        // Durum badge
                        $status = $entry['status'] ?? '-';
                        $badge = 'secondary';
                        if ($status === 'Bekliyor') $badge = 'warning';
                        elseif ($status === 'Onaylandı') $badge = 'info';
                        elseif ($status === 'Tamamlandı') $badge = 'success';
                        echo '<td><span class="badge bg-' . $badge . '">' . htmlspecialchars($status) . '</span></td>';
                        echo '<td>' . htmlspecialchars($entry['date'] ?? '-') . '</td>';
                        // İletişim tooltip
                        echo '<td>';
                        if (!empty($entry['contact'])) {
                            echo '<span style="cursor:pointer; color:#0d6efd;" data-bs-toggle="tooltip" data-bs-title="' . htmlspecialchars($entry['contact']) . '"><i class="bi bi-telephone"></i></span>';
                        }
                        echo '</td>';
                        echo '<td><a href="tracking.php?trackingNo=' . urlencode($entry['trackingNo']) . '" class="btn btn-sm btn-outline-primary"><i class="bi bi-chat-dots"></i> Mesajlaş</a></td>';
                        echo '<td><button type="button" class="btn btn-primary btn-sm" onclick="openUpdatePopup(' .
                            '\'' . htmlspecialchars($entry['trackingNo']) . '\',\'' . htmlspecialchars($entry['status']) . '\',\'' . htmlspecialchars($entry['description'] ?? '') . '\',\'' . htmlspecialchars($entry['contact'] ?? '') . '\')"><i class="bi bi-pencil-square"></i> Güncelle</button></td>';
                        echo '</tr>';
                    }
                }
            }
            ?>
            </tbody>
        </table>
        </div>
      </div>
    </div>
    <script>
    // Tablo arama
    document.addEventListener('DOMContentLoaded', function() {
      var search = document.getElementById('tableSearch');
      if (search) {
        search.addEventListener('keyup', function() {
          var val = this.value.toLowerCase();
          var rows = document.querySelectorAll('#assignedTable tbody tr');
          rows.forEach(function(row) {
            row.style.display = row.textContent.toLowerCase().indexOf(val) > -1 ? '' : 'none';
          });
        });
      }
      // Bootstrap tooltip
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
      });
    });
    </script>
<?php elseif ($tab=='profile'): ?>
    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-6">
        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <h4 class="mb-3"><i class="bi bi-person"></i> Profilim</h4>
            <ul class="list-group mb-3">
                <li class="list-group-item"><b>Kullanıcı Adı:</b> <?= htmlspecialchars($currentUser['username']) ?></li>
                <li class="list-group-item"><b>Rol:</b> <?= htmlspecialchars($currentUser['role']) ?></li>
                <?php if (!empty($currentUser['profession'])): ?>
                <li class="list-group-item"><b>Meslek:</b> <?= htmlspecialchars($currentUser['profession']) ?></li>
                <?php endif; ?>
            </ul>
            <h5 class="mb-3">Şifre Değiştir</h5>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Mevcut Şifre</label>
                    <input type="password" name="old_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Yeni Şifre</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary"><i class="bi bi-key"></i> Değiştir</button>
            </form>
            <?php
            $successMsg = $errorMsg = '';
            if (isset($_POST['change_password'])) {
                $old = $_POST['old_password'] ?? '';
                $new = $_POST['new_password'] ?? '';
                if ($currentUser && isset($currentUser['password']) && $old === $currentUser['password']) {
                    foreach ($users as &$u) {
                        if ($u['username'] === $currentUser['username']) {
                            $u['password'] = $new;
                            break;
                        }
                    }
                    file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    $successMsg = 'Şifre başarıyla değiştirildi.';
                } else {
                    $errorMsg = 'Mevcut şifreniz yanlış.';
                }
            }
            if ($successMsg): ?>
                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                    <?= $successMsg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
            <?php elseif ($errorMsg): ?>
                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                    <?= $errorMsg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
<?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<!-- Açıklama popup -->
<div id="descPopup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); min-width:280px; background:#fff; border-radius:12px; box-shadow:0 8px 40px rgba(0,0,0,0.18); z-index:9999; padding:1.5rem 1.5rem 1rem 1.5rem;">
  <div id="descPopupContent"></div>
  <div class="d-flex justify-content-end mt-2">
    <button class="btn btn-secondary btn-sm" onclick="closeDescPopup()">Kapat</button>
  </div>
</div>
<div id="descOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.2); z-index:9998;" onclick="closeDescPopup()"></div>
<script>
function showDescPopup(desc) {
  document.getElementById('descPopupContent').innerText = desc;
  document.getElementById('descPopup').style.display = 'block';
  document.getElementById('descOverlay').style.display = 'block';
}
function closeDescPopup() {
  document.getElementById('descPopup').style.display = 'none';
  document.getElementById('descOverlay').style.display = 'none';
}
</script>
<!-- Güncelleme popup -->
<div id="updateOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9998;" onclick="closeUpdatePopup(event)"></div>
<div id="updatePopup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); min-width:340px; background:#fff; border-radius:16px; box-shadow:0 8px 40px rgba(0,0,0,0.18); z-index:9999; padding:2rem 2rem 1.5rem 2rem;">
    <form method="post" id="updateForm">
        <input type="hidden" name="update_trackingNo" id="update_trackingNo">
        <div class="mb-3">
            <label class="form-label">Durum</label>
            <select name="update_status" id="update_status" class="form-select" required>
                <option value="Bekliyor">Bekliyor</option>
                <option value="Onaylandı">Onaylandı</option>
                <option value="Tamamlandı">Tamamlandı</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Açıklama</label>
            <textarea name="update_description" id="update_description" class="form-control" rows="2" readonly></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">İletişim</label>
            <input type="text" name="update_contact" id="update_contact" class="form-control" readonly>
        </div>
        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" onclick="closeUpdatePopup()">İptal</button>
            <button type="submit" class="btn btn-success">Kaydet</button>
        </div>
    </form>
</div>
<script>
function openUpdatePopup(trackingNo, status, description, contact) {
    document.getElementById('update_trackingNo').value = trackingNo;
    document.getElementById('update_status').value = status;
    document.getElementById('update_description').value = description || '';
    document.getElementById('update_contact').value = contact || '';
    document.getElementById('updateOverlay').style.display = 'block';
    document.getElementById('updatePopup').style.display = 'block';
}
function closeUpdatePopup(e) {
    if (!e || e.target === document.getElementById('updateOverlay')) {
        document.getElementById('updateOverlay').style.display = 'none';
        document.getElementById('updatePopup').style.display = 'none';
    }
}
</script>
</body>
</html> 