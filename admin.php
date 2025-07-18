<?php
session_start();
require_once 'config.php';
$usersFile = 'users.json';
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
$currentUser = null;
foreach ($users as $u) {
    if ($u['username'] === ($_SESSION['user'] ?? '')) {
        $currentUser = $u;
        break;
    }
}
if (!$currentUser || ($currentUser['role'] !== 'Admin' && $currentUser['role'] !== 'AltAdmin')) {
    header('Location: login.php');
    exit;
}
// --- GÜNCELLEME İŞLEMİ EN BAŞA ALINDI ---
if (isset($_POST['update_trackingNo'], $_POST['update_status'])) {
    $trackingNo = $_POST['update_trackingNo'];
    $newStatus = $_POST['update_status'];
    $newTech = $_POST['update_technician'] ?? '';
    $newMessage = $_POST['update_message'] ?? '';
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $i => $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $trackingNo) {
                $entry['status'] = $newStatus;
                $entry['assignedTo'] = $newTech;
                $entry['message'] = $newMessage;
                // Bildirim: Mesaj varsa, ilgili kişilere gönder
                if (!empty($newMessage)) {
                    if (!empty($entry['assignedTo'])) {
                        addNotification($entry['assignedTo'], 'Takip No: ' . $trackingNo . ' için yeni mesaj: ' . $newMessage);
                    }
                    if (!empty($entry['username'])) {
                        addNotification($entry['username'], 'Takip No: ' . $trackingNo . ' için yeni mesaj: ' . $newMessage);
                    }
                }
                $lines[$i] = json_encode($entry, JSON_UNESCAPED_UNICODE);
                break;
            }
        }
        file_put_contents(PROBLEM_LOG_FILE, implode("\n", $lines) . "\n");
        header('Location: admin.php');
        exit;
    }
}
// Arıza silme işlemi (sadece MainAdmin için)
if (isset($_POST['delete_fault'], $_POST['delete_trackingNo']) && $currentUser && $currentUser['role'] === 'MainAdmin') {
    $deleteTrackingNo = $_POST['delete_trackingNo'];
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $newLines = [];
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $deleteTrackingNo) {
                // Silinecek, ekleme!
                continue;
            }
            $newLines[] = $line;
        }
        file_put_contents(PROBLEM_LOG_FILE, implode("\n", $newLines) . "\n");
        $successMsg = 'Arıza başarıyla silindi.';
    }
}

$date1 = $_GET['date1'] ?? '';
$department = $_GET['department'] ?? '';
$status = $_GET['status'] ?? '';

$reports = [];
if (file_exists(PROBLEM_LOG_FILE)) {
    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry) {
            $match = true;
            if ($date1) {
                $entryDate = date('Y-m-d', strtotime($entry['date'] ?? ''));
                if ($entryDate !== $date1) $match = false;
            }
            if ($department && $entry['department'] !== $department) $match = false;
            if ($status && $entry['status'] !== $status) $match = false;
            if ($match) $reports[] = $entry;
        }
    }
}
// Arıza listeleme: Sadece kendi departmanındaki arızalar (Admin/AltAdmin), MainAdmin ise tüm arızalar
$problems = $reports;
if ($currentUser['role'] === 'Admin' || $currentUser['role'] === 'AltAdmin') {
    $problems = array_filter($problems, function($p) use ($currentUser) {
        return isset($p['department']) && $p['department'] === $currentUser['department'];
    });
}

$filter = $_GET['filter'] ?? '';
$updateMsg = '';
$successMsg = $errorMsg = '';

// Bildirim işlemleri için fonksiyon
function addNotification($user, $msg) {
    $file = 'bildirimler/notifications_' . $user . '.json';
    if (!is_dir('bildirimler')) mkdir('bildirimler');
    $list = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $list[] = [ 'msg' => $msg, 'date' => date('Y-m-d H:i') ];
    file_put_contents($file, json_encode($list, JSON_UNESCAPED_UNICODE));
}
// Durum güncelleme
if (isset($_POST['update_status'])) {
    $trackingNo = $_POST['trackingNo'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';
    if ($trackingNo && $newStatus) {
        // updateFaultStatus($trackingNo, $newStatus);
        $successMsg = 'Durum başarıyla güncellendi.';
        // Bildirim: Arıza sahibine
        if (file_exists(PROBLEM_LOG_FILE)) {
            $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $trackingNo) {
                    if (!empty($entry['username'])) {
                        addNotification($entry['username'], 'Arızanızın durumu "' . $newStatus . '" olarak güncellendi.');
                    }
                    break;
                }
            }
        }
    } else {
        $errorMsg = 'Bir hata oluştu. Lütfen tekrar deneyin.';
    }
}
// Teknisyen atama işlemi (sadece altadmin için)
if (($currentUser['role'] === 'AltAdmin' || $currentUser['role'] === 'Admin') && isset($_POST['assign_tech'], $_POST['assign_tracking'], $_POST['assign_personnel'])) {
    $assignTracking = trim($_POST['assign_tracking']);
    $assignPersonnel = trim($_POST['assign_personnel']);
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $i => $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $assignTracking) {
                $entry['assignedTo'] = $assignPersonnel;
                $entry['assigned'] = $assignPersonnel; // eski kodlarla uyum için
                $entry['assignedBy'] = $currentUser['username']; // Admin/Altadmin kaydı
                $lines[$i] = json_encode($entry, JSON_UNESCAPED_UNICODE);
                // Bildirim: Teknisyene
                addNotification($assignPersonnel, 'Yeni bir arıza size atandı (Takip No: ' . $assignTracking . ').');
                break;
            }
        }
        file_put_contents(PROBLEM_LOG_FILE, implode("\n", $lines) . "\n");
    }
}

// Arıza durumu onaylama (sadece teknikpersonel için)
if ($_SESSION['user'] === 'teknikpersonel' && isset($_POST['confirm_status'], $_POST['confirm_tracking'])) {
    $confirmTracking = trim($_POST['confirm_tracking']);
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $i => $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $confirmTracking && isset($entry['assignedTo']) && $entry['assignedTo'] === $_SESSION['user']) {
                $entry['status'] = 'Onaylandı';
                $lines[$i] = json_encode($entry, JSON_UNESCAPED_UNICODE);
                break;
            }
        }
        file_put_contents(PROBLEM_LOG_FILE, implode("\n", $lines) . "\n");
    }
}

// Listele
// $problems = [];
// if (file_exists(PROBLEM_LOG_FILE)) {
//     $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//     foreach ($lines as $line) {
//         $entry = json_decode($line, true);
//         if ($entry) {
//             if ($filter === '' || ($entry['status'] ?? '') === $filter) {
//                 $problems[] = $entry;
//             }
//         }
//     }
// }
$page = 'admin';

// Bildirim gösterimi
$notification = '';
$notifFile = 'bildirimler/notifications_' . $_SESSION['user'] . '.json';
if (file_exists($notifFile)) {
    $notifs = json_decode(file_get_contents($notifFile), true);
    if (!empty($notifs)) {
        $notification = $notifs[0]['msg'];
        // Bildirimi gösterdikten sonra sil
        array_shift($notifs);
        file_put_contents($notifFile, json_encode($notifs, JSON_UNESCAPED_UNICODE));
    }
}

// Giriş yapan altadminin departmanını bul
$altadmin_department = $currentUser['department'] ?? null;
// Sadece kendi departmanındaki teknisyenleri listele
$teknisyenler = array_filter($users, function($u) use ($altadmin_department) {
    return $u['role'] === 'TeknikPersonel' && $u['department'] === $altadmin_department;
});
// Atama formunda sadece $teknisyenler kullanılacak

// Ortak arıza durumları
$faultStatuses = [
    'Bekliyor' => 'Bekliyor',
    'Onaylandı' => 'Onaylandı',
    'Tamamlandı' => 'Tamamlandı'
];

// Arıza türleri
$faultTypes = [
    1 => "MAKİNE/TESİSAT",
    2 => "ELEKTRİK",
    3 => "İNŞAAT"
];
function getFaultTypeName($id, $faultTypes) {
    return $faultTypes[$id] ?? $id;
}

// Add sub-fault types array for lookup
$subFaultTypes = [
    1 => "Temiz Su Sistemi",
    2 => "Pis Su Sistemi",
    3 => "Buhar Sistemi",
    4 => "Yangın Sistemi",
    5 => "Klima Sistemi",
    6 => "Havalandırma",
    7 => "Makine/Teknik",
    8 => "Yangın Algılama",
    9 => "Aydınlatma",
    10 => "Enerji Dağıtım",
    11 => "Enerji Kaynağı",
    12 => "Kampüs Aydınlatma",
    13 => "Elektrik Raporu",
    14 => "Çatı/Duvar",
    15 => "Boya",
    16 => "Kapı/Pencere",
    17 => "Zemin Kaplama",
    18 => "Kaynak/Montaj",
    19 => "Nem ve Küf",
    20 => "İnşaat Raporu"
];

// İstatistikler için değişkenler
$totalFaults = 0;
$statusCounts = ['Bekliyor'=>0, 'Onaylandı'=>0, 'Tamamlandı'=>0];
$lastMonthCount = 0;
$departmentCounts = [];
$typeCounts = [];
$mostReportedDepartment = '-';
$mostReportedType = '-';
$mostReportedDepartmentCount = 0;
$mostReportedTypeCount = 0;
$now = time();
if (file_exists(PROBLEM_LOG_FILE)) {
    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry) {
            $totalFaults++;
            $status = $entry['status'] ?? '-';
            if (isset($statusCounts[$status])) $statusCounts[$status]++;
            $date = isset($entry['date']) ? strtotime($entry['date']) : false;
            if ($date && ($now - $date) <= 31*24*60*60) $lastMonthCount++;
            $dep = $entry['department'] ?? '-';
            $departmentCounts[$dep] = ($departmentCounts[$dep] ?? 0) + 1;
            $type = $entry['faultType'] ?? '-';
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
        }
    }
    // En çok arıza bildiren birim
    if ($departmentCounts) {
        arsort($departmentCounts);
        $mostReportedDepartment = array_key_first($departmentCounts);
        $mostReportedDepartmentCount = $departmentCounts[$mostReportedDepartment];
    }
    // En çok görülen arıza türü
    if ($typeCounts) {
        arsort($typeCounts);
        $mostReportedType = array_key_first($typeCounts);
        $mostReportedTypeCount = $typeCounts[$mostReportedType];
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Cardbox için CSS ekle -->
    <style>
    .detail-cardbox {
      position: absolute;
      z-index: 9999;
      min-width: 320px;
      max-width: 400px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.18);
      padding: 1rem 1.2rem;
      border: 1px solid #e3e3e3;
      top: 40px;
      left: 0;
      display: none;
      font-size: 0.9rem;
      animation: fadeIn 0.2s ease-in-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }
    @media (max-width: 768px) {
      .detail-cardbox {
        min-width: 280px;
        max-width: 90vw;
        left: 5vw !important;
        right: 5vw;
        position: fixed;
        top: 50% !important;
        transform: translateY(-50%);
      }
    }
    .detail-cardbox .close-btn {
      position: absolute;
      top: 8px;
      right: 12px;
      background: none;
      border: none;
      font-size: 1.2rem;
      color: #888;
      cursor: pointer;
      transition: color 0.2s;
    }
    .detail-cardbox .close-btn:hover {
      color: #dc3545;
    }
    /* Dark mode için cardbox */
    body.dark-mode .detail-cardbox {
      background: #2d3748;
      color: #e2e8f0;
      border-color: #4a5568;
    }
    body.dark-mode .detail-cardbox .close-btn {
      color: #a0aec0;
    }
    body.dark-mode .detail-cardbox .close-btn:hover {
      color: #fc8181;
    }
    .table.detail-table { position: relative; }
    /* Tablo güzelleştirme */
    .table-striped > tbody > tr:nth-of-type(odd) {
      background-color: #f8f9fa;
    }
    .table-hover tbody tr:hover {
      background-color: #e3f0fa;
      transition: background 0.2s;
    }
    .table th {
      background: linear-gradient(135deg, #0d6efd, #0b5ed7);
      color: white;
      font-weight: 600;
      border: none;
      padding: 12px 8px;
    }
    .table td {
      padding: 12px 8px;
      vertical-align: middle;
    }
    .badge {
      font-size: 0.85em;
      padding: 0.4em 0.7em;
      border-radius: 0.6em;
      font-weight: 500;
    }
    .btn-group .btn {
      border-radius: 0.5em;
      margin: 0 1px;
    }
    .btn-primary, .btn-outline-info, .btn-danger, .btn-success {
      transition: all 0.2s ease;
    }
    .btn-primary:hover, .btn-outline-info:hover, .btn-danger:hover, .btn-success:hover {
      box-shadow: 0 3px 10px rgba(0,0,0,0.15);
      transform: translateY(-1px);
    }
    .form-select, .form-control {
      border-radius: 0.7em;
      border: 1.5px solid #b6d4fe;
      transition: all 0.2s ease;
    }
    .form-select:focus, .form-control:focus {
      border-color: #0d6efd;
      box-shadow: 0 0 0 0.15rem rgba(13,110,253,.15);
      transform: translateY(-1px);
    }
    .card {
      border-radius: 12px;
      border: none;
      transition: all 0.2s ease;
    }
    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
    }
    /* Karanlık mod uyumu */
    body.dark-mode .table-striped > tbody > tr:nth-of-type(odd) {
      background-color: #23272b;
    }
    body.dark-mode .table-hover tbody tr:hover {
      background-color: #1a1d20;
    }
    body.dark-mode .form-select, body.dark-mode .form-control {
      background: #23272b;
      color: #fff;
      border-color: #495057;
    }
    body.dark-mode .form-select:focus, body.dark-mode .form-control:focus {
      border-color: #0d6efd;
      box-shadow: 0 0 0 0.15rem rgba(13,110,253,.25);
    }
    /* Navbar'da dark mode butonunu daha görünür ve yuvarlak yap */
    .navbar .btn-icon#darkModeToggle {
      background: #fff2;
      border: none;
      color: #fff;
      font-size: 1.4em;
      margin-left: 8px;
      margin-right: 2px;
      transition: color 0.2s, background 0.2s;
      border-radius: 50%;
      padding: 6px 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      position: relative;
    }
    .navbar .btn-icon#darkModeToggle:hover, .navbar .btn-icon#darkModeToggle:focus {
      background: #fff4;
      color: #ffd700;
    }
    @media (max-width: 600px) {
      #floatingDarkToggle {
        display: block !important;
      }
    }
    #floatingDarkToggle {
      display: none;
      position: fixed;
      bottom: 18px;
      right: 18px;
      z-index: 9999;
      background: #232a3a;
      color: #ffd700;
      border-radius: 50%;
      width: 48px;
      height: 48px;
      font-size: 2em;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 12px rgba(0,0,0,0.18);
      border: 2px solid #ffd700;
      cursor: pointer;
    }
    #floatingDarkToggle:hover { background: #0d6efd; color: #fff; border-color: #fff; }
    
    /* Arıza seçimi için özel stiller */
    #assignTrackingSelect option {
      padding: 8px;
      border-bottom: 1px solid #eee;
    }
    #assignTrackingSelect option:checked {
      background: linear-gradient(135deg, #0d6efd, #0b5ed7);
      color: white;
    }
    #selectedFaultInfo {
      margin-top: 5px;
      padding: 8px;
      background: #f8f9fa;
      border-radius: 6px;
      border-left: 3px solid #0d6efd;
    }
    body.dark-mode #selectedFaultInfo {
      background: #2d3748;
      color: #e2e8f0;
      border-left-color: #4299e1;
    }
    .assign-row { gap: 0.5rem; }
    @media (min-width: 768px) {
      .assign-row { flex-wrap: nowrap !important; }
      .assign-row > div { margin-bottom: 0 !important; }
    }
    .card-body.assign-tech-body {
      padding: 1.2rem 1.2rem 0.7rem 1.2rem;
    }
    @media (max-width: 991.98px) {
      .card-body.assign-tech-body {
        min-width: 100% !important;
        max-width: 100% !important;
      }
    }
    #selectedFaultInfo {
      margin-top: 4px;
      padding: 8px 12px;
      background: #232a3a;
      color: #e2e8f0;
      border-radius: 6px;
      border-left: 3px solid #0d6efd;
      font-size: 0.97em;
      min-height: 28px;
    }
    body:not(.dark-mode) #selectedFaultInfo {
      background: #f8f9fa;
      color: #222;
      border-left-color: #0d6efd;
    }
    .status-editable {
      cursor: pointer;
      display: inline-block;
      vertical-align: middle;
    }
    .status-editable .badge {
      font-size: 1.08em;
      padding: 0.55em 1.1em 0.55em 1.1em;
      border-radius: 1.2em;
      font-weight: 600;
      box-shadow: 0 2px 8px rgba(13,110,253,0.08);
      letter-spacing: 0.01em;
      display: inline-flex;
      align-items: center;
      gap: 0.4em;
      transition: background 0.18s, color 0.18s;
    }
    .status-editable .edit-icon {
      font-size: 1.08em;
      color: #0d6efd;
      opacity: 0.55;
      margin-left: 0.18em;
      transition: color 0.18s, opacity 0.18s;
      vertical-align: middle;
    }
    .status-editable:hover .edit-icon {
      color: #005ca9;
      opacity: 1;
    }
    </style>
    <style>
    html, body {
      background: #181a1b !important;
      color: #eee;
      transition: none !important;
    }
  </style>
  <script>
    (function() {
      try {
        var userPref = localStorage.getItem('darkMode');
        if (userPref === '1') {
          document.documentElement.classList.add('dark-mode');
        } else if (userPref === null && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
          document.documentElement.classList.add('dark-mode');
        }
      } catch(e){}
    })();
  </script>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="uploads/Akdeniz_Üniversitesi_logosu.IMG_0838.png" class="akdeniz-logo" alt="Akdeniz Üniversitesi" style="width:56px;height:56px;border-radius:50%;background:#fff;">
      <span>Akdeniz Üniversitesi</span>
    </a>
    <div class="d-flex ms-auto align-items-center gap-2">
      <span class="badge bg-light text-primary me-2">
        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($currentUser['username'] ?? 'Misafir') ?>
        <span class="badge bg-secondary ms-1"><?= htmlspecialchars($currentUser['role'] ?? '') ?></span>
      </span>
      <button class="btn-icon" id="darkModeToggle" title="Karanlık Modu Aç/Kapat" aria-label="Karanlık Mod"><i class="bi bi-moon"></i></button>
      <a class="btn-icon" id="notifBtn" title="Bildirimler" data-bs-toggle="modal" data-bs-target="#notifModal"><i class="bi bi-bell"></i></a>
      <a class="btn-icon" id="helpBtn" title="Yardım" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="bi bi-question-circle"></i></a>
      <a class="btn btn-outline-light" href="index.php"><i class="bi bi-house"></i> Ana Sayfa</a>
      <a class="btn btn-outline-light" href="messages.php"><i class="bi bi-chat-dots"></i> Mesajlar</a>
      <a class="btn btn-outline-light" href="logout.php"><i class="bi bi-box-arrow-right"></i> Çıkış</a>
    </div>
  </div>
</nav>
<div class="container mb-4">
  <div class="row align-items-center mb-3">
    <div class="col-md-8">
      <h2 class="mb-0"><i class="bi bi-gear-fill text-primary"></i> Admin Paneli</h2>
    </div>
    <div class="col-md-4 text-md-end mt-2 mt-md-0">
      <ul class="nav nav-tabs justify-content-end" style="border-bottom: 2px solid #e3f0fa;">
        <li class="nav-item"><a class="nav-link<?= $page=='admin'?' active':'' ?>" href="admin.php"><i class="bi bi-list-task"></i> Arıza Listesi</a></li>
        <li class="nav-item"><a class="nav-link<?= $page=='assigned'?' active':'' ?>" href="?page=assigned"><i class="bi bi-person-lines-fill"></i> Atanan İşler</a></li>
      </ul>
    </div>
  </div>
  <!-- Modern Dashboard Kartları kaldırıldı, sadece klasik başlık ve filtre kalacak -->
</div>

<?php
// --- Mesajlaşma için gerekli kodlar (messages.php'den alınanlar) ---
$usersFile = 'users.json';
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
$currentUser = null;
foreach ($users as $u) {
    if ($u['username'] === ($_SESSION['user'] ?? '')) {
        $currentUser = $u;
        break;
    }
}
$role = $currentUser['role'] ?? '';
$allUsers = array_column($users, 'username');
$messagesFile = 'messages.json';
if (!file_exists($messagesFile)) file_put_contents($messagesFile, '[]');
$messages = json_decode(file_get_contents($messagesFile), true);
$toList = [];
if ($role === 'Admin') {
    foreach ($users as $u) {
        if ($u['role'] === 'TeknikPersonel' && $u['department'] === $currentUser['department']) {
            $toList[] = $u;
        }
    }
    foreach ($users as $u) {
        if ($u['role'] === 'Admin' && $u['username'] !== $currentUser['username']) {
            $toList[] = $u;
        }
    }
    foreach ($users as $u) {
        if (in_array($u['role'], ['MainAdmin', 'GenelAdmin'])) {
            $toList[] = $u;
        }
    }
}
// Sohbet edilen kullanıcıları bul
$myUsername = $currentUser['username'] ?? '';
$chattedUsers = [];
foreach ($messages as $msg) {
    if ($msg['from'] === $myUsername && $msg['to'] !== $myUsername) {
        $chattedUsers[$msg['to']] = true;
    }
    if ($msg['to'] === $myUsername && $msg['from'] !== $myUsername) {
        $chattedUsers[$msg['from']] = true;
    }
}
$chattedUserObjs = [];
foreach ($users as $u) {
    if (isset($chattedUsers[$u['username']])) {
        $chattedUserObjs[] = $u;
    }
}
$activeUser = $_GET['user'] ?? ($chattedUserObjs[0]['username'] ?? null);
$chatHistory = [];
if ($activeUser) {
    foreach ($messages as $m) {
        if (($m['from'] === $currentUser['username'] && $m['to'] === $activeUser) ||
            ($m['from'] === $activeUser && $m['to'] === $currentUser['username'])) {
            $chatHistory[] = $m;
        }
    }
}
?>
<!-- ADMIN MESAJLAŞMA ALANI BAŞLANGIÇ -->
<!-- Bu arayüzü ve ilgili PHP kodlarını tamamen kaldır -->
<!-- ADMIN MESAJLAŞMA ALANI BAŞLANGIÇ -->
<div class="container">
    <form method="get" class="row g-3 mb-4" id="filterForm">
        <div class="col-md-2">
            <label class="form-label" for="date1">Tarih</label>
            <input type="date" name="date1" id="date1" class="form-control" value="<?= htmlspecialchars($date1) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="department">Birim</label>
            <select name="department" id="department" class="form-select">
                <option value="">Tümü</option>
                <?php
                $departments = [
                    "Diş Hekimliği Fakültesi", "Eczacılık Fakültesi", "Edebiyat Fakültesi", "Eğitim Fakültesi", "Fen Fakültesi", "Güzel Sanatlar Fakültesi", "Hemşirelik Fakültesi", "Hukuk Fakültesi", "İktisadi ve İdari Bilimler Fakültesi", "İlahiyat Fakültesi", "İletişim Fakültesi", "Kemer Denizcilik Fakültesi", "Kumluca Sağlık Bilimleri Fakültesi", "Hukuk Müşavirliği", "Ziraat Fakültesi", "Adalet Meslek Yüksekokulu", "Alanya Meslek Yüksekokulu", "Demre Dr. Hasan Ünal Meslek Yüksekokulu", "Elmalı Meslek Yüksekokulu", "Finike Meslek Yüksekokulu", "Gastronomi ve Mutfak Sanatları Meslek Yüksekokulu", "Korkuteli Meslek Yüksekokulu", "Kumluca Meslek Yüksekokulu", "Manavgat Meslek Yüksekokulu", "Serik Meslek Yüksekokulu", "Sosyal Bilimler Meslek Yüksekokulu", "Teknik Bilimler Meslek Yüksekokulu", "Turizm İşletmeciliği ve Otelcilik Yüksekokulu", "Antalya Devlet Konservatuvarı", "Yabancı Diller Yüksekokulu", "Akdeniz Uygarlıkları Araştırma Enstitüsü", "Eğitim Bilimleri Enstitüsü", "Fen Bilimleri Enstitüsü", "Güzel Sanatlar Enstitüsü", "Prof.Dr.Tuncer Karpuzoğlu Organ Nakli Enstitüsü", "Sağlık Bilimleri Enstitüsü", "Sosyal Bilimler Enstitüsü", "Atatürk İlkeleri ve İnkılap Tarihi Bölüm Başkanlığı", "Beden Eğitimi ve Spor Bölüm Başkanlığı", "Enformatik Bölüm Başkanlığı", "Güzel Sanatlar Bölüm Başkanlığı", "Türk Dili Bölüm Başkanlığı", "Hukuk Müşavirliği", "Kütüphane ve Dokümantasyon Daire Başkanlığı", "Öğrenci İşleri Daire Başkanlığı", "Sağlık Kültür ve Spor Daire Başkanlığı", "Strateji Geliştirme Daire Başkanlığı", "Uluslararası İlişkiler Ofisi", "Yapı İşleri ve Teknik Daire Başkanlığı", "Basın Yayın ve Halkla İlişkiler Müdürlüğü", "Döner Sermaye İşletme Müdürlüğü", "Hastane", "İdari ve Mali İşler Daire Başkanlığı", "İnsan Kaynakları Daire Başkanlığı", "Kariyer Planlama ve Mezun İzleme Uygulama ve Araştırma Merkezi", "Kütüphane ve Dokümantasyon Daire Başkanlığı", "Öğrenci İşleri Daire Başkanlığı", "Sağlık Kültür ve Spor Daire Başkanlığı", "Strateji Geliştirme Daire Başkanlığı", "Teknoloji Transfer Ofisi", "TÖMER", "Yabancı Diller Yüksekokulu", "Diğer (liste dışı birim)"
                ];
                foreach ($departments as $dep): ?>
                    <option value="<?= htmlspecialchars($dep) ?>" <?= $department === $dep ? 'selected' : '' ?>><?= htmlspecialchars($dep) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="status">Durum</label>
            <select name="status" id="status" class="form-select">
<?php foreach ($faultStatuses as $key => $label): ?>
<option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 align-self-end d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100">Filtrele</button>
            <button type="button" class="btn btn-secondary w-100" onclick="resetFilters()">Filtreyi Sıfırla</button>
        </div>
    </form>
    <script>
    function resetFilters() {
        window.location.href = window.location.pathname;
    }
    </script>
    <div class="row mb-4">
        <div class="col-md-6">
            <form method="get" class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 fw-bold">
                    <i class="bi bi-funnel"></i> Duruma Göre Filtrele:
                </label>
                <select name="filter" class="form-select w-auto" onchange="this.form.submit()">
                <option value="" <?= $filter === '' ? 'selected' : '' ?>>Tümü</option>
    <?php foreach ($faultStatuses as $key => $label): ?>
        <option value="<?= $key ?>" <?= $filter === $key ? 'selected' : '' ?>><?= $label ?></option>
    <?php endforeach; ?>
            </select>
    </form>
        </div>
        <div class="col-md-6 text-md-end">
            <span class="badge bg-primary fs-6">
                <i class="bi bi-list-ul"></i> Toplam: <?= count($problems) ?> Arıza
            </span>
        </div>
    </div>
    <?php if ($updateMsg): ?>
        <div class="alert alert-success"> <?= htmlspecialchars($updateMsg) ?> </div>
    <?php endif; ?>
    <?php if ($successMsg): ?>
<div class="toast-container position-fixed top-0 end-0 p-3">
  <div class="toast align-items-center text-bg-success border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">
        <?= $successMsg ?>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="toast-container position-fixed top-0 end-0 p-3">
  <div class="toast align-items-center text-bg-danger border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">
        <?= $errorMsg ?>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<?php endif; ?>
    <?php if (isset($_GET['page']) && $_GET['page'] === 'assigned' && ($currentUser['role'] === 'Admin' || $currentUser['role'] === 'AltAdmin')): ?>
<h3>Atanan İşler</h3>
<table class="table table-bordered table-striped align-middle mb-4">
<thead class="table-primary">
<tr><th>Takip No</th><th>Açıklama</th><th>Durum</th><th>Tarih</th></tr>
</thead>
<tbody>
<?php
if (file_exists(PROBLEM_LOG_FILE)) {
    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if (
            $entry &&
            isset($entry['assignedTo']) &&
            isset($entry['department']) &&
            $entry['department'] === $currentUser['department'] &&
            isset($users) &&
            in_array($entry['assignedTo'], array_column($teknisyenler, 'username'))
        ) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($entry['trackingNo']) . '</td>';
            echo '<td>' . htmlspecialchars(isset($entry['description']) ? $entry['description'] : '') . '</td>';
            echo '<td>' . htmlspecialchars($entry['status'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($entry['date'] ?? '-') . '</td>';
            echo '</tr>';
        }
    }
}
?>
</tbody>
</table>
<?php endif; ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="bi bi-person-plus"></i> Arıza Teknisyen Atama
                <span class="badge bg-light text-primary ms-2">
                    <?= count(array_filter($problems, function($p) { return empty($p['assignedTo'] ?? $p['assigned'] ?? ''); })) ?> Atanmamış
                </span>
            </h5>
        </div>
        <div class="card-body assign-tech-body" style="padding: 1.2rem 1.2rem 0.7rem 1.2rem;">
            <form method="post" class="w-100" id="assignTechForm">
                <input type="hidden" name="assign_tech" value="1">
                <div class="row g-3 align-items-center">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold mb-1"><i class="bi bi-hash"></i> Arıza Takip No</label>
                        <select name="assign_tracking" class="form-select form-select-lg mb-0" required id="assignTrackingSelect">
                            <option value="">Arıza seçin...</option>
                            <?php 
                            $unassignedProblems = array_filter($problems, function($p) {
                                return empty($p['assignedTo'] ?? $p['assigned'] ?? '');
                            });
                            if (empty($unassignedProblems)): ?>
                                <option value="" disabled>Tüm arızalar atanmış</option>
                            <?php else: ?>
                                <?php foreach ($unassignedProblems as $p): ?>
                                    <option value="<?= htmlspecialchars($p['trackingNo']) ?>" 
                                            data-department="<?= htmlspecialchars($p['department']) ?>"
                                            data-type="<?= htmlspecialchars($p['faultType']) ?>"
                                            data-date="<?= htmlspecialchars($p['date']) ?>">
                                        <?= htmlspecialchars($p['trackingNo']) ?> - <?= htmlspecialchars($p['department']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="w-100 d-block">
                            <small class="form-text" id="selectedFaultInfo"></small>
                        </div>
            </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-bold mb-1"><i class="bi bi-person-workspace"></i> Teknisyen</label>
                        <select name="assign_personnel" class="form-select form-select-lg mb-0" required>
                            <option value="">Teknisyen seçin...</option>
                    <?php foreach ($teknisyenler as $t): ?>
                        <option value="<?= htmlspecialchars($t['username']) ?>">
                            <?= htmlspecialchars($t['username']) ?><?php if (!empty($t['profession'])): ?> (<?= htmlspecialchars($t['profession']) ?>)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
                    <div class="col-12 col-md-3 d-grid">
                        <button type="submit" name="assign_tech" class="btn btn-success btn-lg h-100 w-100 mt-0">
                            <i class="bi bi-check-circle"></i> Ata
                        </button>
            </div>
        </div>
    </form>
        </div>
    </div>
    <div class="table-responsive">
    <table class="table table-bordered table-striped table-hover align-middle detail-table shadow-sm">
        <thead class="table-primary">
        <tr>
            <th><i class="bi bi-building"></i> Birim</th>
            <th class="text-center"><i class="bi bi-tag"></i> Tür</th>
            <th class="text-center"><i class="bi bi-tag"></i> Alt Tür</th>
            <th class="text-center"><i class="bi bi-hash"></i> Takip No</th>
            <th><i class="bi bi-card-text"></i> Açıklama</th>
            <th><i class="bi bi-telephone"></i> İletişim</th>
            <th class="text-center"><i class="bi bi-calendar"></i> Tarih</th>
            <th class="text-center" style="min-width: 200px;">Durum</th>
            <th><i class="bi bi-person-workspace"></i> Teknisyen</th>
            <th class="text-center"><i class="bi bi-pencil-square"></i> İşlemler</th>
            <?php if ($currentUser && $currentUser['role'] === 'MainAdmin'): ?>
                <th class="text-center"><i class="bi bi-trash"></i> Sil</th>
            <?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($problems as $p): ?>
    <?php 
    $assignedTech = '';
    if (isset($p['assignedTo']) && !empty($p['assignedTo'])) {
        $assignedTech = $p['assignedTo'];
    } elseif (isset($p['assigned']) && !empty($p['assigned'])) {
        $assignedTech = $p['assigned'];
    }
    ?>
    <tr>
        <td><i class="bi bi-building text-muted"></i> <?= htmlspecialchars($p['department']) ?></td>
        <td class="text-center">
  <?php if (isset($p['faultType']) && $p['faultType'] !== '-' && isset($faultTypes[$p['faultType']])): ?>
    <span class="badge bg-secondary"> <?= htmlspecialchars(getFaultTypeName($p['faultType'], $faultTypes)) ?> </span>
  <?php else: ?>
    <span class="badge bg-light text-muted"> - </span>
  <?php endif; ?>
</td>
        <td class="text-center">
  <?php $subId = $p['subFaultType'] ?? null; ?>
  <?php if ($subId && $subId !== '-' && isset($subFaultTypes[$subId])): ?>
    <span class="badge bg-info"> <?= htmlspecialchars($subFaultTypes[$subId]) ?> </span>
  <?php else: ?>
    <span class="badge bg-light text-muted"> - </span>
  <?php endif; ?>
</td>
        <td class="text-center fw-bold text-primary"><?= htmlspecialchars($p['trackingNo']) ?></td>
        <td>
  <?php 
    $desc = $p['description'] ?? $p['detailedDescription'] ?? $p['content'] ?? '';
    if (!empty($desc)) : ?>
    <span class="desc-hover" onclick="showDescPopup(`<?= htmlspecialchars(addslashes($desc)) ?>`)" title="<?= htmlspecialchars($desc) ?>">
      <?= htmlspecialchars(mb_strimwidth($desc, 0, 60, '...')) ?>
    </span>
  <?php endif; ?>
        </td>
        <td><i class="bi bi-telephone text-muted"></i> <?= htmlspecialchars($p['contact'] ?? '-') ?></td>
        <td class="text-center"><small><?= htmlspecialchars($p['date']) ?></small></td>
        <td class="text-center" style="min-width:200px; white-space:nowrap;">
            <?php 
            $statusClass = '';
            $statusIcon = '';
            switch($p['status']) {
                case 'Bekliyor':
                    $statusClass = 'bg-warning text-dark';
                    $statusIcon = 'bi-clock';
                    break;
                case 'Onaylandı':
                    $statusClass = 'bg-info text-white';
                    $statusIcon = 'bi-check-circle';
                    break;
                case 'Tamamlandı':
                    $statusClass = 'bg-success text-white';
                    $statusIcon = 'bi-check2-all';
                    break;
                default:
                    $statusClass = 'bg-secondary text-white';
                    $statusIcon = 'bi-question-circle';
            }
            ?>
            <span class="status-editable"
      onclick='openUpdatePopup(
        <?= json_encode($p["trackingNo"] ?? "") ?>,
        <?= json_encode($p["status"] ?? "Bekliyor") ?>,
        <?= json_encode($assignedTech ?? "") ?>,
        <?= json_encode($p["message"] ?? "") ?>
      )'
      title="Durumu Düzenle">
              <span class="badge <?= $statusClass ?>">
                <i class="bi <?= $statusIcon ?>"></i> <?= htmlspecialchars($p['status']) ?>
                <i class="bi bi-pencil ms-1 edit-icon"></i>
              </span>
            </span>
            <?php if (!empty($p['message'])): ?>
<span class="msg-icon-wrap position-relative" style="margin-left:10px;vertical-align:middle;">
  <i class="bi bi-chat-left-text-fill text-primary"
     style="font-size:1.2em;cursor:pointer;"
     data-bs-toggle="tooltip"
     data-bs-placement="top"
     title="<?= htmlspecialchars($p['message']) ?>"
     data-tracking="<?= htmlspecialchars($p['trackingNo']) ?>"
     onmouseenter="showMsgBubble(this, problemMessages[this.getAttribute('data-tracking')])"
     onmouseleave="msgBubbleTimeout = setTimeout(hideMsgBubble, 120);"
     onclick="toggleMsgBubble(this, problemMessages[this.getAttribute('data-tracking')])"
  ></i>
</span>
<?php endif; ?>
        </td>
        <td>
            <?php if (!empty($assignedTech)): ?>
                <span class="badge bg-primary">
                    <i class="bi bi-person-workspace"></i> <?= htmlspecialchars($assignedTech) ?>
                </span>
            <?php else: ?>
                <span class="text-muted"><small>Atanmamış</small></span>
            <?php endif; ?>
        </td>
        <td class="text-center">
            <div class="btn-group" role="group">
            <!-- Kalem butonunu kaldırdık, sadece detay butonu kaldı -->
            <button type="button" class="btn btn-outline-info btn-sm detail-btn" 
                onclick="openDetailCardbox('<?= htmlspecialchars($p['trackingNo']) ?>', this, event)"
                title="Detaylı İncele"
            >
                <i class="bi bi-search"></i>
            </button>
            </div>
                </td>
                <?php if ($currentUser && $currentUser['role'] === 'MainAdmin'): ?>
        <td class="text-center">
                    <form method="post" onsubmit="return confirm('Bu arızayı silmek istediğinize emin misiniz?');" style="display:inline-block">
                        <input type="hidden" name="delete_trackingNo" value="<?= htmlspecialchars($p['trackingNo']) ?>">
                <button type="submit" name="delete_fault" class="btn btn-danger btn-sm" title="Sil">
                    <i class="bi bi-trash"></i>
                </button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Popup ve Overlay -->
    <!-- Durum Düzenleme Popup -->
    <div id="updateOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:9998;" onclick="closeUpdatePopup(event)"></div>
    <div id="updatePopup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); min-width:340px; background:#fff; border-radius:16px; box-shadow:0 8px 40px rgba(0,0,0,0.18); z-index:9999; padding:2rem 2rem 1.5rem 2rem;">
        <form method="post" id="updateForm">
            <input type="hidden" name="update_trackingNo" id="update_trackingNo">
            <div class="mb-3">
                <label class="form-label" for="update_status">Durum</label>
                <select name="update_status" id="update_status" class="form-select" required>
                    <option value="Bekliyor">Bekliyor</option>
                    <option value="Onaylandı">Onaylandı</option>
                    <option value="Tamamlandı">Tamamlandı</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="update_technician">Teknisyen</label>
                <select name="update_technician" id="update_technician" class="form-select">
                    <option value="">(Atanmamış)</option>
                    <?php foreach ($teknisyenler as $t): ?>
                        <option value="<?= htmlspecialchars($t['username']) ?>">
                            <?= htmlspecialchars($t['username']) ?><?php if (!empty($t['profession'])): ?> (<?= htmlspecialchars($t['profession']) ?>)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="update_message">Mesaj</label>
                <textarea name="update_message" id="update_message" class="form-control" rows="3" placeholder="Bu arıza için not veya bilgi mesajı yazın..."></textarea>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="closeUpdatePopup()">İptal</button>
                <button type="submit" class="btn btn-success">Kaydet</button>
            </div>
        </form>
    </div>
    <!-- Detay Popup -->
    <div id="detailOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:9998;" onclick="closeDetailPopup(event)"></div>
    <div id="detailPopup" style="display:none; position:fixed; min-width:320px; background:#fff; border-radius:16px; box-shadow:0 8px 40px rgba(0,0,0,0.18); z-index:9999; padding:2rem 2rem 1.5rem 2rem;">
        <button class="btn btn-light btn-sm float-end" onclick="closeDetailPopup()">&times;</button>
        <div id="detailPopupContent"></div>
    </div>
    <script>
function openUpdatePopup(trackingNo, status, technician, message) {
    document.getElementById('update_trackingNo').value = trackingNo || '';
    document.getElementById('update_status').value = status || 'Bekliyor';
    document.getElementById('update_technician').value = technician || '';
    document.getElementById('update_message').value = message || '';
    document.getElementById('updateOverlay').style.display = 'block';
    document.getElementById('updatePopup').style.display = 'block';
}
function closeUpdatePopup(e) {
    if (!e || e.target === document.getElementById('updateOverlay')) {
        document.getElementById('updateOverlay').style.display = 'none';
        document.getElementById('updatePopup').style.display = 'none';
    }
}
function openDetailCardbox(trackingNo, btn, event) {
    if (event) event.stopPropagation();
    const p = problemsData.find(x => String(x.trackingNo).trim() === String(trackingNo).trim());
    if (!p) {
        alert('Detay bulunamadı: ' + trackingNo);
        return;
    }
    let html = `<div class='mb-2'><b>Takip No:</b> ${p.trackingNo}</div>`;
    html += `<div class='mb-2'><b>Detaylı Tanım:</b><br><span>${p.detailedDescription ?? p.description ?? '-'}</span></div>`;
    if (p.filePath && p.filePath !== '') {
        const fileName = p.filePath.split('/').pop();
        const ext = fileName.split('.').pop().toLowerCase();
        if (["jpg","jpeg","png","gif","bmp","webp"].includes(ext)) {
            html += `<div class='mb-2'><img src='uploads/${fileName}' alt='Ekli Görsel' style='max-width:100%;max-height:180px;border-radius:8px;'></div>`;
        } else {
            html += `<div class='mb-2'><a href='uploads/${fileName}' target='_blank' class='btn btn-outline-primary btn-sm'><i class='bi bi-file-earmark-arrow-down'></i> Dosyayı Görüntüle/İndir</a></div>`;
        }
    } else {
        html += `<div class='mb-2 text-muted'>Dosya eklenmemiş.</div>`;
    }
    html += `<div class='mb-2'><b>Birim:</b> ${p.department ?? '-'}</div>`;
    if (p.message && p.message !== '') {
        html += `<div class='mb-2'><b>Durum Mesajı:</b><br><span>${p.message}</span></div>`;
    }
    html += `<div class='mb-2'><b>Durum:</b> ${p.status ?? '-'}</div>`;
    html += `<div class='mb-2'><b>Tarih:</b> ${p.date ?? '-'}</div>`;
    document.getElementById('detailPopupContent').innerHTML = html;
    document.getElementById('detailOverlay').style.display = 'block';
    document.getElementById('detailPopup').style.display = 'block';
}
function closeDetailPopup(e) {
    if (!e || e.target === document.getElementById('detailOverlay')) {
        document.getElementById('detailOverlay').style.display = 'none';
        document.getElementById('detailPopup').style.display = 'none';
    }
}
</script>
    </div>
</div>
<!-- Detay Modalı ve eski openDetailModal fonksiyonunu tamamen kaldır -->
<!-- Bildirim Merkezi Modal -->
<div class="modal fade" id="notifModal" tabindex="-1" aria-labelledby="notifModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-end">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="notifModalLabel"><i class="bi bi-bell"></i> Bildirim Merkezi</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <ul class="list-group">
          <?php 
          $notifFile = 'bildirimler/notifications_' . ($_SESSION['user'] ?? '') . '.json';
          $notifs = file_exists($notifFile) ? json_decode(file_get_contents($notifFile), true) : [];
          if (!empty($notifs)) {
            foreach ($notifs as $n) {
              echo '<li class="list-group-item">' . htmlspecialchars($n['msg']) . ' <span class="text-muted float-end" style="font-size:0.9em">' . htmlspecialchars($n['date']) . '</span></li>';
            }
          } else {
            echo '<li class="list-group-item text-muted">Hiç bildiriminiz yok.</li>';
          }
          ?>
        </ul>
        <div class="text-end mt-2"><button class="btn btn-sm btn-outline-secondary" onclick="clearNotifs()">Tümünü Temizle</button></div>
      </div>
    </div>
  </div>
</div>
<!-- Yardım/SSS Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="helpModalLabel"><i class="bi bi-question-circle"></i> Yardım & SSS</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <h6>Sıkça Sorulan Sorular</h6>
        <ul>
          <li><b>Takip numaramı kaybettim, ne yapmalıyım?</b><br>İletişim bilgilerinizle birlikte destek ekibine başvurun.</li>
          <li><b>Arıza durumunu nasıl takip ederim?</b><br>"Takip" sekmesinden takip numaranızla sorgulayabilirsiniz.</li>
          <li><b>Şifremi unuttum, nasıl sıfırlarım?</b><br>Giriş ekranındaki "Şifremi unuttum" bağlantısını kullanın.</li>
        </ul>
        <hr>
        <h6>Geri Bildirim</h6>
        <form id="feedbackForm">
          <div class="mb-2">
            <label class="form-label">Görüşünüz</label>
            <textarea class="form-control" name="feedback" rows="2" required></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Gönder</button>
        </form>
        <div id="feedbackMsg" class="mt-2"></div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function clearNotifs() {
  document.querySelector('#notifModal .list-group').innerHTML = '<li class="list-group-item text-muted">Tüm bildirimler temizlendi.</li>';
}
const feedbackForm = document.getElementById('feedbackForm');
if (feedbackForm) {
  feedbackForm.onsubmit = function(e) {
    e.preventDefault();
    document.getElementById('feedbackMsg').innerHTML = '<span class="text-success">Teşekkürler, geri bildiriminiz alındı.</span>';
    feedbackForm.reset();
  };
}
</script>
<div id="floatingDarkToggle" title="Karanlık Mod" aria-label="Karanlık Mod" style="display:none;"><i class="bi bi-moon"></i></div>
<script>
function setDarkMode(on) {
  if (on) {
    document.body.classList.add('dark-mode');
    darkToggle.innerHTML = '<i class="bi bi-brightness-high"></i>';
    localStorage.setItem('darkMode', '1');
  } else {
    document.body.classList.remove('dark-mode');
    darkToggle.innerHTML = '<i class="bi bi-moon"></i>';
    localStorage.setItem('darkMode', '0');
  }
}
// Sayfa yüklenince:
const userPref = localStorage.getItem('darkMode');
if (userPref === '1') {
  setDarkMode(true);
} else if (userPref === '0') {
  setDarkMode(false);
} else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
  setDarkMode(true);
} else {
  setDarkMode(false);
}
darkToggle.onclick = () => setDarkMode(!document.body.classList.contains('dark-mode'));
document.addEventListener('DOMContentLoaded', function() {
  // Navbar butonu
  var darkToggle = document.getElementById('darkModeToggle');
  if (darkToggle) {
    darkToggle.onclick = function() {
      setDarkMode(!document.body.classList.contains('dark-mode'));
    };
  }
  // Mobil/floating buton
  var floatToggle = document.getElementById('floatingDarkToggle');
  if (floatToggle) {
    floatToggle.onclick = function() {
      setDarkMode(!document.body.classList.contains('dark-mode'));
    };
  }
  // Başlangıçta localStorage'a göre ayarla
  if (localStorage.getItem('darkMode') === '1') setDarkMode(true);
  else setDarkMode(false);
  // Mobilde floating butonu göster
  if (window.innerWidth < 600) {
    floatToggle.style.display = 'flex';
  }
  // Sistem dark mode tercihine göre otomatik başlat
  if (localStorage.getItem('darkMode') === null) {
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      document.body.classList.add('dark-mode');
      darkToggle.innerHTML = '<i class="bi bi-brightness-high"></i>';
    }
  }
});
</script>
<script>
// PHP'den JS'ye arıza verilerini dizi (array) olarak aktar
const faultTypes = <?= json_encode($faultTypes, JSON_UNESCAPED_UNICODE) ?>;
// PHP'den JS'ye problemsData aktar
const problemsData = <?php echo json_encode(array_values($problems), JSON_UNESCAPED_UNICODE); ?>;
let openCardbox = null;
function openDetailCardbox(trackingNo, btn, event) {
    console.log('[DEBUG] openDetailCardbox called with:', trackingNo, problemsData);
    if (event) event.stopPropagation();
    if (openCardbox) openCardbox.remove();
    const p = problemsData.find(x => String(x.trackingNo).trim() === String(trackingNo).trim());
    if (!p) {
        alert('Detay bulunamadı: ' + trackingNo);
        return;
    }
    let html = `<button class='close-btn' onclick='this.parentElement.remove(); openCardbox=null;'>×</button>`;
    html += `<div class='mb-2'><b>Takip No:</b> ${p.trackingNo}</div>`;
    html += `<div class='mb-2'><b>Detaylı Tanım:</b><br><span>${p.detailedDescription ?? '-'}</span></div>`;
    if (p.filePath && p.filePath !== '') {
        const fileName = p.filePath.split('/').pop();
        const ext = fileName.split('.').pop().toLowerCase();
        if (["jpg","jpeg","png","gif","bmp","webp"].includes(ext)) {
            html += `<div class='mb-2'><img src='uploads/${fileName}' alt='Ekli Görsel' style='max-width:100%;max-height:180px;border-radius:8px;'></div>`;
        } else {
            html += `<div class='mb-2'><a href='uploads/${fileName}' target='_blank' class='btn btn-outline-primary btn-sm'><i class='bi bi-file-earmark-arrow-down'></i> Dosyayı Görüntüle/İndir</a></div>`;
        }
    } else {
        html += `<div class='mb-2 text-muted'>Dosya eklenmemiş.</div>`;
    }
    html += `<div class='mb-2'><b>Birim:</b> ${p.department ?? '-'}</div>`;
    if (p.message && p.message !== '') {
        html += `<div class='mb-2'><b>Durum Mesajı:</b><br><span>${p.message}</span></div>`;
    }
    html += `<div class='mb-2'><b>Durum:</b> ${p.status ?? '-'}</div>`;
    html += `<div class='mb-2'><b>Tarih:</b> ${p.date ?? '-'}</div>`;
    const card = document.createElement('div');
    card.className = 'detail-cardbox';
    card.innerHTML = html;
    card.onclick = function(e) { e.stopPropagation(); };
    document.body.appendChild(card);
    const rect = btn.getBoundingClientRect();
    card.style.top = (window.scrollY + rect.bottom + 4) + 'px';
    card.style.left = (window.scrollX + rect.left) + 'px';
    card.style.display = 'block';
    openCardbox = card;
    // Cardbox dışına tıklayınca kapat (mousedown ile, bir kereye mahsus)
    setTimeout(() => {
      function handler(e) {
        if (openCardbox && !openCardbox.contains(e.target) && !e.target.classList.contains('detail-btn')) {
          openCardbox.remove();
          openCardbox = null;
          document.removeEventListener('mousedown', handler);
        }
      }
      document.addEventListener('mousedown', handler);
    }, 0);
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('assignTechForm');
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(form);
      fetch('admin.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(html => {
        location.reload();
      })
      .catch(err => alert('Atama sırasında hata oluştu!'));
    });
  }
});
</script>