<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$page = 'tracking';
$isUser = ($_SESSION['user'] === 'user');
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
    <title>Arıza Takip - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="logo.png" class="akdeniz-logo" alt="Akdeniz Üniversitesi">
      Akdeniz Üniversitesi
    </a>
    <div>
      <?php if ($isUser): ?>
        <a class="btn btn-outline-light me-2<?= $page=='fault_form'?' active':'' ?>" href="fault_form.php">Arıza Bildir</a>
      <?php endif; ?>
      <a class="btn btn-outline-light me-2<?= $page=='tracking'?' active':'' ?>" href="tracking.php">Takip</a>
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
<?php
// Mesajlaşma/yorum alanı
if ($result) {
    $commentsFile = 'tracking_comments/' . $result['trackingNo'] . '.json';
    if (!is_dir('tracking_comments')) mkdir('tracking_comments');
    $comments = file_exists($commentsFile) ? json_decode(file_get_contents($commentsFile), true) : [];
    $msgSuccess = $msgError = '';
    if (isset($_POST['add_comment'])) {
        $msg = trim($_POST['comment'] ?? '');
        if ($msg) {
            $comments[] = [
                'user' => $_SESSION['user'],
                'role' => $_SESSION['role'] ?? '',
                'text' => $msg,
                'date' => date('Y-m-d H:i')
            ];
            file_put_contents($commentsFile, json_encode($comments, JSON_UNESCAPED_UNICODE));
            $msgSuccess = 'Açıklama eklendi.';
        } else {
            $msgError = 'Açıklama boş olamaz.';
        }
    }
    ?>
    <div class="card mt-4">
        <div class="card-body">
            <h5 class="mb-3">Açıklamalar / Mesajlar</h5>
            <?php if ($msgSuccess): ?><div class="alert alert-success"> <?= $msgSuccess ?> </div><?php endif; ?>
            <?php if ($msgError): ?><div class="alert alert-danger"> <?= $msgError ?> </div><?php endif; ?>
            <form method="post" class="mb-3">
                <div class="input-group">
                    <input type="text" name="comment" class="form-control" placeholder="Açıklama ekle..." required>
                    <button type="submit" name="add_comment" class="btn btn-primary">Ekle</button>
                </div>
            </form>
            <ul class="list-group">
                <?php foreach ($comments as $c): ?>
                    <li class="list-group-item">
                        <b><?= htmlspecialchars($c['user']) ?><?= $c['role'] ? ' ('.htmlspecialchars($c['role']).')' : '' ?>:</b>
                        <?= htmlspecialchars($c['text']) ?>
                        <span class="text-muted float-end" style="font-size:0.9em;">[<?= htmlspecialchars($c['date']) ?>]</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php } ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 