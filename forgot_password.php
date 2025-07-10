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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
    .reset-card {
      border-radius: 32px;
      box-shadow: 0 4px 24px rgba(0,92,169,0.13);
      border: none;
      transition: box-shadow 0.22s, transform 0.16s, border-radius 0.22s;
      background: #fff;
      max-width: 400px;
      margin: 0 auto;
      overflow: hidden;
    }
    .reset-card:hover {
      box-shadow: 0 12px 36px rgba(0,92,169,0.18);
      transform: translateY(-6px) scale(1.035);
      border-radius: 48px;
    }
    body.dark-mode .reset-card {
      background: #232a3a;
      color: #e3e3e3;
      border: none;
    }
    body.dark-mode .reset-card:hover {
      box-shadow: 0 12px 36px rgba(13,110,253,0.18);
    }
    .reset-card .card-body, .reset-card .card-header {
      padding-left: 2rem;
      padding-right: 2rem;
    }
    </style>
</head>
<body class="login-bg d-flex align-items-center justify-content-center" style="min-height:100vh;">
  <div class="container">
    <div class="row justify-content-center align-items-center" style="min-height:100vh;">
      <div class="col-12 col-sm-8 col-md-6 col-lg-5 col-xl-4">
        <a href="login.php" class="btn btn-outline-primary mb-3 d-inline-flex align-items-center" style="position:relative; z-index:2;"><i class="bi bi-arrow-left me-1"></i> Geri</a>
        <div class="reset-card shadow-sm">
          <div class="card-header text-center bg-white border-0 d-flex flex-column align-items-center">
            <a href="index.php">
              <img src="https://upload.wikimedia.org/wikipedia/tr/d/dc/Akdeniz_%C3%9Cniversitesi_logosu.IMG_0838.png" class="akdeniz-logo mb-2" alt="Akdeniz Üniversitesi" style="width: 80px; height: 80px; object-fit: contain;">
            </a>
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
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  // Dark mode localStorage'dan okunmaya devam etsin
  if (localStorage.getItem('darkMode') === '1') document.body.classList.add('dark-mode');
  </script>
</body>
</html> 