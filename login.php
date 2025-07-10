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
<body class="login-bg d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <div class="container">
      <div class="row justify-content-center align-items-center" style="min-height:100vh;">
        <div class="col-12 col-sm-8 col-md-6 col-lg-5 col-xl-4">
          <div class="card login-card shadow-sm" style="max-width: 420px; margin: 0 auto;">
            <div class="card-header text-center bg-white border-0">
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
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Dark mode toggle (login sayfası için de ekle)
    if (localStorage.getItem('darkMode') === '1') document.body.classList.add('dark-mode');
    </script>
    <style>
.login-card {
  border-radius: 32px;
  box-shadow: 0 4px 24px rgba(0,92,169,0.13);
  border: 2px solid #e3f0fa;
  transition: box-shadow 0.22s, transform 0.16s, border-color 0.18s, border-radius 0.22s;
  background: #fff;
}
.login-card:hover {
  box-shadow: 0 12px 36px rgba(0,92,169,0.18);
  transform: translateY(-6px) scale(1.025);
  border-color: #0d6efd;
  border-radius: 48px;
}
body.dark-mode .login-card {
  background: #232a3a;
  color: #e3e3e3;
  border-color: #333;
}
body.dark-mode .login-card:hover {
  border-color: #0d6efd;
  box-shadow: 0 12px 36px rgba(13,110,253,0.18);
}
</style>
</body>
</html> 