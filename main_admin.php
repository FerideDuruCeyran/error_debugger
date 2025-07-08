<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['user'] !== 'admin') {
    header('Location: index.php');
    exit;
}
require_once 'config.php';

// Kullanıcılar (örnek veri, gerçek uygulamada veritabanı gerekir)
$users = [
    ['id' => 1, 'username' => 'mainadmin', 'role' => 'MainAdmin'],
];
// Sadece admin ve main admin göster
$users = array_filter($users, function($u) {
    return in_array($u['role'], ['Admin', 'MainAdmin']);
});

// Kullanıcı ekleme/güncelleme işlemleri burada yapılabilir (örnek için statik)

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
$page = 'main_admin';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Main Admin Paneli - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="https://upload.wikimedia.org/wikipedia/tr/3/3d/Akdeniz_%C3%9Cniversitesi_logo.png" class="akdeniz-logo" alt="Akdeniz Üniversitesi">
      Akdeniz Üniversitesi
    </a>
    <div>
      <!-- Takip sekmesi kaldırıldı -->
      <a class="btn btn-outline-light me-2<?= $page=='admin'?' active':'' ?>" href="admin.php">Admin</a>
      <a class="btn btn-outline-light me-2<?= $page=='main_admin'?' active':'' ?>" href="main_admin.php">Main Admin</a>
      <span class="text-white ms-3">Hoşgeldiniz, <b><?= htmlspecialchars($_SESSION['user']) ?></b></span>
      <a class="btn btn-outline-light ms-2" href="logout.php">Çıkış</a>
    </div>
  </div>
</nav>
<div class="container">
    <h1 class="mb-4">Main Admin Paneli</h1>
    <h2>Kullanıcılar ve Yetkiler</h2>
    <table class="table table-bordered table-striped align-middle mb-4">
        <thead class="table-primary">
        <tr><th>ID</th><th>Kullanıcı Adı</th><th>Rol</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['role']) ?></td>
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