<?php
$serverName = "LAPTOP-KK1SQ6AD\\SQLEXPRESS"; // Double backslash for PHP
$connectionOptions = [
    "Database" => "master",
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
    echo "✅ MSSQL bağlantısı başarılı!";
} catch (PDOException $e) {
    echo "❌ Bağlantı hatası: " . $e->getMessage();
}
?>
