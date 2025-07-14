<?php
require_once 'config.php';

function log_error($message) {
    $log_entry = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
    file_put_contents(ERROR_LOG_FILE, $log_entry, FILE_APPEND);
}

function log_problem($entry) {
    $log_entry = json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents(PROBLEM_LOG_FILE, $log_entry, FILE_APPEND);
}

// problems.log dosyasını temizler: Aynı trackingNo'ya sahip birden fazla kayıt varsa sadece en sonuncusu kalır.
// Ayrıca status'u boş olanları 'Bekliyor' yapar.

$logFile = 'problems.log';
if (!file_exists($logFile)) {
    die('problems.log bulunamadı!');
}
$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$latest = [];
foreach ($lines as $line) {
    $entry = json_decode($line, true);
    if ($entry && isset($entry['trackingNo'])) {
        // Status boşsa düzelt
        if (!isset($entry['status']) || trim($entry['status']) === '') {
            $entry['status'] = 'Bekliyor';
        }
        $latest[$entry['trackingNo']] = $entry; // Aynı trackingNo varsa sonuncusu kalır
    }
}
// Temizlenmiş logu tekrar yaz
file_put_contents($logFile, "");
foreach ($latest as $entry) {
    file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
} 