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
        }
    }
} catch (PDOException $e) {
    $error = "Veritabanı bağlantı hatası: " . $e->getMessage();
}
?>
<!-- (HTML form and rest of your page stays the same) --> 