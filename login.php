<?php
session_start();
$error = '';

// SQL Server connection settings
$serverName = "LAPTOP-KK1SQ6AD\\SQLEXPRESS";
$connectionOptions = [
    "Database" => "ArizaBildirimSistemi",
    "Uid" => "phpuser",
    "PWD" => "serverdata123"
];

try {
    $conn = new PDO(
        "sqlsrv:Server=$serverName;Database=" . $connectionOptions["Database"],
        $connectionOptions["Uid"],
        $connectionOptions["PWD"]
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $sql = "SELECT u.*, r.name AS role, d.name AS department
                FROM Users u
                LEFT JOIN Roles r ON u.role_id = r.id
                LEFT JOIN Departments d ON u.department_id = d.id
                WHERE u.username = :username";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['password'] === $password) { // Use password_verify() if hashed
            $_SESSION['user'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['department'] = $user['department'];
            // Update last_login
            $update = $conn->prepare("UPDATE Users SET last_login = GETDATE() WHERE id = :id");
            $update->execute(['id' => $user['id']]);
            // Redirect by role
            if ($user['role'] === 'MainAdmin') {
                header('Location: main_admin.php');
            } elseif ($user['role'] === 'Admin' || $user['role'] === 'AltAdmin') {
                header('Location: admin.php');
            } elseif ($user['role'] === 'TeknikPersonel') {
                header('Location: teknik_personel.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $error = 'Kullanıcı adı veya şifre hatalı.';
            // Debug output
            if ($user) {
                echo "User found, but password mismatch. DB password: " . $user['password'] . " Input: " . $password;
            } else {
                echo "No user found for username: " . $username;
            }
            exit;
        }
    }
} catch (PDOException $e) {
    $error = "Veritabanı bağlantı hatası: " . $e->getMessage();
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
                        <label class="form-label" for="username">Kullanıcı Adı</label>
                        <input type="text" name="username" id="username" class="form-control" required autofocus placeholder="kullanici">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Şifre</label>
                        <input type="password" name="password" id="password" class="form-control" required>
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
  border: none;
  transition: box-shadow 0.22s, transform 0.16s, border-radius 0.22s;
  background: #fff;
}
.login-card:hover {
  box-shadow: 0 12px 36px rgba(0,92,169,0.18);
  transform: translateY(-6px) scale(1.035);
  border-radius: 48px;
}
body.dark-mode .login-card {
  background: #232a3a;
  color: #e3e3e3;
  border: none;
}
body.dark-mode .login-card:hover {
  box-shadow: 0 12px 36px rgba(13,110,253,0.18);
}
</style>
</body>
</html> 