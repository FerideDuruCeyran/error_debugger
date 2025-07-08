<?php
session_start();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (($username === 'admin' && $password === '1234') ||
        ($username === 'user' && $password === 'user123') ||
        ($username === 'mainadmin' && $password === 'main1234')) {
        $_SESSION['user'] = $username;
        header('Location: index.php');
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
</head>
<body class="login-bg">
    <div class="card login-card">
        <div class="card-header">
            <img src="https://upload.wikimedia.org/wikipedia/tr/3/3d/Akdeniz_%C3%9Cniversitesi_logo.png" class="akdeniz-logo" alt="Akdeniz Üniversitesi">
            Akdeniz Üniversitesi Arıza Takip Sistemi
        </div>
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" name="username" class="form-control" required autofocus>
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
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 