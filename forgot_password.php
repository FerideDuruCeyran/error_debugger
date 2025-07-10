<?php
$usersFile = 'users.json';
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $found = null;
    foreach ($users as &$u) {
        if ($u['username'] === $username && $u['email'] === $email) {
            $found = &$u;
            break;
        }
    }
    if ($found) {
        $token = bin2hex(random_bytes(16));
        $found['reset_token'] = $token;
        file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        // Simüle: Şifre sıfırlama linkini ekrana/loga yaz
        $resetLink = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/reset_password.php?token=' . $token;
        $success = 'Şifre sıfırlama bağlantınız: <a href="' . htmlspecialchars($resetLink) . '">' . htmlspecialchars($resetLink) . '</a> (Gerçek sistemde bu bağlantı e-posta ile gönderilecektir)';
    } else {
        $error = 'Kullanıcı adı ve e-posta eşleşmiyor.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Şifre Sıfırlama - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-bg">
    <div class="card login-card">
        <div class="card-header">
            <a href="login.php" class="btn btn-outline-light mb-2"><i class="bi bi-arrow-left"></i> Girişe Dön</a>
            <h5 class="mt-2 mb-0">Şifre Sıfırlama</h5>
        </div>
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success"> <?= $success ?> </div>
            <?php else: ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">E-posta</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary w-100">Sıfırlama Bağlantısı Gönder</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 