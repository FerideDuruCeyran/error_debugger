<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user'] !== 'mainadmin') {
    header('Location: login.php');
    exit;
}
require_once 'config.php';
$usersFile = 'users.json';
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, json_encode([
        ['id' => 1, 'username' => 'mainadmin', 'role' => 'MainAdmin'],
        ['id' => 2, 'username' => 'altadmin', 'role' => 'Admin'],
        ['id' => 3, 'username' => 'teknikpersonel', 'role' => 'TeknikPersonel', 'profession' => 'Bilgisayar Teknikeri'],
        ['id' => 4, 'username' => 'teknisyen.ahmet', 'role' => 'TeknikPersonel', 'profession' => 'Ağ Uzmanı'],
        ['id' => 5, 'username' => 'teknisyen.ayse', 'role' => 'TeknikPersonel', 'profession' => 'Elektrik Teknisyeni'],
        ['id' => 6, 'username' => 'teknisyen.mehmet', 'role' => 'TeknikPersonel', 'profession' => 'Yazıcı Bakım Uzmanı'],
        ['id' => 7, 'username' => 'teknisyen.elif', 'role' => 'TeknikPersonel', 'profession' => 'Laboratuvar Destek Personeli'],
        ['id' => 8, 'username' => 'teknisyen.omer', 'role' => 'TeknikPersonel', 'profession' => 'Donanım Destek Uzmanı']
    ], JSON_UNESCAPED_UNICODE));
}
$users = json_decode(file_get_contents($usersFile), true);
$currentUser = null;
foreach ($users as $u) {
    if ($u['username'] === ($_SESSION['user'] ?? '')) {
        $currentUser = $u;
        break;
    }
}
$successMsg = $errorMsg = '';
// Kullanıcı ekleme
if (isset($_POST['add_user'])) {
    $newId = count($users) ? max(array_column($users, 'id')) + 1 : 1;
    $users[] = [
        'id' => $newId,
        'username' => trim($_POST['username']),
        'role' => $_POST['role']
    ];
    file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE));
    $successMsg = 'Kullanıcı başarıyla eklendi.';
}
// Kullanıcı silme
if (isset($_POST['delete_user'])) {
    $users = array_filter($users, function($u) {
        return $u['id'] != $_POST['delete_id'];
    });
    file_put_contents($usersFile, json_encode(array_values($users), JSON_UNESCAPED_UNICODE));
    $successMsg = 'Kullanıcı başarıyla silindi.';
}
// Kullanıcı güncelleme
if (isset($_POST['update_user'])) {
    foreach ($users as &$u) {
        if ($u['id'] == $_POST['update_id']) {
            $u['username'] = trim($_POST['update_username']);
            $u['role'] = $_POST['update_role'];
        }
    }
    unset($u);
    file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE));
    $successMsg = 'Kullanıcı başarıyla güncellendi.';
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

// 1 haftadan eski tamamlanan arızaları otomatik sil
if (file_exists(PROBLEM_LOG_FILE)) {
    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $now = time();
    $newLines = [];
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry && isset($entry['status']) && $entry['status'] === 'Tamamlandı' && isset($entry['date'])) {
            $entryTime = strtotime($entry['date']);
            if ($entryTime !== false && ($now - $entryTime) > 7*24*60*60) {
                // 7 günden eski tamamlanan, sil
                continue;
            }
        }
        $newLines[] = $line;
    }
    file_put_contents(PROBLEM_LOG_FILE, implode("\n", $newLines) . "\n");
}

$date1 = $_GET['date1'] ?? '';
$date2 = $_GET['date2'] ?? '';
$department = $_GET['department'] ?? '';
$status = $_GET['status'] ?? '';

$reports = [];
if (file_exists(PROBLEM_LOG_FILE)) {
    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry) {
          $match = true;
          if ($date1 && strtotime($entry['date']) < strtotime($date1)) $match = false;
          if ($date2 && strtotime($entry['date']) > strtotime($date2)) $match = false;
          if ($department && $entry['department'] !== $department) $match = false;
          if ($status && $entry['status'] !== $status) $match = false;
          if ($match) $reports[] = $entry;
      }
  }
}

// Teknik personel atama işlemi
if (isset($_POST['assign_submit'], $_POST['assign_tracking'], $_POST['assign_personnel'])) {
  $assignTracking = trim($_POST['assign_tracking']);
  $assignPersonnel = trim($_POST['assign_personnel']);
  if (file_exists(PROBLEM_LOG_FILE)) {
      $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      foreach ($lines as $i => $line) {
          $entry = json_decode($line, true);
          if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $assignTracking) {
              $entry['assignedTo'] = $assignPersonnel;
              $entry['assigned'] = $assignPersonnel; // Teknik personel paneliyle tam uyum
              $lines[$i] = json_encode($entry, JSON_UNESCAPED_UNICODE);
              break;
          }
      }
      file_put_contents(PROBLEM_LOG_FILE, implode(PHP_EOL, $lines) . PHP_EOL);
  }
}

// Main admin popup ile arıza güncelleme işlemi (durum, teknisyen, mesaj)
if (
    isset($_POST['update_trackingNo']) &&
    (isset($_POST['update_status']) || isset($_POST['update_technician']) || isset($_POST['update_message']))
) {
    $trackingNo = $_POST['update_trackingNo'];
    $newStatus = $_POST['update_status'] ?? '';
    $newTechnician = $_POST['update_technician'] ?? '';
    $newMessage = $_POST['update_message'] ?? '';
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $i => $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $trackingNo) {
                if ($newStatus !== '') $entry['status'] = $newStatus;
                $entry['assignedTo'] = $newTechnician;
                $entry['message'] = $newMessage;
                $lines[$i] = json_encode($entry, JSON_UNESCAPED_UNICODE);
                break;
            }
        }
        file_put_contents(PROBLEM_LOG_FILE, implode(PHP_EOL, $lines) . PHP_EOL);
        $successMsg = 'Arıza başarıyla güncellendi.';
    }
}

// Altadmin'lere atanmış arıza sayısını say
$altadminJobCount = [];
if (file_exists(PROBLEM_LOG_FILE)) {
  $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
      $entry = json_decode($line, true);
      if ($entry && isset($entry['assignedTo']) && strpos($entry['assignedTo'], 'altadmin') !== false) {
          $altadminJobCount[$entry['assignedTo']] = ($altadminJobCount[$entry['assignedTo']] ?? 0) + 1;
      }
  }
}

// Teknik personel (alt admin) listesi (örnek)
$personnel = array_values(array_filter($users, function($u) {
  return $u['role'] === 'TeknikPersonel';
}));

$tab = $_GET['tab'] ?? 'arizalar';
$altAdmins = array_filter($users, function($u){ return $u['role']==='Admin'; });
$teknikPersonel = array_filter($users, function($u){ return $u['role']==='TeknikPersonel'; });

// Ortak arıza durumları
$faultStatuses = [
    'Bekliyor' => 'Bekliyor',
    'Onaylandı' => 'Onaylandı',
    'Tamamlandı' => 'Tamamlandı'
];

$faultTypes = [
    1 => "MAKİNE/TESİSAT",
    2 => "ELEKTRİK",
    3 => "İNŞAAT"
];
function getFaultTypeName($id, $faultTypes) {
    return $faultTypes[$id] ?? $id;
}
// JS için güvenli string fonksiyonu
function js_safe($str) {
    return addslashes(str_replace(["\r", "\n"], ['', '\\n'], $str));
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Main Admin Paneli - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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
      <button class="btn-icon position-relative" id="notifBtn" title="Bildirimler" data-bs-toggle="modal" data-bs-target="#notifModal"><i class="bi bi-bell"></i></button>
      <button class="btn-icon" id="helpBtn" title="Yardım" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="bi bi-question-circle"></i></button>
      <a class="btn btn-outline-light" href="index.php"><i class="bi bi-house"></i> Ana Sayfa</a>
      <a class="btn btn-outline-light" href="messages.php"><i class="bi bi-chat-dots"></i> Mesajlar</a>
      <a class="btn btn-outline-light" href="logout.php"><i class="bi bi-box-arrow-right"></i> Çıkış</a>
    </div>
  </div>
</nav>
<div class="container mb-4">
  <div class="row align-items-center mb-3">
    <div class="col-12">
      <ul class="nav nav-tabs justify-content-center" style="border-bottom: 2px solid #e3f0fa;">
        <li class="nav-item flex-fill text-center"><a class="nav-link<?= $tab=='arizalar'?' active':'' ?>" href="main_admin.php?tab=arizalar"><i class="bi bi-list-task"></i> Tüm Arızalar</a></li>
        <li class="nav-item flex-fill text-center"><a class="nav-link<?= $tab=='altadmin'?' active':'' ?>" href="main_admin.php?tab=altadmin"><i class="bi bi-person-badge"></i> Alt Adminler</a></li>
        <li class="nav-item flex-fill text-center"><a class="nav-link<?= $tab=='teknik'?' active':'' ?>" href="main_admin.php?tab=teknik"><i class="bi bi-person-gear"></i> Teknik Personeller</a></li>
        <li class="nav-item flex-fill text-center"><a class="nav-link<?= $tab=='kullanicilar'?' active':'' ?>" href="main_admin.php?tab=kullanicilar"><i class="bi bi-people"></i> Kullanıcılar</a></li>
      </ul>
    </div>
  </div>
</div>
<?php if ($tab=='arizalar'): ?>
<div class="container">
    <h1 class="mb-4">Main Admin Paneli</h1>
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
    <h2>Raporlama</h2>
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-2">
            <label class="form-label">Tarih 1</label>
            <input type="date" name="date1" class="form-control" value="<?= htmlspecialchars($date1) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Tarih 2</label>
            <input type="date" name="date2" class="form-control" value="<?= htmlspecialchars($date2) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Birim</label>
            <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($department) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Durum</label>
            <select name="status" class="form-select">
<?php foreach ($faultStatuses as $key => $label): ?>
    <option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 align-self-end">
            <button type="submit" class="btn btn-primary w-100">Filtrele</button>
        </div>
    </form>
    <h2>Tüm Arızalar ve Durumları</h2>
    <table id="reportTable" class="table table-bordered table-striped align-middle detail-table">
    <thead class="table-primary">
    <tr>
        <th>Takip No</th>
        <th>Açıklama</th>
        <th>Bölüm</th>
        <th>İletişim</th>
        <th>Atanan</th>
        <th class="text-center" style="min-width: 200px;">Durum</th>
        <th>Tarih</th>
        <?php if ($currentUser && $currentUser['role'] === 'MainAdmin'): ?>
            <th>IP</th>
        <?php endif; ?>
        <th>Admin Mesajı</th>
        <?php if ($currentUser && $currentUser['role'] === 'MainAdmin'): ?>
            <th>Sil</th>
        <?php endif; ?>
        <th>Detaylı İncele</th>
    </tr>
    </thead>
    <tbody>
<?php
$problems = [];
if (file_exists(PROBLEM_LOG_FILE)) {
    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $latest = [];
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry && isset($entry['trackingNo'])) {
            if (!isset($entry['status']) || trim($entry['status']) === '') {
                $entry['status'] = 'Bekliyor';
            }
            $latest[$entry['trackingNo']] = $entry;
        }
    }
    $problems = array_values($latest);
}
?>
<?php
foreach ($problems as $entry):
    echo '<tr>';
    // 1. Takip No
    echo '<td class="text-center fw-bold text-primary">' . htmlspecialchars($entry['trackingNo']) . '</td>';
    // 2. Açıklama
    $desc = $entry['description'] ?? $entry['detailedDescription'] ?? $entry['content'] ?? '';
    if (!empty($desc)) {
        echo '<td><span class="desc-hover" onclick="showDescPopup(' . json_encode($desc) . ')">' . htmlspecialchars(mb_strimwidth($desc, 0, 60, '...')) . '</span></td>';
    } else {
        echo '<td>-</td>';
    }
    // 3. Bölüm
    echo '<td>' . htmlspecialchars($entry['department'] ?? '-') . '</td>';
    // 4. İletişim
    echo '<td>' . htmlspecialchars($entry['contact'] ?? '-') . '</td>';
    // 5. Atanan
    echo '<td>' . htmlspecialchars($entry['assignedTo'] ?? '-') . '</td>';
    // 6. Durum
    $status = $entry['status'] ?? '-';
    $badge = 'secondary'; $statusIcon = 'bi-question-circle';
    if ($status === 'Bekliyor') { $badge = 'warning'; $statusIcon = 'bi-clock'; }
    elseif ($status === 'Onaylandı') { $badge = 'info'; $statusIcon = 'bi-check-circle'; }
    elseif ($status === 'Tamamlandı') { $badge = 'success'; $statusIcon = 'bi-check2-all'; }
    echo '<td class="text-center" style="min-width:200px; white-space:nowrap;">';
    echo '<span style="cursor:pointer;display:inline-flex;align-items:center;gap:8px;">';
    echo '<span class="badge bg-' . $badge . '"><i class="bi ' . $statusIcon . '"></i> ' . htmlspecialchars($status);
    echo '<i class="bi bi-pencil ms-1 edit-icon" style="cursor:pointer;" onclick="openStatusEditPopup(this,'
        . '\'' . js_safe($entry['trackingNo']) . '\','
        . '\'' . js_safe($status) . '\','
        . '\'' . js_safe($entry['assignedTo'] ?? '') . '\','
        . '\'' . js_safe($entry['message'] ?? '') . '\')"></i></span>';
    if (!empty($entry['message'])) {
        echo '<span class="msg-icon-wrap position-relative" style="margin-left:10px;vertical-align:middle;">';
        echo '<i class="bi bi-chat-left-text-fill text-primary" style="font-size:1.2em;cursor:pointer;" data-tracking="' . htmlspecialchars($entry['trackingNo']) . '" onmouseenter="showMsgBubble(this, problemMessages[this.getAttribute(\'data-tracking\')])" onmouseleave="msgBubbleTimeout = setTimeout(hideMsgBubble, 120);" onclick="toggleMsgBubble(this, problemMessages[this.getAttribute(\'data-tracking\')])"></i>';
        echo '</span>';
    }
    echo '</span>';
    echo '</td>';
    // 7. Tarih
    echo '<td class="text-center"><small>' . htmlspecialchars($entry['date'] ?? '-') . '</small></td>';
    // 8. IP (sadece MainAdmin ise)
    if ($currentUser && $currentUser['role'] === 'MainAdmin') {
        echo '<td>' . htmlspecialchars($entry['ip'] ?? '-') . '</td>';
    }
    // 9. Admin Mesajı
    echo '<td>' . htmlspecialchars($entry['adminMessage'] ?? '') . '</td>';
    // 10. Sil (sadece MainAdmin ise)
    if ($currentUser && $currentUser['role'] === 'MainAdmin') {
        echo '<td class="text-center">';
        echo '<form method="post" onsubmit="return confirm(\'Bu arızayı silmek istediğinize emin misiniz?\');" style="display:inline-block">';
        echo '<input type="hidden" name="delete_trackingNo" value="' . htmlspecialchars($entry['trackingNo']) . '">';
        echo '<button type="submit" name="delete_fault" class="btn btn-danger btn-sm" title="Sil">';
        echo '<i class="bi bi-trash"></i>';
        echo '</button>';
        echo '</form>';
        echo '</td>';
    }
    // 11. Detaylı İncele
    echo '<td class="text-center">';
    echo '<button type="button" class="btn btn-outline-info btn-sm detail-btn" onclick="openDetailCardbox(\'' . addslashes(trim((string)$entry['trackingNo'])) . '\', this, event)" title="Detaylı İncele">';
    echo '<i class="bi bi-search"></i> Detay';
    echo '</button>';
    echo '</td>';
    echo '</tr>';
endforeach;
?>
    </tbody>
    </table>
    <script>
    const faultTypes = <?= json_encode($faultTypes, JSON_UNESCAPED_UNICODE) ?>;
    const problemsData = <?= json_encode($problems, JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <h2>Arıza İstatistikleri</h2>
    <?php
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
    <div class="row mb-4 justify-content-center">
      <div class="col-md-2 mb-3">
        <div class="card text-bg-primary h-100">
          <div class="card-body text-center">
            <div class="fs-2 fw-bold"><?= $totalFaults ?></div>
            <div class="fw-semibold">Toplam Arıza</div>
          </div>
        </div>
      </div>
      <div class="col-md-2 mb-3">
        <div class="card text-bg-warning h-100">
          <div class="card-body text-center">
            <div class="fs-2 fw-bold"><?= $statusCounts['Bekliyor'] ?></div>
            <div class="fw-semibold">Bekliyor</div>
          </div>
        </div>
      </div>
      <div class="col-md-2 mb-3">
        <div class="card text-bg-info h-100">
          <div class="card-body text-center">
            <div class="fs-2 fw-bold"><?= $statusCounts['Onaylandı'] ?></div>
            <div class="fw-semibold">Onaylandı</div>
          </div>
        </div>
      </div>
      <div class="col-md-2 mb-3">
        <div class="card text-bg-success h-100">
          <div class="card-body text-center">
            <div class="fs-2 fw-bold"><?= $statusCounts['Tamamlandı'] ?></div>
            <div class="fw-semibold">Tamamlandı</div>
          </div>
        </div>
      </div>
      <div class="col-md-2 mb-3">
        <div class="card text-bg-secondary h-100">
          <div class="card-body text-center">
            <div class="fs-2 fw-bold"><?= $lastMonthCount ?></div>
            <div class="fw-semibold">Son 1 Ayda Açılan</div>
          </div>
        </div>
      </div>
    </div>
    <div class="row mb-4 justify-content-center">
      <div class="col-md-4 mb-3">
        <div class="card h-100">
          <div class="card-body text-center">
            <div class="fw-semibold">En Çok Arıza Bildiren Birim</div>
            <div class="fs-4 fw-bold"><?= htmlspecialchars($mostReportedDepartment) ?></div>
            <div class="text-muted">(<?= $mostReportedDepartmentCount ?> arıza)</div>
          </div>
        </div>
      </div>
      <div class="col-md-4 mb-3">
        <div class="card h-100">
          <div class="card-body text-center">
            <div class="fw-semibold">En Çok Görülen Arıza Türü</div>
            <div class="fs-4 fw-bold"><?= htmlspecialchars($mostReportedType) ?></div>
            <div class="text-muted">(<?= $mostReportedTypeCount ?> arıza)</div>
          </div>
        </div>
      </div>
    </div>
    <div class="row mb-4 justify-content-center">
      <div class="col-md-6">
        <canvas id="chartType" width="400" height="220"></canvas>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    <?php
    $typeCounts = [];
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry) {
                $type = $entry['faultType'] ?? '-';
                $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            }
        }
    }
    ?>
    const typeData = {
      labels: <?= json_encode(array_keys($typeCounts), JSON_UNESCAPED_UNICODE) ?>,
      datasets: [{
        label: 'Arıza Türü',
        data: <?= json_encode(array_values($typeCounts)) ?>,
        backgroundColor: ['#0d6efd','#20c997','#ffc107','#fd7e14','#6f42c1','#dc3545','#198754','#0dcaf0','#adb5bd','#343a40']
      }]
    };
    if (document.getElementById('chartType')) {
    new Chart(document.getElementById('chartType'), {
      type: 'doughnut',
      data: typeData,
      options: { plugins: { legend: { position: 'bottom' } }, responsive:true }
    });
    }
    </script>
</div>
<?php elseif ($tab=='altadmin'): ?>
<div class="container">
    <h1 class="mb-4">Alt Adminler</h1>
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-primary">
        <tr>
            <th>ID</th>
            <th>Kullanıcı Adı</th>
            <th>Rol</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($altAdmins as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['id']) ?></td>
                <td><?= htmlspecialchars($a['username']) ?></td>
                <td><?= htmlspecialchars($a['role']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php elseif ($tab=='teknik'): ?>
<div class="container">
    <h1 class="mb-4">Teknik Personeller</h1>
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-primary">
        <tr>
            <th>ID</th>
            <th>Kullanıcı Adı</th>
            <th>Rol</th>
            <th>Branş</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($teknikPersonel as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['id']) ?></td>
                <td><?= htmlspecialchars($t['username']) ?></td>
                <td><?= htmlspecialchars($t['role']) ?></td>
                <td><?= htmlspecialchars($t['profession'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php elseif ($tab=='kullanicilar'): ?>
<div class="container">
    <h1 class="mb-4">Kullanıcılar ve Yetkiler</h1>
    <form method="post" class="row g-3 mb-4">
        <div class="col-md-3">
            <input type="text" name="username" class="form-control" placeholder="Kullanıcı Adı" required>
        </div>
        <div class="col-md-3">
            <select name="role" class="form-select" required>
                <option value="MainAdmin">MainAdmin</option>
                <option value="Admin">Admin</option>
                <option value="TeknikPersonel">TeknikPersonel</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" name="add_user" class="btn btn-success">Ekle</button>
        </div>
    </form>
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-primary">
        <tr>
            <th>ID</th>
            <th>Kullanıcı Adı</th>
            <th>Rol</th>
            <th>İşlemler</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['id']) ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['role']) ?></td>
                <td>
                    <form method="post" style="display:inline-block">
                        <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Sil</button>
                    </form>
                    <form method="post" style="display:inline-block">
                        <input type="hidden" name="update_id" value="<?= $u['id'] ?>">
                        <input type="text" name="update_username" value="<?= htmlspecialchars($u['username']) ?>" class="form-control form-control-sm d-inline w-auto" required>
                        <select name="update_role" class="form-select form-select-sm d-inline w-auto" required>
                            <option value="MainAdmin" <?= $u['role']=='MainAdmin'?'selected':'' ?>>MainAdmin</option>
                            <option value="Admin" <?= $u['role']=='Admin'?'selected':'' ?>>Admin</option>
                            <option value="TeknikPersonel" <?= $u['role']=='TeknikPersonel'?'selected':'' ?>>TeknikPersonel</option>
                        </select>
                        <button type="submit" name="update_user" class="btn btn-primary btn-sm">Güncelle</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
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
<!-- Açıklama popup -->
<div id="descPopup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); min-width:320px; max-width:90vw; background:#fff; border-radius:16px; box-shadow:0 8px 40px rgba(0,0,0,0.18); z-index:9999; padding:2.2rem 2.2rem 1.5rem 2.2rem; font-size:1.15em; line-height:1.5;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <span style="font-size:1.25em;font-weight:600;">Açıklama</span>
    <button class="btn btn-light btn-sm" style="opacity:0.7;font-size:1.5em;line-height:1;" onclick="closeDescPopup()">&times;</button>
  </div>
  <div id="descPopupContent" style="max-height:60vh; overflow:auto; word-break:break-word;"></div>
</div>
<div id="descOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9998;" onclick="closeDescPopup()"></div>
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
<!-- Mesaj bubble -->
<style>
.msg-bubble {
  display: block;
  position: absolute;
  left: 50%;
  bottom: 120%;
  transform: translateX(-50%);
  min-width: 200px;
  max-width: 340px;
  background: #fff;
  color: #005ca9;
  border-radius: 10px;
  box-shadow: 0 6px 32px rgba(0,92,169,0.18);
  padding: 14px 18px;
  font-size: 1.08em;
  z-index: 1000;
  white-space: pre-line;
  word-break: break-word;
  border: 1.5px solid #b6d4fa;
}
.msg-bubble::after {
  content: '';
  position: absolute;
  left: 50%;
  top: 100%;
  transform: translateX(-50%);
  border-width: 8px;
  border-style: solid;
  border-color: #fff transparent transparent transparent;
  filter: drop-shadow(0 2px 2px #b6d4fa);
}
</style>
<script>
let msgBubbleEl = null;
let openCardbox = null;
let msgBubbleTimeout = null;
function showMsgBubble(icon, msg) {
  hideMsgBubble();
  msgBubbleEl = document.createElement('div');
  msgBubbleEl.className = 'msg-bubble';
  msgBubbleEl.innerText = msg;
  icon.parentElement.appendChild(msgBubbleEl);
  msgBubbleEl.addEventListener('mouseenter', function() {
    if (msgBubbleTimeout) clearTimeout(msgBubbleTimeout);
  });
  msgBubbleEl.addEventListener('mouseleave', function() {
    hideMsgBubble();
  });
}
function hideMsgBubble() {
  if (msgBubbleTimeout) clearTimeout(msgBubbleTimeout);
  if (msgBubbleEl && msgBubbleEl.parentElement) msgBubbleEl.parentElement.removeChild(msgBubbleEl);
  msgBubbleEl = null;
}
function toggleMsgBubble(icon, msg) {
  if (msgBubbleEl) { hideMsgBubble(); return; }
  showMsgBubble(icon, msg);
}
const problemMessages = <?php
$messagesArr = [];
if (file_exists(PROBLEM_LOG_FILE)) {
    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry && !empty($entry['message']) && !empty($entry['trackingNo'])) {
            $messagesArr[$entry['trackingNo']] = $entry['message'];
        }
    }
}
$out = json_encode($messagesArr, JSON_UNESCAPED_UNICODE);
echo ($out && $out !== 'null') ? $out : '{}';
?>;
</script>
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
<!-- Durum Düzenleme Popup -->
<div id="statusEditOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:9998;" onclick="closeStatusEditPopup(event)"></div>
<div id="statusEditPopup" style="display:none; position:fixed; min-width:320px; background:#fff; border-radius:16px; box-shadow:0 8px 40px rgba(0,0,0,0.18); z-index:9999; padding:2rem 2rem 1.5rem 2rem;">
    <form method="post" id="statusEditForm">
        <input type="hidden" name="update_trackingNo" id="statusEdit_trackingNo">
        <div class="mb-3">
            <label class="form-label">Durum</label>
            <select name="update_status" id="statusEdit_status" class="form-select" required>
<?php foreach ($faultStatuses as $key => $label): ?>
    <option value="<?= $key ?>"><?= $label ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Teknisyen</label>
            <select name="update_technician" id="statusEdit_technician" class="form-select">
                <option value="">(Atanmamış)</option>
                <?php foreach ($teknikPersonel as $t): ?>
                    <option value="<?= htmlspecialchars($t['username']) ?>">
                        <?= htmlspecialchars($t['username']) ?><?php if (!empty($t['profession'])): ?> (<?= htmlspecialchars($t['profession']) ?>)<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Mesaj</label>
            <textarea name="update_message" id="statusEdit_message" class="form-control" rows="3" placeholder="Bu arıza için not veya bilgi mesajı yazın..."></textarea>
        </div>
        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" onclick="closeStatusEditPopup()">İptal</button>
            <button type="submit" class="btn btn-success">Kaydet</button>
        </div>
    </form>
</div>
<script>
function closeStatusEditPopup(e) {
    if (!e || e.target === document.getElementById('statusEditOverlay')) {
        document.getElementById('statusEditOverlay').style.display = 'none';
        document.getElementById('statusEditPopup').style.display = 'none';
    }
}
</script>
<script>
// Global error handler
window.onerror = function(message, source, lineno, colno, error) {
  return false;
};
// Durum edit popup fonksiyonu
function openStatusEditPopup(badgeEl, trackingNo, status, technician, message) {
  var popup = document.getElementById('statusEditPopup');
  var overlay = document.getElementById('statusEditOverlay');
  document.getElementById('statusEdit_trackingNo').value = trackingNo;
  document.getElementById('statusEdit_status').value = status;
  document.getElementById('statusEdit_technician').value = technician || '';
  document.getElementById('statusEdit_message').value = message || '';
  popup.style.top = '50%';
  popup.style.left = '50%';
  popup.style.transform = 'translate(-50%, -50%)';
  popup.style.display = 'block';
  overlay.style.display = 'block';
}
// Detay popup fonksiyonu
function openDetailCardbox(trackingNo, btn, event) {
    if (event) event.stopPropagation();
    if (openCardbox) openCardbox.remove();
    const p = problemsData.find(x => String(x.trackingNo).trim() === String(trackingNo).trim());
    if (!p) {
        alert('Detay bulunamadı: ' + trackingNo);
        return;
    }
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
    if (p.message && p.message !== '') {
      html += `<div class='mb-2'><b>Teknik Personel Mesajı:</b><br><span>${p.message}</span></div>`;
    }
    html += `<div class='mb-2'><b>Birim:</b> ${p.department ?? '-'}</div>`;
    html += `<div class='mb-2'><b>Arıza Türü:</b> ${faultTypes[p.faultType] ?? p.faultType}</div>`;
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Karanlık mod fonksiyonu (tüm panellerle birebir aynı)
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
// Modal butonları debug
const notifBtn = document.getElementById('notifBtn');
notifBtn && notifBtn.addEventListener('click', function() {
});
const helpBtn = document.getElementById('helpBtn');
helpBtn && helpBtn.addEventListener('click', function() {
});
// Toast'ları otomatik ve çarpıdan kapatılabilir yap
var toastElList = [].slice.call(document.querySelectorAll('.toast'));
toastElList.forEach(function (toastEl) {
  new bootstrap.Toast(toastEl, { delay: 4000 }).show();
});
</script>
</body>
</html> 