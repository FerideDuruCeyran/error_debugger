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

// Rapor filtreleri
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
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Main Admin Paneli - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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
                <option value="" <?= $status === '' ? 'selected' : '' ?>>Tümü</option>
                <option value="Bekliyor" <?= $status === 'Bekliyor' ? 'selected' : '' ?>>Bekliyor</option>
                <option value="Onaylandı" <?= $status === 'Onaylandı' ? 'selected' : '' ?>>Onaylandı</option>
                <option value="Tamamlandı" <?= $status === 'Tamamlandı' ? 'selected' : '' ?>>Tamamlandı</option>
            </select>
        </div>
        <div class="col-md-2 align-self-end">
            <button type="submit" class="btn btn-primary w-100">Filtrele</button>
        </div>
    </form>
    <h2>Tüm Arızalar ve Durumları</h2>
    <table id="reportTable" class="table table-bordered table-striped align-middle mb-4">
    <thead class="table-primary">
    <tr>
        <th>Takip No</th>
        <th>Açıklama</th>
        <th>Birim</th>
        <th>Bilgisayar Özellikleri</th>
        <th>İletişim</th>
        <th>Atanan</th>
        <th>Durum</th>
        <th>Tarih</th>
        <?php if ($currentUser && $currentUser['role'] === 'MainAdmin'): ?>
            <th>IP</th>
        <?php endif; ?>
        <th>Admin Mesajı</th>
        <?php if ($currentUser && $currentUser['role'] === 'MainAdmin'): ?>
            <th>Sil</th>
        <?php endif; ?>
    </tr>
    </thead>
    <tbody>
    <?php
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($entry['trackingNo']) . '</td>';
                echo '<td>' . htmlspecialchars($entry['description'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($entry['department'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($entry['specs'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($entry['contact'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($entry['assignedTo'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($entry['status'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($entry['date'] ?? '-') . '</td>';
                if ($currentUser && $currentUser['role'] === 'MainAdmin') {
                    echo '<td>' . htmlspecialchars($entry['ip'] ?? '-') . '</td>';
                }
                echo '<td>' . htmlspecialchars($entry['adminMessage'] ?? '') . '</td>';
                if ($currentUser && $currentUser['role'] === 'MainAdmin') {
                    echo '<td>';
                    echo '<form method="post" onsubmit="return confirm(\'Bu arızayı silmek istediğinize emin misiniz?\');" style="display:inline-block">';
                    echo '<input type="hidden" name="delete_trackingNo" value="' . htmlspecialchars($entry['trackingNo']) . '">';
                    echo '<button type="submit" name="delete_fault" class="btn btn-danger btn-sm">Sil</button>';
                    echo '</form>';
                    echo '</td>';
                }
                echo '</tr>';
            }
        }
    }
    ?>
    </tbody>
    </table>
    <h2>Arıza İstatistikleri</h2>
    <div class="row mb-4 justify-content-center">
      <div class="col-md-6 mb-3">
        <canvas id="chartType"></canvas>
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
    new Chart(document.getElementById('chartType'), {
      type: 'doughnut',
      data: typeData,
      options: { plugins: { legend: { position: 'bottom' } }, responsive:true }
    });
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
</script>
</body>
</html> 