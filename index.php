<?php
session_start();
$page = 'index';
if (isset($_SESSION['user'])) {
    $usersFile = 'users.json';
    $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
    $currentUser = null;
    foreach ($users as $u) {
        if ($u['username'] === $_SESSION['user']) {
            $currentUser = $u;
            break;
        }
    }
    if ($currentUser) {
        if ($currentUser['role'] === 'MainAdmin') {
            header('Location: main_admin.php');
            exit;
        } elseif ($currentUser['role'] === 'Admin') {
            header('Location: admin.php');
            exit;
        } elseif ($currentUser['role'] === 'TeknikPersonel') {
            header('Location: teknik_personel.php');
            exit;
        }
    }
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
      <img src="https://upload.wikimedia.org/wikipedia/tr/d/dc/Akdeniz_%C3%9Cniversitesi_logosu.IMG_0838.png" class="akdeniz-logo" alt="Akdeniz Üniversitesi">
      <span>Akdeniz Üniversitesi</span>
    </a>
    <div>
      <?php
      $panel = 'index.php';
      if (isset($_SESSION['user'])) {
        $usersFile = 'users.json';
        $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
        $currentUser = null;
        foreach ($users as $u) {
            if ($u['username'] === $_SESSION['user']) {
                $currentUser = $u;
                break;
            }
        }
        if ($currentUser) {
            if ($currentUser['role'] === 'MainAdmin') $panel = 'main_admin.php';
            elseif ($currentUser['role'] === 'Admin') $panel = 'admin.php';
            elseif ($currentUser['role'] === 'TeknikPersonel') $panel = 'teknik_personel.php';
        }
      }
      ?>
      <a class="btn btn-outline-light me-2<?= $page=='fault_form'?' active':'' ?>" href="fault_form.php">Arıza Bildir</a>
      <a class="btn btn-outline-light me-2<?= $page=='tracking'?' active':'' ?>" href="tracking.php">Takip</a>
      <a class="btn btn-outline-light me-2" href="<?= $panel ?>"><i class="bi bi-house"></i> Ana Sayfa</a>
      <a class="btn btn-outline-light ms-2" href="login.php">Yönetici Girişi</a>
    </div>
  </div>
</nav>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h2 class="mb-4">Akdeniz Üniversitesi Arıza Takip Sistemi</h2>
                    <p class="lead">Hoşgeldiniz! Arıza bildirimi yapmak veya mevcut bildiriminizi takip etmek için yukarıdaki butonları kullanabilirsiniz.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 