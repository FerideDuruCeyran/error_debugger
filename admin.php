<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
// Sadece admin ve main admin erişebilsin
if (!in_array($_SESSION['user'], ['admin', 'altadmin'])) {
    header('Location: index.php');
    exit;
}
require_once 'config.php';

$filter = $_GET['filter'] ?? '';
$updateMsg = '';

// Durum güncelleme
if (isset($_POST['update_status'], $_POST['trackingNo'], $_POST['new_status'])) {
    $trackingNo = $_POST['trackingNo'];
    $newStatus = $_POST['new_status'];
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $updated = false;
        foreach ($lines as $i => $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $trackingNo) {
                $entry['status'] = $newStatus;
                $lines[$i] = json_encode($entry, JSON_UNESCAPED_UNICODE);
                $updated = true;
                break;
            }
        }
        if ($updated) {
            file_put_contents(PROBLEM_LOG_FILE, implode(PHP_EOL, $lines) . PHP_EOL);
            $updateMsg = 'Durum güncellendi!';
        }
    }
}

// Listele
$problems = [];
if (file_exists(PROBLEM_LOG_FILE)) {
    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $count = 0;
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry) {
            if ($filter === '' || $entry['status'] === $filter) {
                // Sadece 15 arıza formu göster
                if ($count < 15) {
                    $problems[] = $entry;
                    $count++;
                }
            }
        }
    }
}
$page = 'admin';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli - Akdeniz Üniversitesi</title>
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
    <h1 class="mb-4">Admin Paneli - Arıza Listesi</h1>
    <form method="get" class="mb-3">
        <label class="form-label">Duruma Göre Filtrele:
            <select name="filter" class="form-select d-inline w-auto" onchange="this.form.submit()">
                <option value="" <?= $filter === '' ? 'selected' : '' ?>>Tümü</option>
                <option value="Bekliyor" <?= $filter === 'Bekliyor' ? 'selected' : '' ?>>Bekliyor</option>
                <option value="Onaylandı" <?= $filter === 'Onaylandı' ? 'selected' : '' ?>>Onaylandı</option>
                <option value="Tamamlandı" <?= $filter === 'Tamamlandı' ? 'selected' : '' ?>>Tamamlandı</option>
            </select>
        </label>
    </form>
    <?php if ($updateMsg): ?>
        <div class="alert alert-success"> <?= htmlspecialchars($updateMsg) ?> </div>
    <?php endif; ?>
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
            <th>Güncelle</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($problems as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['trackingNo']) ?></td>
                <td><?= htmlspecialchars($p['faultType']) ?></td>
                <td><?= htmlspecialchars($p['title']) ?></td>
                <td><?= htmlspecialchars($p['department']) ?></td>
                <td><?= htmlspecialchars($p['date']) ?></td>
                <td><?= htmlspecialchars($p['status']) ?></td>
                <td><?= htmlspecialchars($p['userAgent'] ?? '') ?></td>
                <td><?= htmlspecialchars($p['ip'] ?? '') ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="trackingNo" value="<?= htmlspecialchars($p['trackingNo']) ?>">
                        <select name="new_status" class="form-select form-select-sm d-inline w-auto">
                            <option value="Bekliyor" <?= $p['status'] === 'Bekliyor' ? 'selected' : '' ?>>Bekliyor</option>
                            <option value="Onaylandı" <?= $p['status'] === 'Onaylandı' ? 'selected' : '' ?>>Onaylandı</option>
                            <option value="Tamamlandı" <?= $p['status'] === 'Tamamlandı' ? 'selected' : '' ?>>Tamamlandı</option>
                        </select>
                        <button type="submit" name="update_status" class="btn btn-sm btn-primary">Güncelle</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 