<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user'], ['altadmin', 'teknikpersonel'])) {
    header('Location: login.php');
    exit;
}
require_once 'config.php';

$filter = $_GET['filter'] ?? '';
$updateMsg = '';
$successMsg = $errorMsg = '';

// Durum güncelleme
if (isset($_POST['update_status'])) {
    $trackingNo = $_POST['trackingNo'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';
    if ($trackingNo && $newStatus) {
        // Burada veritabanı/güncelleme işlemi yapılmalı
        // updateFaultStatus($trackingNo, $newStatus);
        $successMsg = 'Durum başarıyla güncellendi.';
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
                $lines[$i] = json_encode($entry, JSON_UNESCAPED_UNICODE);
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
    $count = 0;
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry) {
            // Sadece kendisine atanmış arızaları göster (hem altadmin hem teknikpersonel için)
            if (isset($entry['assignedTo']) && $entry['assignedTo'] === $_SESSION['user']) {
                if ($filter === '' || $entry['status'] === $filter) {
                    if ($count < 15) {
                        $problems[] = $entry;
                        $count++;
                    }
                }
            }
        }
    }
}
$page = 'admin';

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
    <title>Admin Paneli - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="logo.png" class="akdeniz-logo" alt="Akdeniz Üniversitesi">
      <span>Akdeniz Üniversitesi</span>
    </a>
    <div>
      <a class="btn btn-outline-light me-2<?= $page=='admin'?' active':'' ?>" href="admin.php">Admin Paneli</a>
      <?php if ($_SESSION['user'] === 'teknikpersonel'): ?>
      <a class="btn btn-outline-light me-2<?= $page=='assigned'?' active':'' ?>" href="?page=assigned">Atanan İşler</a>
      <?php endif; ?>
      <span class="text-white ms-3">Hoşgeldiniz, <b><?= htmlspecialchars($_SESSION['user']) ?></b></span>
      <a class="btn btn-outline-light ms-2" href="logout.php">Çıkış</a>
    </div>
  </div>
</nav>
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
                <option value="Bekliyor" <?= $filter === 'Bekliyor' ? 'selected' : '' ?>>Bekliyor</option>
                <option value="Onaylandı" <?= $filter === 'Onaylandı' ? 'selected' : '' ?>>Onaylandı</option>
                <option value="Tamamlandı" <?= $filter === 'Tamamlandı' ? 'selected' : '' ?>>Tamamlandı</option>
            </select>
        </label>
    </form>
    <?php if ($updateMsg): ?>
        <div class="alert alert-success"> <?= htmlspecialchars($updateMsg) ?> </div>
    <?php endif; ?>
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
    <?php if (isset($_GET['page']) && $_GET['page'] === 'assigned' && $_SESSION['user'] === 'teknikpersonel'): ?>
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
        if ($entry && isset($entry['assignedTo']) && $entry['assignedTo'] === $_SESSION['user']) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($entry['trackingNo']) . '</td>';
            echo '<td>' . htmlspecialchars($entry['description']) . '</td>';
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
                    <option value="altadmin">altadmin</option>
                    <option value="teknikpersonel">teknikpersonel</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" name="assign_tech" class="btn btn-success">Ata</button>
            </div>
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
            <th>Bilgisayar Özellikleri</th>
            <th>İletişim</th>
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
                <td><?= htmlspecialchars($p['specs'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['contact'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['date']) ?></td>
                <td><?= htmlspecialchars($p['status']) ?></td>
                <td><?= htmlspecialchars($p['userAgent'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['ip'] ?? '-') ?></td>
                <td>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="trackingNo" value="<?= htmlspecialchars($p['trackingNo']) ?>">
                        <select name="new_status" class="form-select form-select-sm d-inline w-auto">
                            <option value="Bekliyor" <?= $p['status']==='Bekliyor'?'selected':'' ?>>Bekliyor</option>
                            <option value="Onaylandı" <?= $p['status']==='Onaylandı'?'selected':'' ?>>Onaylandı</option>
                            <option value="Tamamlandı" <?= $p['status']==='Tamamlandı'?'selected':'' ?>>Tamamlandı</option>
                        </select>
                        <button type="submit" name="update_status" class="btn btn-primary btn-sm">Güncelle</button>
                    </form>
                </td>
            </tr>
            <?php if ($_SESSION['user'] === 'teknikpersonel'): ?>
            <tr>
                <td colspan="9">
                    <form method="post" class="d-inline">
                        <input type="hidden" name="confirm_tracking" value="<?= htmlspecialchars($p['trackingNo']) ?>">
                        <button type="submit" name="confirm_status" class="btn btn-success btn-sm">Onayla</button>
                    </form>
                </td>
            </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 