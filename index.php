<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$page = 'index';
$isUser = ($_SESSION['user'] === 'user');

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
    <title>Arıza Takip Sistemi - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="logo.png" class="akdeniz-logo" alt="Akdeniz Üniversitesi">
      <span>Akdeniz Üniversitesi</span>
    </a>
    <div>
      <?php if ($isUser): ?>
        <a class="btn btn-outline-light me-2<?= $page=='fault_form'?' active':'' ?>" href="fault_form.php">Arıza Bildir</a>
        <a class="btn btn-outline-light me-2<?= $page=='tracking'?' active':'' ?>" href="tracking.php">Takip</a>
      <?php endif; ?>
      <?php if ($_SESSION['user'] === 'admin'): ?>
        <a class="btn btn-outline-light me-2<?= $page=='admin'?' active':'' ?>" href="admin.php">Admin</a>
        <a class="btn btn-outline-light me-2<?= $page=='main_admin'?' active':'' ?>" href="main_admin.php">Main Admin</a>
      <?php elseif ($_SESSION['user'] === 'altadmin'): ?>
        <a class="btn btn-outline-light me-2<?= $page=='admin'?' active':'' ?>" href="admin.php">Admin</a>
      <?php endif; ?>
      <a class="btn btn-outline-light me-2" href="profile.php">Profilim</a>
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
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h2 class="mb-4">Akdeniz Üniversitesi Arıza Takip Sistemi</h2>
                    <p class="lead">Hoşgeldiniz! Sol üstteki menüden ilgili işlemleri seçebilirsiniz.</p>
                    <?php if ($isUser): ?>
                        <p>Arıza bildirmek için <b>Arıza Bildir</b> sekmesini kullanabilirsiniz.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 