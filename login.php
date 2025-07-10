<?php
session_start();
$error = '';
$usersFile = 'users.json';
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $foundUser = null;
    foreach ($users as $u) {
        if ($u['username'] === $username && isset($u['password']) && $u['password'] === $password) {
            $foundUser = $u;
            break;
        }
    }
    if ($foundUser) {
        $_SESSION['user'] = $foundUser['username'];
        // last_login güncelle
        foreach ($users as &$u) {
            if ($u['username'] === $foundUser['username']) {
                $u['last_login'] = date('Y-m-d H:i:s');
                break;
            }
        }
        file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        // Role göre yönlendirme
        if ($foundUser['role'] === 'MainAdmin') {
            header('Location: main_admin.php');
        } elseif ($foundUser['role'] === 'Admin') {
            header('Location: admin.php');
        } elseif ($foundUser['role'] === 'TeknikPersonel') {
            header('Location: teknik_personel.php');
        } else {
            header('Location: index.php');
        }
        exit;
    } else {
        $error = 'Kullanıcı adı veya şifre hatalı.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Akdeniz Üniversitesi Arıza Takip Giriş</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="login-bg">
    <div class="card login-card">
        <div class="card-header">
            <a href="index.php">
                <img src="https://upload.wikimedia.org/wikipedia/tr/d/dc/Akdeniz_%C3%9Cniversitesi_logosu.IMG_0838.png" class="akdeniz-logo mb-2" alt="Akdeniz Üniversitesi" style="width: 100px; height: 100px; object-fit: contain;">
            </a>
            <h5 class="mt-2 mb-0">Akdeniz Üniversitesi Arıza Takip Sistemi</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" name="username" class="form-control" required autofocus placeholder="kullanici">
                </div>
                <div class="mb-3">
                    <label class="form-label">Şifre</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
            </form>
            <div class="mt-3 text-center">
                <a href="forgot_password.php">Şifrenizi mi unuttunuz?</a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 