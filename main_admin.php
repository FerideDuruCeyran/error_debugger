<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user'] !== 'mainadmin') {
    header('Location: login.php');
    exit;
}
require_once 'config.php';

// Kullanıcılar dosyadan okunacak
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

$page = 'main_admin';

// Bildirim örneği (gerçek uygulamada dinamik olacak)
$notification = '';
if ($_SESSION['user'] === 'user') {
    $notification = 'Arızanız onaylandı!';
} elseif ($_SESSION['user'] === 'teknikpersonel') {
    $notification = 'Yeni bir arıza size atandı!';
} elseif ($_SESSION['user'] === 'admin') {
    $notification = 'Sistemde 2 yeni arıza bildirimi var.';
} elseif ($_SESSION['user'] === 'mainadmin') {
    $notification = 'Kullanıcı yönetimi için yeni talepler var.';
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
</head>
<body class="bg-light">
<?php
// Sekme kontrolü
$tab = $_GET['tab'] ?? 'arizalar';
$usersFile = 'users.json';
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
$altAdmins = array_filter($users, function($u){ return $u['role']==='Admin'; });
$teknikPersonel = array_filter($users, function($u){ return $u['role']==='TeknikPersonel'; });
?>
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
    <div class="col-12">
      <ul class="nav nav-tabs justify-content-center" style="border-bottom: 2px solid #e3f0fa;">
        <li class="nav-item flex-fill text-center"><a class="nav-link<?= $tab=='arizalar'?' active':'' ?>" href="main_admin.php?tab=arizalar"><i class="bi bi-list-task"></i> Tüm Arızalar</a></li>
        <li class="nav-item flex-fill text-center"><a class="nav-link<?= $tab=='altadmin'?' active':'' ?>" href="main_admin.php?tab=altadmin"><i class="bi bi-person-badge"></i> Alt Adminler</a></li>
        <li class="nav-item flex-fill text-center"><a class="nav-link<?= $tab=='teknik'?' active':'' ?>" href="main_admin.php?tab=teknik"><i class="bi bi-person-gear"></i> Teknik Personeller</a></li>
      </ul>
    </div>
  </div>
</div>
<?php if ($tab=='altadmin'): ?>
<div class="container mb-4">
  <h4>Alt Adminler</h4>
  <table class="table table-bordered table-striped">
    <thead><tr><th>Kullanıcı Adı</th><th>Arıza Atama Sayısı</th></tr></thead>
    <tbody>
      <?php foreach ($altAdmins as $a): ?>
        <tr><td><?= htmlspecialchars($a['username']) ?></td><td>
        <?php
        $count=0;
        $logFile = defined('PROBLEM_LOG_FILE') ? PROBLEM_LOG_FILE : 'problem_log.txt';
        if (file_exists($logFile)) {
          $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
          foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['assignedBy']) && $entry['assignedBy']===$a['username']) $count++;
          }
        }
        echo $count;
        ?>
        </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php elseif ($tab=='teknik'): ?>
<div class="container mb-4">
  <h4>Teknik Personeller</h4>
  <table class="table table-bordered table-striped">
    <thead><tr><th>Kullanıcı Adı</th><th>Atanan Arıza</th><th>Tamamlanan</th></tr></thead>
    <tbody>
      <?php foreach ($teknikPersonel as $t): ?>
        <tr><td><?= htmlspecialchars($t['username']) ?></td><td>
        <?php
        $at=0; $done=0;
        $logFile = defined('PROBLEM_LOG_FILE') ? PROBLEM_LOG_FILE : 'problem_log.txt';
        if (file_exists($logFile)) {
          $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
          foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['assigned']) && $entry['assigned']===$t['username']) {
              $at++;
              if (($entry['status']??'')==='Tamamlandı') $done++;
            }
          }
        }
        echo $at.'</td><td>'.$done;
        ?>
        </td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="container mb-4">
  <h4>Tüm Arızalar</h4>
  <table class="table table-bordered table-striped">
    <thead><tr><th>Takip No</th><th>Tür</th><th>Açıklama</th><th>Durum</th><th>Atanan</th><th>Tarih</th></tr></thead>
    <tbody>
      <?php
      $logFile = defined('PROBLEM_LOG_FILE') ? PROBLEM_LOG_FILE : 'problem_log.txt';
      if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
          $entry = json_decode($line, true);
          if ($entry) {
            $status = ($entry['status']??'')==='Tamamlandı' ? 'Yapıldı' : (($entry['status']??'')==='Bekliyor' ? 'Beklemede' : 'Devam Ediyor');
            echo '<tr>';
            echo '<td>' . htmlspecialchars($entry['trackingNo']??'') . '</td>';
            echo '<td>' . htmlspecialchars($entry['faultType']??'-') . '</td>';
            echo '<td>' . htmlspecialchars($entry['description']??'') . '</td>';
            echo '<td>' . $status . '</td>';
            echo '<td>' . htmlspecialchars($entry['assigned']??'-') . '</td>';
            echo '<td>' . htmlspecialchars($entry['date']??'-') . '</td>';
            echo '</tr>';
          }
        }
      }
      ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php if ($notification): ?>
<div class="container mt-2">
  <div class="alert alert-info alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($notification) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
  </div>
</div>
<?php endif; ?>
<div class="container">
    <h1 class="mb-4">Main Admin Paneli</h1>
    <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $successMsg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $errorMsg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <h2>Kullanıcılar ve Yetkiler</h2>
    <!-- Kullanıcı ekleme formu -->
    <form method="post" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="username" class="form-control" placeholder="Kullanıcı Adı" required>
        </div>
        <div class="col-md-4">
            <select name="role" class="form-select" required>
                <option value="Admin">Admin</option>
                <option value="MainAdmin">MainAdmin</option>
                <option value="TeknikPersonel">TeknikPersonel</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" name="add_user" class="btn btn-success w-100">Ekle</button>
        </div>
    </form>
    <table class="table table-bordered table-striped align-middle mb-4">
        <thead class="table-primary">
        <tr><th>ID</th><th>Kullanıcı Adı</th><th>Rol</th><th>İşlemler</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <form method="post" class="d-inline">
                    <td><?= $u['id'] ?><input type="hidden" name="update_id" value="<?= $u['id'] ?>"></td>
                    <td><input type="text" name="update_username" value="<?= htmlspecialchars($u['username']) ?>" class="form-control" required></td>
                    <td>
                        <select name="update_role" class="form-select">
                            <option value="Admin" <?= $u['role']==='Admin'?'selected':'' ?>>Admin</option>
                            <option value="MainAdmin" <?= $u['role']==='MainAdmin'?'selected':'' ?>>MainAdmin</option>
                            <option value="TeknikPersonel" <?= $u['role']==='TeknikPersonel'?'selected':'' ?>>TeknikPersonel</option>
                        </select>
                    </td>
                    <td>
                        <button type="submit" name="update_user" class="btn btn-primary btn-sm">Güncelle</button>
                </form>
                <form method="post" class="d-inline ms-1">
                    <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                    <button type="submit" name="delete_user" class="btn btn-danger btn-sm" onclick="return confirm('Silmek istediğinize emin misiniz?')">Sil</button>
                </form>
                    </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
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
<a href="?export=csv" class="btn btn-success mb-2">Excel'e Aktar</a>
<div class="row mb-2">
  <div class="col-md-3">
    <input type="text" id="tableSearch" class="form-control" placeholder="Tabloda ara...">
  </div>
  <div class="col-md-2">
    <select id="statusFilter" class="form-select">
      <option value="">Tüm Durumlar</option>
      <option value="Bekliyor">Bekliyor</option>
      <option value="Onaylandı">Onaylandı</option>
      <option value="Tamamlandı">Tamamlandı</option>
    </select>
  </div>
  <div class="col-md-2">
    <input type="text" id="departmentFilter" class="form-control" placeholder="Birim ara...">
  </div>
  <div class="col-md-2">
    <input type="date" id="dateStart" class="form-control" placeholder="Başlangıç Tarihi">
  </div>
  <div class="col-md-2">
    <input type="date" id="dateEnd" class="form-control" placeholder="Bitiş Tarihi">
  </div>
</div>
<table id="reportTable" class="table table-bordered table-striped align-middle mb-4">
<thead class="table-primary">
<tr><th>Takip No</th><th>Açıklama</th><th>Birim</th><th>Bilgisayar Özellikleri</th><th>İletişim</th><th>Atanan</th><th>Durum</th><th>Tarih</th></tr>
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
            echo '<td>' . htmlspecialchars($entry['description']) . '</td>';
            echo '<td>' . htmlspecialchars($entry['department'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($entry['specs'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($entry['contact'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($entry['assignedTo'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($entry['status'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($entry['date'] ?? '-') . '</td>';
            echo '</tr>';
        }
    }
}
?>
</tbody>
</table>
<script>
function filterTable() {
  var search = document.getElementById('tableSearch').value.toLowerCase();
  var status = document.getElementById('statusFilter').value;
  var department = document.getElementById('departmentFilter').value.toLowerCase();
  var dateStart = document.getElementById('dateStart').value;
  var dateEnd = document.getElementById('dateEnd').value;
  var rows = document.querySelectorAll('#reportTable tbody tr');
  rows.forEach(function(row) {
    var text = row.textContent.toLowerCase();
    var statusCell = row.children[6] ? row.children[6].textContent.trim() : '';
    var departmentCell = row.children[2] ? row.children[2].textContent.toLowerCase() : '';
    var dateCell = row.children[7] ? row.children[7].textContent.trim() : '';
    var show = true;
    if (search && text.indexOf(search) === -1) show = false;
    if (status && statusCell !== status) show = false;
    if (department && departmentCell.indexOf(department) === -1) show = false;
    if (dateStart && dateCell < dateStart) show = false;
    if (dateEnd && dateCell > dateEnd) show = false;
    row.style.display = show ? '' : 'none';
  });
}
document.getElementById('tableSearch').addEventListener('keyup', filterTable);
document.getElementById('statusFilter').addEventListener('change', filterTable);
document.getElementById('departmentFilter').addEventListener('keyup', filterTable);
document.getElementById('dateStart').addEventListener('change', filterTable);
document.getElementById('dateEnd').addEventListener('change', filterTable);
</script>
    <h2>Teknik Personel Ata</h2>
    <form method="post" class="mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Arıza Takip No</label>
                <input type="text" name="assign_tracking" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Teknik Personel</label>
                <select name="assign_personnel" class="form-control" required>
                    <?php foreach ($personnel as $p): ?>
                        <option value="<?= htmlspecialchars($p['username']) ?>">
                            <?= htmlspecialchars($p['username']) ?><?php if (!empty($p['profession'])): ?> (<?= htmlspecialchars($p['profession']) ?>)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" name="assign_submit" class="btn btn-primary">Ata</button>
            </div>
        </div>
        <?php if (isset($_POST['assign_personnel']) && isset($altadminJobCount[$_POST['assign_personnel']]) && $altadminJobCount[$_POST['assign_personnel']] >= 10): ?>
            <div class="alert alert-danger mt-2">Bu teknik personele daha fazla arıza atanamaz (10/10 dolu).</div>
        <?php endif; ?>
    </form>
    <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-primary">
        <tr>
            <th>Takip No</th>
            <th>Tür</th>
            <th>Başlık</th>
            <th>Birim</th>
            <th>Tarih</th>
            <th>Durum</th>
            <th>Tarayıcı</th>
            <th>IP</th>
            <th>Admin Mesajı</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($reports as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['trackingNo']) ?></td>
                <td><?= htmlspecialchars($r['faultType']) ?></td>
                <td><?= htmlspecialchars($r['title']) ?></td>
                <td><?= htmlspecialchars($r['department']) ?></td>
                <td><?= htmlspecialchars($r['date']) ?></td>
                <td><?= htmlspecialchars($r['status']) ?></td>
                <td><?= htmlspecialchars($r['userAgent'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['ip'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['adminMessage'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 