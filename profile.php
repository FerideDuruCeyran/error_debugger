<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$usersFile = 'users.json';
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
$currentUser = null;
foreach ($users as $u) {
    if ($u['username'] === $_SESSION['user']) {
        $currentUser = $u;
        break;
    }
}
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
        file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE));
        $successMsg = 'Şifre başarıyla değiştirildi.';
    } else {
        $errorMsg = 'Mevcut şifreniz yanlış.';
    }
}
// Ortak arıza durumları
$faultStatuses = [
    'Bekliyor' => 'Bekliyor',
    'Onaylandı' => 'Onaylandı',
    'Tamamlandı' => 'Tamamlandı'
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Profilim</title>
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
    <div class="d-flex ms-auto align-items-center gap-2">
      <button class="btn-icon" id="darkModeToggle" title="Karanlık Mod"><i class="bi bi-moon"></i></button>
      <button class="btn-icon position-relative" id="notifBtn" title="Bildirimler" data-bs-toggle="modal" data-bs-target="#notifModal"><i class="bi bi-bell"></i></button>
      <button class="btn-icon" id="helpBtn" title="Yardım" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="bi bi-question-circle"></i></button>
      <a class="btn btn-outline-light me-2" href="fault_form.php">Arıza Bildir</a>
      <a class="btn btn-outline-light me-2" href="tracking.php">Takip</a>
      <a class="btn btn-outline-light me-2" href="index.php"><i class="bi bi-house"></i> Ana Sayfa</a>
      <a class="btn btn-outline-light ms-2" href="login.php">Yönetici Girişi</a>
    </div>
  </div>
</nav>
<div class="container mb-4">
  <div class="row align-items-center mb-3">
    <div class="col-md-8">
      <h2 class="mb-0">Profilim</h2>
      <div class="text-muted small">Kullanıcı: <b><?= htmlspecialchars($currentUser['username']) ?></b></div>
    </div>
  </div>
</div>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h4 class="mb-3">Profil Bilgilerim</h4>
                    <ul class="list-group mb-3">
                        <li class="list-group-item"><b>Kullanıcı Adı:</b> <?= htmlspecialchars($currentUser['username'] ?? '') ?></li>
                        <li class="list-group-item"><b>Rol:</b> <?= htmlspecialchars($currentUser['role'] ?? '') ?></li>
                        <?php if (!empty($currentUser['contact'])): ?>
                        <li class="list-group-item"><b>İletişim:</b> <?= htmlspecialchars($currentUser['contact']) ?></li>
                        <?php endif; ?>
                    </ul>
                    <h5 class="mb-3">Şifre Değiştir</h5>
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
                    <form method="post">
                        <div class="mb-2">
                            <label class="form-label">Mevcut Şifre</label>
                            <input type="password" name="old_password" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Yeni Şifre</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary">Şifreyi Değiştir</button>
                    </form>
                </div>
            </div>
            <?php if ($currentUser && $currentUser['role'] === 'user'): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">Arıza Geçmişim</h5>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr><th>Takip No</th><th>Açıklama</th><th>Durum</th><th>Atanan</th><th>Tarih</th></tr>
                            </thead>
                            <tbody>
                            <?php
                            $logFile = defined('PROBLEM_LOG_FILE') ? PROBLEM_LOG_FILE : 'problem_log.txt';
                            if (file_exists($logFile)) {
                                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                                foreach ($lines as $line) {
                                    $entry = json_decode($line, true);
                                    if ($entry && isset($entry['username']) && $entry['username'] === $currentUser['username']) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($entry['trackingNo'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($entry['description'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($entry['status'] ?? '-') . '</td>';
                                        echo '<td>' . htmlspecialchars($entry['assigned'] ?? '-') . '</td>';
                                        echo '<td>' . htmlspecialchars($entry['date'] ?? '-') . '</td>';
                                        echo '</tr>';
                                    }
                                }
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($currentUser && $currentUser['role'] === 'teknikpersonel'): ?>
                <?php $tab = $_GET['tab'] ?? 'ozet'; ?>
                <ul class="nav nav-tabs mb-3">
                  <li class="nav-item"><a class="nav-link<?= $tab=='ozet'?' active':'' ?>" href="profile.php?tab=ozet">Performans Özeti</a></li>
                  <li class="nav-item"><a class="nav-link<?= $tab=='arizalar'?' active':'' ?>" href="profile.php?tab=arizalar">Atanan Arızalar</a></li>
                </ul>
                <?php if ($tab=='ozet'): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">Performans Özeti</h5>
                        <?php
                        $logFile = defined('PROBLEM_LOG_FILE') ? PROBLEM_LOG_FILE : 'problem_log.txt';
                        $assigned = $completed = 0;
                        if (file_exists($logFile)) {
                            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                            foreach ($lines as $line) {
                                $entry = json_decode($line, true);
                                if ($entry && isset($entry['assigned']) && $entry['assigned'] === $currentUser['username']) {
                                    $assigned++;
                                    if (($entry['status'] ?? '') === $faultStatuses['Tamamlandı']) $completed++;
                                }
                            }
                        }
                        ?>
                        <div class="mb-2">
                            <b>Toplam Atanan Arıza:</b> <?= $assigned ?> <br>
                            <b>Tamamlanan Arıza:</b> <?= $completed ?>
                        </div>
                    </div>
                </div>
                <?php elseif ($tab=='arizalar'): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">Atanan Arızalarım</h5>
                        <?php
                        $logFile = defined('PROBLEM_LOG_FILE') ? PROBLEM_LOG_FILE : 'problem_log.txt';
                        $rows = '';
                        if (file_exists($logFile)) {
                            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                            foreach ($lines as $line) {
                                $entry = json_decode($line, true);
                                if ($entry && isset($entry['assigned']) && $entry['assigned'] === $currentUser['username']) {
                                    $rows .= '<tr>';
                                    $rows .= '<td>' . htmlspecialchars($entry['trackingNo'] ?? '') . '</td>';
                                    $rows .= '<td>' . htmlspecialchars($entry['description'] ?? '') . '</td>';
                                    $rows .= '<td>' . htmlspecialchars($entry['status'] ?? '-') . '</td>';
                                    $rows .= '<td>' . htmlspecialchars($entry['date'] ?? '-') . '</td>';
                                    $rows .= '<td><a href="tracking.php?trackingNo=' . urlencode($entry['trackingNo']) . '" class="btn btn-sm btn-outline-primary">Mesajlaş</a></td>';
                                    $rows .= '</tr>';
                                }
                            }
                        }
                        ?>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr><th>Takip No</th><th>Açıklama</th><th>Durum</th><th>Tarih</th><th>Mesajlaş</th></tr>
                            </thead>
                            <tbody><?= $rows ?></tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($currentUser && in_array($currentUser['role'], ['admin','mainadmin'])): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">Atadığım Arızalar & İstatistik</h5>
                        <?php
                        $logFile = defined('PROBLEM_LOG_FILE') ? PROBLEM_LOG_FILE : 'problem_log.txt';
                        $assigned = $completed = 0;
                        $rows = '';
                        if (file_exists($logFile)) {
                            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                            foreach ($lines as $line) {
                                $entry = json_decode($line, true);
                                if ($entry && isset($entry['assignedBy']) && $entry['assignedBy'] === $currentUser['username']) {
                                    $assigned++;
                                    if (($entry['status'] ?? '') === $faultStatuses['Tamamlandı']) $completed++;
                                    $rows .= '<tr>';
                                    $rows .= '<td>' . htmlspecialchars($entry['trackingNo'] ?? '') . '</td>';
                                    $rows .= '<td>' . htmlspecialchars($entry['description'] ?? '') . '</td>';
                                    $rows .= '<td>' . htmlspecialchars($entry['assigned'] ?? '-') . '</td>';
                                    $rows .= '<td>' . htmlspecialchars($entry['status'] ?? '-') . '</td>';
                                    $rows .= '<td>' . htmlspecialchars($entry['date'] ?? '-') . '</td>';
                                    $rows .= '</tr>';
                                }
                            }
                        }
                        ?>
                        <div class="mb-2">
                            <b>Toplam Atanan Arıza:</b> <?= $assigned ?> <br>
                            <b>Tamamlanan Arıza:</b> <?= $completed ?>
                        </div>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr><th>Takip No</th><th>Açıklama</th><th>Atanan</th><th>Durum</th><th>Tarih</th></tr>
                            </thead>
                            <tbody><?= $rows ?></tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
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
          <?php 
          $notifFile = 'bildirimler/notifications_' . ($_SESSION['user'] ?? '') . '.json';
          $notifs = file_exists($notifFile) ? json_decode(file_get_contents($notifFile), true) : [];
          if (!empty($notifs)) {
            foreach ($notifs as $n) {
              echo '<li class="list-group-item">' . htmlspecialchars($n['msg']) . ' <span class="text-muted float-end" style="font-size:0.9em">' . htmlspecialchars($n['date']) . '</span></li>';
            }
          } else {
            echo '<li class="list-group-item text-muted">Hiç bildiriminiz yok.</li>';
          }
          ?>
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
          <li><b>Arıza bildirimi nasıl yapılır?</b><br>"Arıza Bildir" butonunu kullanarak formu doldurabilirsiniz.</li>
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
<script>
function clearNotifs() {
  document.querySelector('#notifModal .list-group').innerHTML = '<li class="list-group-item text-muted">Tüm bildirimler temizlendi.</li>';
}
const feedbackForm = document.getElementById('feedbackForm');
if (feedbackForm) {
  feedbackForm.onsubmit = function(e) {
    e.preventDefault();
    document.getElementById('feedbackMsg').innerHTML = '<span class="text-success">Teşekkürler, geri bildiriminiz alındı.</span>';
    feedbackForm.reset();
  };
}
</script>
</body>
</html> 