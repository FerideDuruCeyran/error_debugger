<?php
session_start();
$page = 'tracking';
require_once 'config.php';

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trackingNo = trim($_POST['trackingNo'] ?? '');
    if ($trackingNo === '') {
        $error = 'Takip numarası giriniz.';
    } else {
        if (file_exists(PROBLEM_LOG_FILE)) {
            $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry && isset($entry['trackingNo']) && strtoupper($entry['trackingNo']) === strtoupper($trackingNo)) {
                    $result = $entry;
                    break;
                }
            }
            if (!$result) {
                $error = 'Bu takip numarasına ait kayıt bulunamadı.';
            }
        } else {
            $error = 'Henüz hiç arıza bildirimi yapılmamış.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Arıza Takip - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="https://upload.wikimedia.org/wikipedia/tr/d/dc/Akdeniz_%C3%9Cniversitesi_logosu.IMG_0838.png" class="akdeniz-logo" alt="Akdeniz Üniversitesi">
      Akdeniz Üniversitesi
    </a>
    <div>
      <a class="btn btn-outline-light me-2<?= $page=='fault_form'?' active':'' ?>" href="fault_form.php">Arıza Bildir</a>
      <a class="btn btn-outline-light me-2<?= $page=='tracking'?' active':'' ?>" href="tracking.php">Takip</a>
      <a class="btn btn-outline-light ms-2" href="login.php">Yönetici Girişi</a>
    </div>
  </div>
</nav>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="mb-4">Arıza Takip</h2>
                    <form method="post" class="mb-4">
                        <div class="mb-3">
                            <label class="form-label">Takip Numaranız</label>
                            <input type="text" name="trackingNo" class="form-control" value="<?= htmlspecialchars($_POST['trackingNo'] ?? '') ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Sorgula</button>
                    </form>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
                    <?php endif; ?>
                    <?php if ($result): ?>
                        <h3>Arıza Bilgileri</h3>
                        <ul class="list-group mb-3">
                            <li class="list-group-item"><b>Arıza Türü:</b> <?= htmlspecialchars($result['faultType']) ?></li>
                            <li class="list-group-item"><b>Başlık:</b> <?= htmlspecialchars($result['title']) ?></li>
                            <li class="list-group-item"><b>İçerik:</b> <?= htmlspecialchars($result['content']) ?></li>
                            <li class="list-group-item"><b>Bilgisayar Özellikleri:</b> <?= htmlspecialchars($result['specs'] ?? '-') ?></li>
                            <li class="list-group-item"><b>Birim:</b> <?= htmlspecialchars($result['department']) ?></li>
                            <li class="list-group-item"><b>Tarih:</b> <?= htmlspecialchars($result['date']) ?></li>
                            <li class="list-group-item"><b>Durum:</b> <?= htmlspecialchars($result['status']) ?></li>
                            <li class="list-group-item"><b>İletişim:</b> <?= htmlspecialchars($result['contact']) ?></li>
                            <?php if (!empty($result['assignedTo'])): ?>
                                <li class="list-group-item"><b>Atanan Kişi:</b> <?= htmlspecialchars($result['assignedTo']) ?></li>
                            <?php endif; ?>
                            <?php if (!empty($result['filePath'])): ?>
                                <li class="list-group-item"><b>Dosya:</b> <a href="<?= htmlspecialchars(basename($result['filePath'])) ?>" target="_blank">Dosyayı Görüntüle</a></li>
                            <?php endif; ?>
                            <?php if (!empty($result['userAgent'])): ?>
                                <li class="list-group-item"><b>Tarayıcı:</b> <?= htmlspecialchars($result['userAgent']) ?></li>
                            <?php endif; ?>
                            <?php if (!empty($result['ip'])): ?>
                                <li class="list-group-item"><b>IP:</b> <?= htmlspecialchars($result['ip']) ?></li>
                            <?php endif; ?>
                            <li class="list-group-item"><b>Takip No:</b> <?= htmlspecialchars($result['trackingNo']) ?></li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 