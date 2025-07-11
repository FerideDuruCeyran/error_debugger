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
// Ortak arıza durumları
$faultStatuses = [
    'Bekliyor' => 'Bekliyor',
    'Onaylandı' => 'Onaylandı',
    'Tamamlandı' => 'Tamamlandı'
];
// --- GÜNCELLEME İŞLEMİ ---
$successMsg = $errorMsg = '';
if (isset($_POST['update_trackingNo'], $_POST['update_status'])) {
    $trackingNo = $_POST['update_trackingNo'];
    $newStatus = $_POST['update_status'];
    $newDesc = $_POST['update_description'] ?? '';
    $newContact = $_POST['update_contact'] ?? '';
    $updated = false;
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $i => $line) {
            $entry = json_decode($line, true);
            $assignedUser = $entry['assignedTo'] ?? ($entry['assigned'] ?? null);
            if (
                $entry &&
                isset($entry['trackingNo']) && $entry['trackingNo'] === $trackingNo &&
                $assignedUser &&
                mb_strtolower(trim($assignedUser)) === mb_strtolower(trim($currentUser['username']))
            ) {
                $entry['status'] = $newStatus;
                if ($newDesc !== '') $entry['description'] = $newDesc;
                if ($newContact !== '') $entry['contact'] = $newContact;
                $lines[$i] = json_encode($entry, JSON_UNESCAPED_UNICODE);
                $updated = true;
                break;
            }
        }
        file_put_contents(PROBLEM_LOG_FILE, implode("\n", $lines) . "\n");
        if ($updated) {
            $successMsg = 'Durum başarıyla güncellendi.';
        } else {
            $errorMsg = 'Güncelleme başarısız. Kayıt bulunamadı veya yetkiniz yok.';
        }
    } else {
        $errorMsg = 'Kayıt dosyası bulunamadı.';
    }
}
// --- Atanan arızaları $problems dizisine aktar ---
$problems = [];
$logFile = PROBLEM_LOG_FILE;
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        $assignedUser = $entry['assignedTo'] ?? ($entry['assigned'] ?? null);
        if ($entry && $assignedUser && mb_strtolower(trim($assignedUser)) === mb_strtolower(trim($currentUser['username']))) {
            $problems[] = $entry;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Teknik Personel Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
.detail-cardbox {
  position: absolute;
  z-index: 9999;
  min-width: 320px;
  max-width: 400px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18);
  padding: 1rem 1.2rem;
  border: 1px solid #e3e3e3;
  top: 40px;
  left: 0;
  display: none;
}
.detail-cardbox .close-btn {
  position: absolute;
  top: 8px;
  right: 12px;
  background: none;
  border: none;
  font-size: 1.2rem;
  color: #888;
  cursor: pointer;
}
.table.detail-table { position: relative; }
</style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="https://upload.wikimedia.org/wikipedia/tr/d/dc/Akdeniz_%C3%9Cniversitesi_logosu.IMG_0838.png" class="akdeniz-logo" alt="Akdeniz Üniversitesi">
      <span>Akdeniz Üniversitesi</span>
    </a>
    <div class="d-flex ms-auto align-items-center gap-2">
      <span class="badge bg-light text-primary me-2">
        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($currentUser['username'] ?? 'Misafir') ?>
        <span class="badge bg-secondary ms-1"><?= htmlspecialchars($currentUser['role'] ?? '') ?></span>
      </span>
      <button class="btn-icon" id="darkModeToggle" title="Karanlık Mod"><i class="bi bi-moon"></i></button>
      <button class="btn-icon position-relative" id="notifBtn" title="Bildirimler" data-bs-toggle="modal" data-bs-target="#notifModal"><i class="bi bi-bell"></i><span id="notifDot" class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle d-none"></span></button>
      <button class="btn-icon" id="helpBtn" title="Yardım" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="bi bi-question-circle"></i></button>
      <a class="btn btn-outline-light" href="index.php"><i class="bi bi-house"></i> Ana Sayfa</a>
      <a class="btn btn-outline-light" href="messages.php"><i class="bi bi-chat-dots"></i> Mesajlar</a>
      <a class="btn btn-outline-light" href="logout.php"><i class="bi bi-box-arrow-right"></i> Çıkış</a>
    </div>
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
    <div class="alert alert-info">
        <b>Oturumdaki kullanıcı:</b> <?= htmlspecialchars($currentUser['username']) ?><br>
    </div>
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3 gap-2">
          <h4 class="mb-0"><i class="bi bi-list-task text-primary"></i> Atanan İşlerim</h4>
          <input type="text" id="tableSearch" class="form-control w-auto" style="max-width:260px;" placeholder="Tabloda ara...">
        </div>
        <?php if ($successMsg): ?>
<div class="toast-container position-fixed top-0 end-0 p-3">
  <div class="toast align-items-center text-bg-success border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">
        <?= $successMsg ?>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="toast-container position-fixed top-0 end-0 p-3">
  <div class="toast align-items-center text-bg-danger border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">
        <?= $errorMsg ?>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<?php endif; ?>
        <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover align-middle detail-table shadow-sm" id="assignedTable">
            <thead class="table-primary">
            <tr>
              <th class="text-center"><i class="bi bi-hash"></i> Takip No</th>
              <th><i class="bi bi-card-heading"></i> Açıklama</th>
              <th class="text-center"><i class="bi bi-flag"></i> Durum</th>
              <th class="text-center"><i class="bi bi-calendar"></i> Tarih</th>
              <th class="text-center"><i class="bi bi-telephone"></i> İletişim</th>
              <th class="text-center"><i class="bi bi-pencil-square"></i> İşlemler</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($problems as $entry): ?>
<tr>
    <td class="text-center fw-bold text-primary"><?= htmlspecialchars($entry['trackingNo'] ?? '') ?></td>
    <td>
      <?php if (!empty($entry['description'])): ?>
        <span style="cursor:pointer; color:#0d6efd;" data-bs-toggle="tooltip" data-bs-title="<?= htmlspecialchars($entry['description']) ?>">
          <i class="bi bi-info-circle"></i>
        </span>
      <?php endif; ?>
    </td>
    <?php $status = $entry['status'] ?? '-';
    $badge = 'secondary'; $statusIcon = 'bi-question-circle';
    if ($status === 'Bekliyor') { $badge = 'warning'; $statusIcon = 'bi-clock'; }
    elseif ($status === 'Onaylandı') { $badge = 'info'; $statusIcon = 'bi-check-circle'; }
    elseif ($status === 'Tamamlandı') { $badge = 'success'; $statusIcon = 'bi-check2-all'; } ?>
    <td class="text-center">
      <span class="badge bg-<?= $badge ?>">
        <i class="bi <?= $statusIcon ?>"></i> <?= htmlspecialchars($status) ?>
      </span>
    </td>
    <td class="text-center"><small><?= htmlspecialchars($entry['date'] ?? '-') ?></small></td>
    <td class="text-center">
      <?php if (!empty($entry['contact'])): ?>
        <span style="cursor:pointer; color:#0d6efd;" data-bs-toggle="tooltip" data-bs-title="<?= htmlspecialchars($entry['contact']) ?>">
          <i class="bi bi-telephone"></i>
        </span>
      <?php endif; ?>
    </td>
    <td class="text-center">
      <div class="btn-group" role="group">
        <button type="button" class="btn btn-primary btn-sm"
          onclick="openUpdatePopup('<?= htmlspecialchars($entry['trackingNo']) ?>','<?= htmlspecialchars($entry['status']) ?>')"
          title="Güncelle">
          <i class="bi bi-pencil"></i>
        </button>
        <button type="button" class="btn btn-outline-info btn-sm detail-btn"
          onclick="openDetailCardbox('<?= htmlspecialchars($entry['trackingNo']) ?>', this, event)"
          title="Detaylı İncele">
          <i class="bi bi-search"></i>
        </button>
      </div>
    </td>
</tr>
            <?php endforeach; ?>
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
<!-- Bildirim Merkezi Modal -->
<div class="modal fade" id="notifModal" tabindex="-1" aria-labelledby="notifModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-end">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="notifModalLabel"><i class="bi bi-bell"></i> Bildirim Merkezi</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <ul class="list-group">
          <li class="list-group-item"><i class="bi bi-info-circle text-primary"></i> Yeni bir arıza size atandı.</li>
          <li class="list-group-item"><i class="bi bi-check-circle text-success"></i> Bir arıza tamamlandı.</li>
          <li class="list-group-item"><i class="bi bi-chat-dots text-info"></i> Yeni mesajınız var.</li>
        </ul>
        <div class="text-end mt-2"><button class="btn btn-sm btn-outline-secondary" onclick="clearNotifs()">Tümünü Temizle</button></div>
      </div>
    </div>
  </div>
</div>
<!-- Yardım/SSS Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="helpModalLabel"><i class="bi bi-question-circle"></i> Yardım & SSS</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <h6>Sıkça Sorulan Sorular</h6>
        <ul>
          <li><b>Takip numaramı kaybettim, ne yapmalıyım?</b><br>İletişim bilgilerinizle birlikte destek ekibine başvurun.</li>
          <li><b>Arıza durumunu nasıl takip ederim?</b><br>"Takip" sekmesinden takip numaranızla sorgulayabilirsiniz.</li>
          <li><b>Şifremi unuttum, nasıl sıfırlarım?</b><br>Giriş ekranındaki "Şifremi unuttum" bağlantısını kullanın.</li>
        </ul>
        <hr>
        <h6>Geri Bildirim</h6>
        <form id="feedbackForm">
          <div class="mb-2">
            <label class="form-label">Görüşünüz</label>
            <textarea class="form-control" name="feedback" rows="2" required></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Gönder</button>
        </form>
        <div id="feedbackMsg" class="mt-2"></div>
      </div>
    </div>
  </div>
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
<?php foreach ($faultStatuses as $key => $label): ?>
    <option value="<?= $key ?>"><?= $label ?></option>
<?php endforeach; ?>
                </select>
        </div>
        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" onclick="closeUpdatePopup()">İptal</button>
            <button type="submit" class="btn btn-success">Kaydet</button>
        </div>
    </form>
    <script>
    document.getElementById('updateForm').addEventListener('submit', function() {
      alert('Form submit edildi!');
    });
    </script>
</div>
<script>
function openUpdatePopup(trackingNo, status) {
    document.getElementById('update_trackingNo').value = trackingNo;
    document.getElementById('update_status').value = status;
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
<script>
// PHP'den JS'ye arıza verilerini dizi (array) olarak aktar
const problemsData = <?php echo json_encode($problems, JSON_UNESCAPED_UNICODE); ?>;
let openCardbox = null;
function openDetailCardbox(trackingNo, btn, event) {
    if (event) event.stopPropagation();
    if (openCardbox) openCardbox.remove();
    const p = problemsData.find(x => x.trackingNo === trackingNo);
    if (!p) return;
    let html = `<button class='close-btn' onclick='this.parentElement.remove(); openCardbox=null;'>×</button>`;
    html += `<div class='mb-2'><b>Takip No:</b> ${p.trackingNo}</div>`;
    html += `<div class='mb-2'><b>Detaylı Tanım:</b><br><span>${p.detailedDescription ?? '-'}</span></div>`;
    if (p.filePath && p.filePath !== '') {
        const fileName = p.filePath.split('/').pop();
        const ext = fileName.split('.').pop().toLowerCase();
        if (["jpg","jpeg","png","gif","bmp","webp"].includes(ext)) {
            html += `<div class='mb-2'><img src='uploads/${fileName}' alt='Ekli Görsel' style='max-width:100%;max-height:180px;border-radius:8px;'></div>`;
        } else {
            html += `<div class='mb-2'><a href='uploads/${fileName}' target='_blank' class='btn btn-outline-primary btn-sm'><i class='bi bi-file-earmark-arrow-down'></i> Dosyayı Görüntüle/İndir</a></div>`;
        }
    } else {
        html += `<div class='mb-2 text-muted'>Dosya eklenmemiş.</div>`;
    }
    html += `<div class='mb-2'><b>Birim:</b> ${p.department ?? '-'}</div>`;
    html += `<div class='mb-2'><b>Arıza Türü:</b> ${p.faultType ?? '-'}</div>`;
    html += `<div class='mb-2'><b>Durum:</b> ${p.status ?? '-'}</div>`;
    html += `<div class='mb-2'><b>Tarih:</b> ${p.date ?? '-'}</div>`;
    const card = document.createElement('div');
    card.className = 'detail-cardbox';
    card.innerHTML = html;
    card.onclick = function(e) { e.stopPropagation(); };
    document.body.appendChild(card);
    const rect = btn.getBoundingClientRect();
    card.style.top = (window.scrollY + rect.bottom + 4) + 'px';
    card.style.left = (window.scrollX + rect.left) + 'px';
    card.style.display = 'block';
    openCardbox = card;
}
setTimeout(() => {
  document.addEventListener('click', function(e) {
      if (openCardbox && !openCardbox.contains(e.target) && !e.target.classList.contains('detail-btn')) {
          openCardbox.remove();
          openCardbox = null;
      }
  });
}, 0);
</script>
<script>
// Karanlık mod toggle
const darkToggle = document.getElementById('darkModeToggle');
function setDarkMode(on) {
  if (on) {
    document.body.classList.add('dark-mode');
    darkToggle.innerHTML = '<i class="bi bi-brightness-high"></i>';
    localStorage.setItem('darkMode', '1');
  } else {
    document.body.classList.remove('dark-mode');
    darkToggle.innerHTML = '<i class="bi bi-moon"></i>';
    localStorage.setItem('darkMode', '0');
  }
}
darkToggle.onclick = () => setDarkMode(!document.body.classList.contains('dark-mode'));
if (localStorage.getItem('darkMode') === '1') setDarkMode(true);
// Bildirim ve yardım butonları (modal açma placeholder)
</script>
<!-- Detay Modalı -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="detailModalLabel"><i class="bi bi-search"></i> Arıza Detayı</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs mb-3" id="detailTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="detay-tab" data-bs-toggle="tab" data-bs-target="#detailTabDetayPane" type="button" role="tab">Detay</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="dosya-tab" data-bs-toggle="tab" data-bs-target="#detailTabDosyaPane" type="button" role="tab">Dosya</button>
          </li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="detailTabDetayPane" role="tabpanel">
            <div id="detailTabDetay"></div>
          </div>
          <div class="tab-pane fade" id="detailTabDosyaPane" role="tabpanel">
            <div id="detailTabDosya"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html> 