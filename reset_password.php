<?php
$usersFile = 'users.json';
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
$success = $error = '';
$token = $_GET['token'] ?? '';
$found = null;
foreach ($users as &$u) {
    if (!empty($u['reset_token']) && $u['reset_token'] === $token) {
        $found = &$u;
        break;
    }
}
if (!$found) {
    $error = 'Geçersiz veya süresi dolmuş bağlantı.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['new_password'] ?? '';
    if (strlen($new) < 5) {
        $error = 'Şifre en az 5 karakter olmalı.';
    } else {
        $found['password'] = $new;
        $found['reset_token'] = '';
        file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $success = 'Şifreniz başarıyla güncellendi. <a href="login.php">Giriş yap</a>';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Şifre Belirle - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-bg">
    <div class="card login-card">
        <div class="card-header">
            <a href="login.php" class="btn btn-outline-light mb-2"><i class="bi bi-arrow-left"></i> Girişe Dön</a>
            <h5 class="mt-2 mb-0">Yeni Şifre Belirle</h5>
        </div>
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success"> <?= $success ?> </div>
            <?php else: ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Yeni Şifre</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary w-100">Şifreyi Güncelle</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 