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
if (!$currentUser || $currentUser['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}
// --- GÜNCELLEME İŞLEMİ EN BAŞA ALINDI ---
if (isset($_POST['update_trackingNo'], $_POST['update_status'])) {
    $trackingNo = $_POST['update_trackingNo'];
    $newStatus = $_POST['update_status'];
    $newTech = $_POST['update_technician'] ?? '';
    $newDesc = $_POST['update_description'] ?? '';
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $i => $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $trackingNo) {
                $entry['status'] = $newStatus;
                $entry['assignedTo'] = $newTech;
                $entry['description'] = $newDesc;
                $lines[$i] = json_encode($entry, JSON_UNESCAPED_UNICODE);
                break;
            }
        }
        file_put_contents(PROBLEM_LOG_FILE, implode("\n", $lines) . "\n");
        header('Location: admin.php');
        exit;
    }
}
// Arıza silme işlemi (sadece MainAdmin için)
if (isset($_POST['delete_fault'], $_POST['delete_trackingNo']) && $currentUser && $currentUser['role'] === 'MainAdmin') {
    $deleteTrackingNo = $_POST['delete_trackingNo'];
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $newLines = [];
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $deleteTrackingNo) {
                // Silinecek, ekleme!
                continue;
            }
            $newLines[] = $line;
        }
        file_put_contents(PROBLEM_LOG_FILE, implode("\n", $newLines) . "\n");
        $successMsg = 'Arıza başarıyla silindi.';
    }
}

$filter = $_GET['filter'] ?? '';
$updateMsg = '';
$successMsg = $errorMsg = '';

// Bildirim işlemleri için fonksiyon
function addNotification($user, $msg) {
    $file = 'bildirimler/notifications_' . $user . '.json';
    if (!is_dir('bildirimler')) mkdir('bildirimler');
    $list = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $list[] = [ 'msg' => $msg, 'date' => date('Y-m-d H:i') ];
    file_put_contents($file, json_encode($list, JSON_UNESCAPED_UNICODE));
}
// Durum güncelleme
if (isset($_POST['update_status'])) {
    $trackingNo = $_POST['trackingNo'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';
    if ($trackingNo && $newStatus) {
        // updateFaultStatus($trackingNo, $newStatus);
        $successMsg = 'Durum başarıyla güncellendi.';
        // Bildirim: Arıza sahibine
        if (file_exists(PROBLEM_LOG_FILE)) {
            $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $trackingNo) {
                    if (!empty($entry['username'])) {
                        addNotification($entry['username'], 'Arızanızın durumu "' . $newStatus . '" olarak güncellendi.');
                    }
                    break;
                }
            }
        }
    } else {
        $errorMsg = 'Bir hata oluştu. Lütfen tekrar deneyin.';
    }
}
// Teknisyen atama işlemi (sadece altadmin için)
if ($_SESSION['user'] === 'altadmin' && isset($_POST['assign_tech'], $_POST['assign_tracking'], $_POST['assign_personnel'])) {
    $assignTracking = trim($_POST['assign_tracking']);
    $assignPersonnel = trim($_POST['assign_personnel']);
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $i => $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $assignTracking) {
                $entry['assignedTo'] = $assignPersonnel;
                $entry['assigned'] = $assignPersonnel; // eski kodlarla uyum için
                $entry['assignedBy'] = $_SESSION['user']; // Altadmin kaydı
                $lines[$i] = json_encode($entry, JSON_UNESCAPED_UNICODE);
                // Bildirim: Teknisyene
                addNotification($assignPersonnel, 'Yeni bir arıza size atandı (Takip No: ' . $assignTracking . ').');
                break;
            }
        }
        file_put_contents(PROBLEM_LOG_FILE, implode("\n", $lines) . "\n");
    }
}

// Arıza durumu onaylama (sadece teknikpersonel için)
if ($_SESSION['user'] === 'teknikpersonel' && isset($_POST['confirm_status'], $_POST['confirm_tracking'])) {
    $confirmTracking = trim($_POST['confirm_tracking']);
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $i => $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $confirmTracking && isset($entry['assignedTo']) && $entry['assignedTo'] === $_SESSION['user']) {
                $entry['status'] = 'Onaylandı';
                $lines[$i] = json_encode($entry, JSON_UNESCAPED_UNICODE);
                break;
            }
        }
        file_put_contents(PROBLEM_LOG_FILE, implode("\n", $lines) . "\n");
    }
}

// Listele
$problems = [];
if (file_exists(PROBLEM_LOG_FILE)) {
    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry) {
            if ($filter === '' || ($entry['status'] ?? '') === $filter) {
                $problems[] = $entry;
            }
        }
    }
}
$page = 'admin';

// Bildirim gösterimi
$notification = '';
$notifFile = 'bildirimler/notifications_' . $_SESSION['user'] . '.json';
if (file_exists($notifFile)) {
    $notifs = json_decode(file_get_contents($notifFile), true);
    if (!empty($notifs)) {
        $notification = $notifs[0]['msg'];
        // Bildirimi gösterdikten sonra sil
        array_shift($notifs);
        file_put_contents($notifFile, json_encode($notifs, JSON_UNESCAPED_UNICODE));
    }
}

// Teknisyenler listesini oku
$teknisyenler = array_filter($users, function($u) { return $u['role'] === 'TeknikPersonel'; });

// Ortak arıza durumları
$faultStatuses = [
    'Bekliyor' => 'Bekliyor',
    'Onaylandı' => 'Onaylandı',
    'Tamamlandı' => 'Tamamlandı'
];

// İstatistikler için değişkenler
$totalFaults = 0;
$statusCounts = ['Bekliyor'=>0, 'Onaylandı'=>0, 'Tamamlandı'=>0];
$lastMonthCount = 0;
$departmentCounts = [];
$typeCounts = [];
$mostReportedDepartment = '-';
$mostReportedType = '-';
$mostReportedDepartmentCount = 0;
$mostReportedTypeCount = 0;
$now = time();
if (file_exists(PROBLEM_LOG_FILE)) {
    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry) {
            $totalFaults++;
            $status = $entry['status'] ?? '-';
            if (isset($statusCounts[$status])) $statusCounts[$status]++;
            $date = isset($entry['date']) ? strtotime($entry['date']) : false;
            if ($date && ($now - $date) <= 31*24*60*60) $lastMonthCount++;
            $dep = $entry['department'] ?? '-';
            $departmentCounts[$dep] = ($departmentCounts[$dep] ?? 0) + 1;
            $type = $entry['faultType'] ?? '-';
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
        }
    }
    // En çok arıza bildiren birim
    if ($departmentCounts) {
        arsort($departmentCounts);
        $mostReportedDepartment = array_key_first($departmentCounts);
        $mostReportedDepartmentCount = $departmentCounts[$mostReportedDepartment];
    }
    // En çok görülen arıza türü
    if ($typeCounts) {
        arsort($typeCounts);
        $mostReportedType = array_key_first($typeCounts);
        $mostReportedTypeCount = $typeCounts[$mostReportedType];
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Cardbox için CSS ekle -->
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
    /* Tablo güzelleştirme */
    .table-striped > tbody > tr:nth-of-type(odd) {
      background-color: #f8f9fa;
    }
    .table-hover tbody tr:hover {
      background-color: #e3f0fa;
      transition: background 0.2s;
    }
    .badge {
      font-size: 1em;
      padding: 0.5em 0.8em;
      border-radius: 0.7em;
    }
    .btn-primary, .btn-outline-info, .btn-danger, .btn-success {
      transition: box-shadow 0.2s, background 0.2s;
    }
    .btn-primary:hover, .btn-outline-info:hover, .btn-danger:hover, .btn-success:hover {
      box-shadow: 0 2px 8px rgba(0,0,0,0.10);
      filter: brightness(1.08);
    }
    .form-select, .form-control {
      border-radius: 0.7em;
      border: 1.5px solid #b6d4fe;
    }
    .form-select:focus, .form-control:focus {
      border-color: #0d6efd;
      box-shadow: 0 0 0 0.15rem rgba(13,110,253,.15);
    }
    /* Karanlık mod uyumu */
    body.dark-mode .table-striped > tbody > tr:nth-of-type(odd) {
      background-color: #23272b;
    }
    body.dark-mode .table-hover tbody tr:hover {
      background-color: #1a1d20;
    }
    body.dark-mode .form-select, body.dark-mode .form-control {
      background: #23272b;
      color: #fff;
      border-color: #495057;
    }
    body.dark-mode .form-select:focus, body.dark-mode .form-control:focus {
      border-color: #0d6efd;
      box-shadow: 0 0 0 0.15rem rgba(13,110,253,.25);
    }
    /* Navbar'da dark mode butonunu daha görünür ve yuvarlak yap */
    .navbar .btn-icon#darkModeToggle {
      background: #fff2;
      border: none;
      color: #fff;
      font-size: 1.4em;
      margin-left: 8px;
      margin-right: 2px;
      transition: color 0.2s, background 0.2s;
      border-radius: 50%;
      padding: 6px 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      position: relative;
    }
    .navbar .btn-icon#darkModeToggle:hover, .navbar .btn-icon#darkModeToggle:focus {
      background: #fff4;
      color: #ffd700;
    }
    @media (max-width: 600px) {
      #floatingDarkToggle {
        display: block !important;
      }
    }
    #floatingDarkToggle {
      display: none;
      position: fixed;
      bottom: 18px;
      right: 18px;
      z-index: 9999;
      background: #232a3a;
      color: #ffd700;
      border-radius: 50%;
      width: 48px;
      height: 48px;
      font-size: 2em;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 12px rgba(0,0,0,0.18);
      border: 2px solid #ffd700;
      cursor: pointer;
    }
    #floatingDarkToggle:hover { background: #0d6efd; color: #fff; border-color: #fff; }
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
      <button class="btn-icon" id="darkModeToggle" title="Karanlık Modu Aç/Kapat" aria-label="Karanlık Mod"><i class="bi bi-moon"></i></button>
      <a class="btn-icon" id="notifBtn" title="Bildirimler" data-bs-toggle="modal" data-bs-target="#notifModal"><i class="bi bi-bell"></i></a>
      <a class="btn-icon" id="helpBtn" title="Yardım" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="bi bi-question-circle"></i></a>
      <a class="btn btn-outline-light" href="index.php"><i class="bi bi-house"></i> Ana Sayfa</a>
      <a class="btn btn-outline-light" href="messages.php"><i class="bi bi-chat-dots"></i> Mesajlar</a>
      <a class="btn btn-outline-light" href="logout.php"><i class="bi bi-box-arrow-right"></i> Çıkış</a>
    </div>
  </div>
</nav>
<div class="container mb-4">
  <div class="row align-items-center mb-3">
    <div class="col-md-8">
      <h2 class="mb-0">Admin Paneli</h2>
      <div class="text-muted small">Yönetici: <b><?= htmlspecialchars($currentUser['username']) ?></b></div>
    </div>
    <div class="col-md-4 text-md-end mt-2 mt-md-0">
      <ul class="nav nav-tabs justify-content-end" style="border-bottom: 2px solid #e3f0fa;">
        <li class="nav-item"><a class="nav-link<?= $page=='admin'?' active':'' ?>" href="admin.php"><i class="bi bi-list-task"></i> Arıza Listesi</a></li>
        <li class="nav-item"><a class="nav-link<?= $page=='assigned'?' active':'' ?>" href="?page=assigned"><i class="bi bi-person-lines-fill"></i> Atanan İşler</a></li>
      </ul>
    </div>
  </div>
  <!-- Modern Dashboard Kartları kaldırıldı, sadece klasik başlık ve filtre kalacak -->
</div>
<?php if ($notification): ?>
<div class="container mt-2">
  <div class="alert alert-info alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($notification) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
  </div>
</div>
<?php endif; ?>
<div class="container">
    <h1 class="mb-4">Admin Paneli - Arıza Listesi</h1>
    <form method="get" class="mb-3">
        <label class="form-label">Duruma Göre Filtrele:
            <select name="filter" class="form-select d-inline w-auto" onchange="this.form.submit()">
                <option value="" <?= $filter === '' ? 'selected' : '' ?>>Tümü</option>
<?php foreach ($faultStatuses as $key => $label): ?>
    <option value="<?= $key ?>" <?= $filter === $key ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
                </select>
        </label>
    </form>
    <?php if ($updateMsg): ?>
        <div class="alert alert-success"> <?= htmlspecialchars($updateMsg) ?> </div>
    <?php endif; ?>
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
    <?php if (isset($_GET['page']) && $_GET['page'] === 'assigned' && $currentUser['role'] === 'Admin'): ?>
<h3>Atanan İşler</h3>
<table class="table table-bordered table-striped align-middle mb-4">
<thead class="table-primary">
<tr><th>Takip No</th><th>Açıklama</th><th>Durum</th><th>Tarih</th></tr>
</thead>
<tbody>
<?php
if (file_exists(PROBLEM_LOG_FILE)) {
    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry && isset($entry['assignedTo'])) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($entry['trackingNo']) . '</td>';
            echo '<td>' . htmlspecialchars(isset($entry['description']) ? $entry['description'] : '') . '</td>';
            echo '<td>' . htmlspecialchars($entry['status'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($entry['date'] ?? '-') . '</td>';
            echo '</tr>';
        }
    }
}
?>
</tbody>
</table>
<?php endif; ?>
    <h2 class="mt-4">Arıza Teknisyen Atama</h2>
    <form method="post" class="mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Arıza Takip No</label>
                <input type="text" name="assign_tracking" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Teknisyen</label>
                <select name="assign_personnel" class="form-select" required>
                    <?php foreach ($teknisyenler as $t): ?>
                        <option value="<?= htmlspecialchars($t['username']) ?>">
                            <?= htmlspecialchars($t['username']) ?><?php if (!empty($t['profession'])): ?> (<?= htmlspecialchars($t['profession']) ?>)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" name="assign_tech" class="btn btn-success">Ata</button>
            </div>
        </div>
    </form>
    <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle detail-table">
        <thead class="table-primary">
        <tr>
            <th>Takip No</th>
            <th>Tür</th>
            <th>Başlık</th>
            <th>Birim</th>
            <th>Bilgisayar Özellikleri</th>
            <th>İletişim</th>
            <th>Tarih</th>
            <th>Durum</th>
            <th>Teknisyen</th>
            <th>Güncelle</th>
            <?php if ($currentUser && $currentUser['role'] === 'MainAdmin'): ?>
                <th>Sil</th>
            <?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($problems as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['trackingNo']) ?></td>
                <td><?= htmlspecialchars($p['faultType']) ?></td>
                <td><?= htmlspecialchars($p['title'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['department']) ?></td>
                <td><?= htmlspecialchars($p['specs'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['contact'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['date']) ?></td>
                <td><?= htmlspecialchars($p['status']) ?></td>
                <td><?= htmlspecialchars($p['assignedTo'] ?? ($p['assigned'] ?? '')) ?></td>
                <td><?= htmlspecialchars(isset($p['description']) ? $p['description'] : '') ?></td>
                <td>
                    <button type="button" class="btn btn-primary btn-sm"
                        onclick="openUpdatePopup(
                            '<?= htmlspecialchars($p['trackingNo']) ?>',
                            '<?= htmlspecialchars($p['status']) ?>',
                            '<?= htmlspecialchars($p['assignedTo'] ?? ($p['assigned'] ?? '')) ?>',
                            this.dataset.description
                        )"
                        data-description="<?= htmlspecialchars(isset($p['description']) ? $p['description'] : '', ENT_QUOTES) ?>"
                    >Güncelle</button>
                </td>
                <?php if ($currentUser && $currentUser['role'] === 'MainAdmin'): ?>
                <td>
                    <form method="post" onsubmit="return confirm('Bu arızayı silmek istediğinize emin misiniz?');" style="display:inline-block">
                        <input type="hidden" name="delete_trackingNo" value="<?= htmlspecialchars($p['trackingNo']) ?>">
                        <button type="submit" name="delete_fault" class="btn btn-danger btn-sm">Sil</button>
                    </form>
                </td>
                <?php endif; ?>
                <td>
                    <button type="button" class="btn btn-outline-info btn-sm detail-btn" onclick="openDetailCardbox('<?= htmlspecialchars($p['trackingNo']) ?>', this)">
                        <i class="bi bi-search"></i> Detaylı İncele
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Popup ve Overlay -->
    <div id="updateOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:9998;" onclick="closeUpdatePopup(event)"></div>
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
            <div class="mb-3">
                <label class="form-label">Teknisyen</label>
                <select name="update_technician" id="update_technician" class="form-select">
                    <option value="">(Atanmamış)</option>
                    <?php foreach ($teknisyenler as $t): ?>
                        <option value="<?= htmlspecialchars($t['username']) ?>">
                            <?= htmlspecialchars($t['username']) ?><?php if (!empty($t['profession'])): ?> (<?= htmlspecialchars($t['profession']) ?>)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Açıklama</label>
                <textarea name="update_description" id="update_description" class="form-control" rows="2"></textarea>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="closeUpdatePopup()">İptal</button>
                <button type="submit" class="btn btn-success">Kaydet</button>
            </div>
        </form>
    </div>
    <script>
    function openUpdatePopup(trackingNo, status, technician, description) {
        document.getElementById('update_trackingNo').value = trackingNo;
        document.getElementById('update_status').value = status;
        document.getElementById('update_technician').value = technician;
        document.getElementById('update_description').value = description || '';
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
    </div>
</div>
<!-- Detay Modalı ve eski openDetailModal fonksiyonunu tamamen kaldır -->
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
<script>
function showAssignForm(trackingNo) {
    document.getElementById('assignForm-' + trackingNo).classList.remove('d-none');
}
function hideAssignForm(trackingNo) {
    document.getElementById('assignForm-' + trackingNo).classList.add('d-none');
}
// Arıza detay modalı için arıza verisini JS'ye aktar
const problemsData = <?php echo json_encode($problems, JSON_UNESCAPED_UNICODE); ?>;
let openCardbox = null;
function openDetailCardbox(trackingNo, btn) {
    if (openCardbox) openCardbox.remove();
    const p = problemsData.find(x => x.trackingNo === trackingNo);
    if (!p) return;
    let html = `<button class='close-btn' onclick='this.parentElement.remove(); openCardbox=null;'>&times;</button>`;
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
    document.body.appendChild(card);
    const rect = btn.getBoundingClientRect();
    card.style.top = (window.scrollY + rect.bottom + 4) + 'px';
    card.style.left = (window.scrollX + rect.left) + 'px';
    card.style.display = 'block';
    openCardbox = card;
}
document.addEventListener('click', function(e) {
    if (openCardbox && !openCardbox.contains(e.target) && !e.target.classList.contains('detail-btn')) {
        openCardbox.remove();
        openCardbox = null;
    }
});
</script>
<div id="floatingDarkToggle" title="Karanlık Mod" aria-label="Karanlık Mod" style="display:none;"><i class="bi bi-moon"></i></div>
<script>
function setDarkMode(on) {
  if (on) {
    document.body.classList.add('dark-mode');
    document.getElementById('darkModeToggle').innerHTML = '<i class="bi bi-brightness-high"></i>';
    document.getElementById('floatingDarkToggle').innerHTML = '<i class="bi bi-brightness-high"></i>';
    localStorage.setItem('darkMode', '1');
  } else {
    document.body.classList.remove('dark-mode');
    document.getElementById('darkModeToggle').innerHTML = '<i class="bi bi-moon"></i>';
    document.getElementById('floatingDarkToggle').innerHTML = '<i class="bi bi-moon"></i>';
    localStorage.setItem('darkMode', '0');
  }
}
document.addEventListener('DOMContentLoaded', function() {
  // Navbar butonu
  var darkToggle = document.getElementById('darkModeToggle');
  if (darkToggle) {
    darkToggle.onclick = function() {
      setDarkMode(!document.body.classList.contains('dark-mode'));
    };
  }
  // Mobil/floating buton
  var floatToggle = document.getElementById('floatingDarkToggle');
  if (floatToggle) {
    floatToggle.onclick = function() {
      setDarkMode(!document.body.classList.contains('dark-mode'));
    };
  }
  // Başlangıçta localStorage'a göre ayarla
  if (localStorage.getItem('darkMode') === '1') setDarkMode(true);
  else setDarkMode(false);
  // Mobilde floating butonu göster
  if (window.innerWidth < 600) {
    floatToggle.style.display = 'flex';
  }
});
</script>
</body>
</html> 