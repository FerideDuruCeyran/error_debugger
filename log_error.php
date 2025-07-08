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