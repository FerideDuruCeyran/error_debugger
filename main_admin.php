<?php
session_start();
// Debug output removed
require_once 'config.php';

// Session check for MainAdmin
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'MainAdmin') {
    header('Location: login.php');
    exit;
}

// Set currentUser from session
$currentUser = [
    'username' => $_SESSION['user'],
    'role' => $_SESSION['role'],
    'department' => $_SESSION['department']
];

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
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

$successMsg = $errorMsg = '';

// Fetch all users from SQL
$sql = "SELECT u.*, r.name AS role, d.name AS department
        FROM Users u
        LEFT JOIN Roles r ON u.role_id = r.id
        LEFT JOIN Departments d ON u.department_id = d.id";
$stmt = $conn->query($sql);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kullanıcı ekleme
if (isset($_POST['add_user'])) {
    $newId = count($users) ? max(array_column($users, 'id')) + 1 : 1;
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $department = $_POST['department'] ?? '';
    $manager_id = $_POST['manager_id'] ?? '';
    $errorMsg = '';

    $user = [
        'id' => $newId,
        'username' => $username,
        'role' => $role,
        'email' => $email,
        'password' => $password,
        'last_login' => '',
        'reset_token' => ''
    ];

    if ($role === 'Admin' || $role === 'AltAdmin') {
        if (!$department) {
            $errorMsg = 'Lütfen bağlı olduğu türü (departman) seçin!';
        } else {
            $user['department'] = $department;
            $user['manager_id'] = 1; // MainAdmin'e bağlı
        }
    } elseif ($role === 'TeknikPersonel') {
        if (!$manager_id && !$department) {
            $errorMsg = 'Teknik personel için bağlı admin veya tür (departman) seçmelisiniz!';
        } else if ($manager_id) {
            // Seçilen admin/altadmin'den departman al
            $admin = null;
            foreach ($users as $u) {
                if ($u['id'] == $manager_id) {
                    $admin = $u;
                    break;
                }
            }
            $user['department'] = $admin ? $admin['department'] : '';
            $user['manager_id'] = $manager_id;
        } else {
            $user['department'] = $department;
            $user['manager_id'] = null;
        }
    }
    if (!$errorMsg) {
        $users[] = $user;
        file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $successMsg = 'Kullanıcı başarıyla eklendi.';
    }
}
// Kullanıcı silme
if (isset($_POST['delete_user'])) {
    $users = array_filter($users, function($u) {
        return $u['id'] != $_POST['delete_id'];
    });
    file_put_contents($usersFile, json_encode(array_values($users), JSON_UNESCAPED_UNICODE));
    $successMsg = 'Kullanıcı başarıyla silindi.';
}
// Kullanıcı güncelleme
if (isset($_POST['update_user'])) {
    foreach ($users as &$u) {
        if ($u['id'] == $_POST['update_id']) {
            $u['username'] = trim($_POST['update_username']);
            $u['role'] = $_POST['update_role'];
        }
    }
    unset($u);
    file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE));
    $successMsg = 'Kullanıcı başarıyla güncellendi.';
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

// 1 haftadan eski tamamlanan arızaları otomatik sil
if (file_exists(PROBLEM_LOG_FILE)) {
    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $now = time();
    $newLines = [];
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry && isset($entry['status']) && $entry['status'] === 'Tamamlandı' && isset($entry['date'])) {
            $entryTime = strtotime($entry['date']);
            if ($entryTime !== false && ($now - $entryTime) > 7*24*60*60) {
                // 7 günden eski tamamlanan, sil
                continue;
            }
        }
        $newLines[] = $line;
    }
    file_put_contents(PROBLEM_LOG_FILE, implode("\n", $newLines) . "\n");
}

$date1 = $_GET['date1'] ?? '';
// $date2 = $_GET['date2'] ?? '';
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

// Teknik personel atama işlemi
if (isset($_POST['assign_submit'], $_POST['assign_tracking'], $_POST['assign_personnel'])) {
  $assignTracking = trim($_POST['assign_tracking']);
  $assignPersonnel = trim($_POST['assign_personnel']);
  if (file_exists(PROBLEM_LOG_FILE)) {
      $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      foreach ($lines as $i => $line) {
          $entry = json_decode($line, true);
          if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $assignTracking) {
              $entry['assignedTo'] = $assignPersonnel;
              $entry['assigned'] = $assignPersonnel; // Teknik personel paneliyle tam uyum
              $lines[$i] = json_encode($entry, JSON_UNESCAPED_UNICODE);
              break;
          }
      }
      file_put_contents(PROBLEM_LOG_FILE, implode(PHP_EOL, $lines) . PHP_EOL);
  }
}

// Main admin popup ile arıza güncelleme işlemi (durum, teknisyen, mesaj)
if (
    isset($_POST['update_trackingNo']) &&
    (isset($_POST['update_status']) || isset($_POST['update_technician']) || isset($_POST['update_message']))
) {
    $trackingNo = $_POST['update_trackingNo'];
    $newStatus = $_POST['update_status'] ?? '';
    $newTechnician = $_POST['update_technician'] ?? '';
    $newMessage = $_POST['update_message'] ?? '';
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $i => $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['trackingNo']) && $entry['trackingNo'] === $trackingNo) {
                if ($newStatus !== '') $entry['status'] = $newStatus;
                $entry['assignedTo'] = $newTechnician;
                $entry['message'] = $newMessage;
                $lines[$i] = json_encode($entry, JSON_UNESCAPED_UNICODE);
                break;
            }
        }
        file_put_contents(PROBLEM_LOG_FILE, implode(PHP_EOL, $lines) . PHP_EOL);
        $successMsg = 'Arıza başarıyla güncellendi.';
    }
}

// Altadmin'lere atanmış arıza sayısını say
$altadminJobCount = [];
if (file_exists(PROBLEM_LOG_FILE)) {
  $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
      $entry = json_decode($line, true);
      if ($entry && isset($entry['assignedTo']) && strpos($entry['assignedTo'], 'altadmin') !== false) {
          $altadminJobCount[$entry['assignedTo']] = ($altadminJobCount[$entry['assignedTo']] ?? 0) + 1;
      }
  }
}

// Teknik personel (alt admin) listesi (örnek)
$personnel = array_values(array_filter($users, function($u) {
  return $u['role'] === 'TeknikPersonel';
}));

$tab = $_GET['tab'] ?? 'arizalar';
$altAdmins = array_filter($users, function($u){ return $u['role']==='Admin'; });
$teknikPersonel = array_filter($users, function($u){ return $u['role']==='TeknikPersonel'; });

// Ortak arıza durumları
$faultStatuses = [
    'Bekliyor' => 'Bekliyor',
    'Onaylandı' => 'Onaylandı',
    'Tamamlandı' => 'Tamamlandı'
];

$faultTypes = [
    1 => "MAKİNE/TESİSAT",
    2 => "ELEKTRİK",
    3 => "İNŞAAT"
];
// Alt türler dizisi (tüm panellerle aynı)
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
function getFaultTypeName($id, $faultTypes) {
    return $faultTypes[$id] ?? $id;
}
// JS için güvenli string fonksiyonu
function js_safe($str) {
    return addslashes(str_replace(["\r", "\n"], ['', '\\n'], $str));
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Main Admin Paneli - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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
</style>
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
    <div class="col-12">
      <ul class="nav nav-tabs justify-content-center" style="border-bottom: 2px solid #e3f0fa;">
        <li class="nav-item flex-fill text-center"><a class="nav-link<?= $tab=='arizalar'?' active':'' ?>" href="main_admin.php?tab=arizalar"><i class="bi bi-list-task"></i> Tüm Arızalar</a></li>
        <li class="nav-item flex-fill text-center"><a class="nav-link<?= $tab=='altadmin'?' active':'' ?>" href="main_admin.php?tab=altadmin"><i class="bi bi-person-badge"></i> Alt Adminler</a></li>
        <li class="nav-item flex-fill text-center"><a class="nav-link<?= $tab=='teknik'?' active':'' ?>" href="main_admin.php?tab=teknik"><i class="bi bi-person-gear"></i> Teknik Personeller</a></li>
        <li class="nav-item flex-fill text-center"><a class="nav-link<?= $tab=='kullanicilar'?' active':'' ?>" href="main_admin.php?tab=kullanicilar"><i class="bi bi-people"></i> Kullanıcılar</a></li>
        <li class="nav-item flex-fill text-center"><a class="nav-link<?= $tab=='turler'?' active':'' ?>" href="main_admin.php?tab=turler"><i class="bi bi-tags"></i> Tür/Birim & Alt Tür Yönetimi</a></li>
      </ul>
    </div>
  </div>
</div>
<?php if ($tab=='arizalar'): ?>
<div class="container">
    <h1 class="mb-4">Main Admin Paneli</h1>
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
    <form method="get" class="row g-3 mb-4" id="filterForm">
        <div class="col-md-2">
            <label class="form-label" for="date1">Tarih</label>
            <input type="date" name="date1" class="form-control" id="date1" value="<?= htmlspecialchars($date1) ?>">
        </div>
        <!-- <div class="col-md-2">
            <label class="form-label">Tarih 2</label>
            <input type="date" name="date2" class="form-control" value="<?= htmlspecialchars($date2) ?>">
        </div> -->
        <div class="col-md-3">
            <label class="form-label">Birim</label>
            <select name="department" class="form-select" id="department">
                <option value="">Tümü</option>
                <?php
                // Filtre formundan hemen önce, eksiksiz birimler dizisi
                $departments = [
                    "Diş Hekimliği Fakültesi", "Eczacılık Fakültesi", "Edebiyat Fakültesi", "Eğitim Fakültesi", "Fen Fakültesi", "Güzel Sanatlar Fakültesi", "Hemşirelik Fakültesi", "Hukuk Fakültesi", "İktisadi ve İdari Bilimler Fakültesi", "İlahiyat Fakültesi", "İletişim Fakültesi", "Kemer Denizcilik Fakültesi", "Kumluca Sağlık Bilimleri Fakültesi", "Hukuk Müşavirliği", "Ziraat Fakültesi", "Adalet Meslek Yüksekokulu", "Alanya Meslek Yüksekokulu", "Demre Dr. Hasan Ünal Meslek Yüksekokulu", "Elmalı Meslek Yüksekokulu", "Finike Meslek Yüksekokulu", "Gastronomi ve Mutfak Sanatları Meslek Yüksekokulu", "Korkuteli Meslek Yüksekokulu", "Kumluca Meslek Yüksekokulu", "Manavgat Meslek Yüksekokulu", "Serik Meslek Yüksekokulu", "Sosyal Bilimler Meslek Yüksekokulu", "Teknik Bilimler Meslek Yüksekokulu", "Turizm İşletmeciliği ve Otelcilik Yüksekokulu", "Antalya Devlet Konservatuvarı", "Yabancı Diller Yüksekokulu", "Akdeniz Uygarlıkları Araştırma Enstitüsü", "Eğitim Bilimleri Enstitüsü", "Fen Bilimleri Enstitüsü", "Güzel Sanatlar Enstitüsü", "Prof.Dr.Tuncer Karpuzoğlu Organ Nakli Enstitüsü", "Sağlık Bilimleri Enstitüsü", "Sosyal Bilimler Enstitüsü", "Atatürk İlkeleri ve İnkılap Tarihi Bölüm Başkanlığı", "Beden Eğitimi ve Spor Bölüm Başkanlığı", "Enformatik Bölüm Başkanlığı", "Güzel Sanatlar Bölüm Başkanlığı", "Türk Dili Bölüm Başkanlığı", "Hukuk Müşavirliği", "Kütüphane ve Dokümantasyon Daire Başkanlığı", "Öğrenci İşleri Daire Başkanlığı", "Sağlık Kültür ve Spor Daire Başkanlığı", "Strateji Geliştirme Daire Başkanlığı", "Uluslararası İlişkiler Ofisi", "Yapı İşleri ve Teknik Daire Başkanlığı", "Basın Yayın ve Halkla İlişkiler Müdürlüğü", "Döner Sermaye İşletme Müdürlüğü", "Hastane", "İdari ve Mali İşler Daire Başkanlığı", "İnsan Kaynakları Daire Başkanlığı", "Kariyer Planlama ve Mezun İzleme Uygulama ve Araştırma Merkezi", "Kütüphane ve Dokümantasyon Daire Başkanlığı", "Öğrenci İşleri Daire Başkanlığı", "Sağlık Kültür ve Spor Daire Başkanlığı", "Strateji Geliştirme Daire Başkanlığı", "Teknoloji Transfer Ofisi", "TÖMER", "Yabancı Diller Yüksekokulu", "Diğer (liste dışı birim)"
                ];
                foreach ($departments as $dep): ?>
                    <option value="<?= htmlspecialchars($dep) ?>" <?= $department === $dep ? 'selected' : '' ?>><?= htmlspecialchars($dep) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Durum</label>
            <select name="status" class="form-select" id="status">
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
    <h2>Tüm Arızalar ve Durumları</h2>
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
    <?php
// Tablodan önce arıza kayıtlarını oku
//$problems = [];
//if (file_exists(PROBLEM_LOG_FILE)) {
//    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//    $latest = [];
//    foreach ($lines as $line) {
//        $entry = json_decode($line, true);
//        if ($entry && isset($entry['trackingNo'])) {
//            if (!isset($entry['status']) || trim($entry['status']) === '') {
//                $entry['status'] = 'Bekliyor';
//            }
//            $latest[$entry['trackingNo']] = $entry;
//        }
//    }
//    $problems = array_values($latest);
//}
$problems = $reports;
?>
<?php
foreach ($problems as $p):
?>
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
    <td class="text-center"><span class="badge bg-secondary"><?= htmlspecialchars(getFaultTypeName($p['faultType'], $faultTypes)) ?></span></td>
    <td class="text-center"><?php $subId = $p['subFaultType'] ?? null; echo $subId && isset($subFaultTypes[$subId]) ? htmlspecialchars($subFaultTypes[$subId]) : '-'; ?></td>
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
        <span class="badge <?= $statusClass ?>">
            <i class="bi <?= $statusIcon ?>"></i> <?= htmlspecialchars($p['status']) ?>
        </span>
        <button type="button" class="btn btn-sm btn-outline-primary ms-2" title="Düzenle"
    onclick="openStatusEditPopup(this, 
        '<?= js_safe($p['trackingNo'] ?? '') ?>', 
        '<?= js_safe($p['status'] ?? '') ?>', 
        '<?= js_safe($assignedTech ?? '') ?>', 
        '<?= js_safe($p['message'] ?? '') ?>'
    )">
    <i class="bi bi-pencil"></i>
</button>
        <?php if (!empty($p['message'])): ?>
        <span class="msg-icon-wrap position-relative" style="margin-left:10px;vertical-align:middle;">
          <i class="bi bi-chat-left-text-fill text-primary"
             style="font-size:1.2em;cursor:pointer;"
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
</div>
    <script>
    const faultTypes = <?= json_encode($faultTypes, JSON_UNESCAPED_UNICODE) ?>;
    const problemsData = <?= json_encode($problems, JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <h2>Arıza İstatistikleri</h2>
    <?php
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
    <div class="row mb-4 justify-content-center">
      <div class="col-md-2 mb-3">
        <div class="card text-bg-primary h-100">
          <div class="card-body text-center">
            <div class="fs-2 fw-bold"><?= $totalFaults ?></div>
            <div class="fw-semibold">Toplam Arıza</div>
          </div>
        </div>
      </div>
      <div class="col-md-2 mb-3">
        <div class="card text-bg-warning h-100">
          <div class="card-body text-center">
            <div class="fs-2 fw-bold"><?= $statusCounts['Bekliyor'] ?></div>
            <div class="fw-semibold">Bekliyor</div>
          </div>
        </div>
      </div>
      <div class="col-md-2 mb-3">
        <div class="card text-bg-info h-100">
          <div class="card-body text-center">
            <div class="fs-2 fw-bold"><?= $statusCounts['Onaylandı'] ?></div>
            <div class="fw-semibold">Onaylandı</div>
          </div>
        </div>
      </div>
      <div class="col-md-2 mb-3">
        <div class="card text-bg-success h-100">
          <div class="card-body text-center">
            <div class="fs-2 fw-bold"><?= $statusCounts['Tamamlandı'] ?></div>
            <div class="fw-semibold">Tamamlandı</div>
          </div>
        </div>
      </div>
      <div class="col-md-2 mb-3">
        <div class="card text-bg-secondary h-100">
          <div class="card-body text-center">
            <div class="fs-2 fw-bold"><?= $lastMonthCount ?></div>
            <div class="fw-semibold">Son 1 Ayda Açılan</div>
          </div>
        </div>
      </div>
    </div>
    <div class="row mb-4 justify-content-center">
      <div class="col-md-4 mb-3">
        <div class="card h-100">
          <div class="card-body text-center">
            <div class="fw-semibold">En Çok Arıza Bildiren Birim</div>
            <div class="fs-4 fw-bold"><?= htmlspecialchars($mostReportedDepartment) ?></div>
            <div class="text-muted">(<?= $mostReportedDepartmentCount ?> arıza)</div>
          </div>
        </div>
      </div>
      <div class="col-md-4 mb-3">
        <div class="card h-100">
          <div class="card-body text-center">
            <div class="fw-semibold">En Çok Görülen Arıza Türü</div>
            <div class="fs-4 fw-bold"><?php echo isset(
  $faultTypes[$mostReportedType]) ? htmlspecialchars($faultTypes[$mostReportedType]) : htmlspecialchars($mostReportedType); ?></div>
  <div class="text-muted">(<?= $mostReportedTypeCount ?> arıza)</div>
</div>
</div>
</div>
<div class="row mb-4 justify-content-center">
  <div class="col-md-6">
    <canvas id="chartType" width="400" height="220"></canvas>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php
$typeCounts = [];
if (file_exists(PROBLEM_LOG_FILE)) {
    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry) {
            $type = $entry['faultType'] ?? '-';
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
        }
    }
}
// labels için isimleri kullan
$typeLabels = array_map(function($id) use ($faultTypes) {
    return $faultTypes[$id] ?? $id;
}, array_keys($typeCounts));
?>
const typeData = {
  labels: <?= json_encode($typeLabels, JSON_UNESCAPED_UNICODE) ?>,
  datasets: [{
    label: 'Arıza Türü',
    data: <?= json_encode(array_values($typeCounts)) ?>,
    backgroundColor: ['#0d6efd','#20c997','#ffc107','#fd7e14','#6f42c1','#dc3545','#198754','#0dcaf0','#adb5bd','#343a40']
  }]
};
if (document.getElementById('chartType')) {
new Chart(document.getElementById('chartType'), {
  type: 'doughnut',
  data: typeData,
  options: { plugins: { legend: { position: 'bottom' } }, responsive:true }
});
}
</script>
</div>
<?php elseif ($tab=='altadmin'): ?>
<div class="container">
    <h1 class="mb-4">Alt Adminler</h1>
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-primary">
        <tr>
            <th>ID</th>
            <th>Kullanıcı Adı</th>
            <th>Rol</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($altAdmins as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['id']) ?></td>
                <td><?= htmlspecialchars($a['username']) ?></td>
                <td><?= htmlspecialchars($a['role']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php elseif ($tab=='teknik'): ?>
<div class="container">
    <h1 class="mb-4">Teknik Personeller</h1>
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-primary">
        <tr>
            <th>ID</th>
            <th>Kullanıcı Adı</th>
            <th>Rol</th>
            <th>Branş</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($teknikPersonel as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['id']) ?></td>
                <td><?= htmlspecialchars($t['username']) ?></td>
                <td><?= htmlspecialchars($t['role']) ?></td>
                <td><?= htmlspecialchars($t['profession'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php elseif ($tab=='kullanicilar'): ?>
<?php
$typesFile = 'types.json';
$types = file_exists($typesFile) ? json_decode(file_get_contents($typesFile), true) : [];
?>
<div class="container">
    <h1 class="mb-4">Kullanıcılar ve Yetkiler</h1>
    <form method="post" class="row g-3 mb-4 align-items-end">
        <div class="col-md-2">
            <input type="text" name="username" class="form-control" placeholder="Kullanıcı Adı" required>
        </div>
        <div class="col-md-2">
            <select name="role" id="role" class="form-select" required onchange="toggleFields()">
                <option value="">Rol Seç</option>
                <option value="MainAdmin">MainAdmin</option>
                <option value="Admin">Admin</option>
                <option value="TeknikPersonel">TeknikPersonel</option>
            </select>
        </div>
        <div class="col-md-2" id="departmentField" style="display:none;">
            <select name="department" id="department" class="form-select">
                <option value="">Birim/Tür Seç</option>
                <?php foreach ($types as $type => $subs): ?>
                  <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2" id="adminField" style="display:none;">
            <select name="manager_id" id="manager_id" class="form-select" onchange="fillTechDepartment()">
                <option value="">Bağlı Admin Seçin (isteğe bağlı)</option>
                <?php foreach ($users as $u): ?>
                    <?php if (in_array($u['role'], ['Admin'])): ?>
                        <option value="<?= $u['id'] ?>" data-department="<?= htmlspecialchars($u['department'] ?? '') ?>">
                            <?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['department'] ?? '-') ?>)
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <input type="email" name="email" class="form-control" placeholder="E-posta">
        </div>
        <div class="col-md-2">
            <input type="text" name="password" class="form-control" placeholder="Şifre">
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" name="add_user" class="btn btn-success">Ekle</button>
        </div>
    </form>
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-primary">
        <tr>
            <th>ID</th>
            <th>Kullanıcı Adı</th>
            <th>Rol</th>
            <th>İşlemler</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['id']) ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['role']) ?></td>
                <td>
                    <form method="post" style="display:inline-block">
                        <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Sil</button>
                    </form>
                    <form method="post" style="display:inline-block">
                        <input type="hidden" name="update_id" value="<?= $u['id'] ?>">
                        <input type="text" name="update_username" value="<?= htmlspecialchars($u['username']) ?>" class="form-control form-control-sm d-inline w-auto" required>
                        <select name="update_role" class="form-select form-select-sm d-inline w-auto" required>
                            <option value="MainAdmin" <?= $u['role']=='MainAdmin'?'selected':'' ?>>MainAdmin</option>
                            <option value="Admin" <?= $u['role']=='Admin'?'selected':'' ?>>Admin</option>
                            <option value="TeknikPersonel" <?= $u['role']=='TeknikPersonel'?'selected':'' ?>>TeknikPersonel</option>
                        </select>
                        <button type="submit" name="update_user" class="btn btn-primary btn-sm">Güncelle</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php elseif ($tab=='turler'): ?>
<div class="container mt-5 d-flex flex-column align-items-center justify-content-center" style="min-height:70vh;">
  <div class="card shadow-sm p-4" style="max-width: 900px; width:100%;">
    <h2 class="mb-4 text-center text-primary"><i class="bi bi-tags"></i> Tür/Birim & Alt Tür Yönetimi</h2>
    <?php
      $typesFile = 'types.json';
      if (!file_exists($typesFile)) file_put_contents($typesFile, '{}');
      $types = json_decode(file_get_contents($typesFile), true);
      $typeMsg = '';
      // Tür ekle
      if (isset($_POST['add_type']) && !empty($_POST['type_name'])) {
        $name = trim($_POST['type_name']);
        if (!isset($types[$name])) {
          $types[$name] = [];
          file_put_contents($typesFile, json_encode($types, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
          $typeMsg = 'Tür eklendi!';
        } else {
          $typeMsg = 'Bu tür zaten var!';
        }
      }
      // Tür sil
      if (isset($_POST['delete_type']) && !empty($_POST['delete_type_name'])) {
        $name = $_POST['delete_type_name'];
        // Silinmek istenen tür/birime atanmış admin var mı kontrol et
        $usersList = file_exists('users.json') ? json_decode(file_get_contents('users.json'), true) : [];
        $assignedAdmin = false;
        foreach ($usersList as $u) {
          if (in_array($u['role'], ['Admin']) && isset($u['department']) && $u['department'] === $name) {
            $assignedAdmin = true;
            break;
          }
        }
        if ($assignedAdmin) {
          $typeMsg = 'Bu tür/birime atanmış bir admin olduğu için silinemez!';
        } else if (isset($types[$name])) {
          unset($types[$name]);
          file_put_contents($typesFile, json_encode($types, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
          $typeMsg = 'Tür silindi!';
        }
      }
      // Alt tür ekle
      if (isset($_POST['add_subtype']) && !empty($_POST['subtype_name']) && !empty($_POST['parent_type'])) {
        $parent = $_POST['parent_type'];
        $sub = trim($_POST['subtype_name']);
        if (isset($types[$parent]) && !in_array($sub, $types[$parent])) {
          $types[$parent][] = $sub;
          file_put_contents($typesFile, json_encode($types, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
          $typeMsg = 'Alt tür eklendi!';
        } else {
          $typeMsg = 'Bu alt tür zaten var!';
        }
      }
      // Alt tür sil
      if (isset($_POST['delete_subtype']) && !empty($_POST['delete_subtype_name']) && !empty($_POST['delete_subtype_parent'])) {
        $parent = $_POST['delete_subtype_parent'];
        $sub = $_POST['delete_subtype_name'];
        if (isset($types[$parent])) {
          $types[$parent] = array_values(array_diff($types[$parent], [$sub]));
          file_put_contents($typesFile, json_encode($types, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
          $typeMsg = 'Alt tür silindi!';
        }
      }
    ?>
    <?php if ($typeMsg): ?>
      <div class="alert alert-info text-center"> <?= htmlspecialchars($typeMsg) ?> </div>
    <?php endif; ?>
    <div class="row justify-content-center mb-4 g-3">
      <div class="col-12 col-md-6 d-flex align-items-end">
        <form method="post" class="flex-grow-1 d-flex gap-2 align-items-end justify-content-center">
          <input type="text" name="type_name" class="form-control flex-grow-1" placeholder="Yeni Tür/Birim" required>
          <button type="submit" name="add_type" class="btn btn-primary flex-shrink-0"><i class="bi bi-plus-circle"></i> Tür/Birim Ekle</button>
        </form>
      </div>
      <div class="col-12 col-md-6 d-flex align-items-end">
        <form method="post" class="flex-grow-1 d-flex gap-2 align-items-end justify-content-center">
          <select name="parent_type" class="form-select flex-grow-1" required style="max-width:220px;">
            <option value="">Tür/Birim Seç</option>
            <?php foreach ($types as $t => $subs): ?>
              <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="subtype_name" class="form-control flex-grow-1" placeholder="Yeni Alt Tür" required>
          <button type="submit" name="add_subtype" class="btn btn-secondary flex-shrink-0"><i class="bi bi-plus-circle"></i> Alt Tür Ekle</button>
        </form>
      </div>
    </div>
    <div class="row justify-content-center">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="mb-3 text-center text-secondary"><i class="bi bi-list-ul"></i> Mevcut Türler ve Alt Türler</h5>
            <ul class="list-group list-group-flush">
              <?php foreach ($types as $t => $subs): ?>
                <li class="list-group-item">
                  <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-primary"><i class="bi bi-tag"></i> <?= htmlspecialchars($t) ?></span>
                    <form method="post" style="display:inline-block">
                      <input type="hidden" name="delete_type_name" value="<?= htmlspecialchars($t) ?>">
                      <button type="submit" name="delete_type" class="btn btn-danger btn-sm" onclick="return confirm('Bu türü silmek istediğinize emin misiniz?')"><i class="bi bi-trash"></i> Sil</button>
                    </form>
                  </div>
                  <?php if ($subs): ?>
                    <ul class="list-group mt-2 ms-4">
                      <?php foreach ($subs as $sub): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                          <span><i class="bi bi-chevron-right"></i> <?= htmlspecialchars($sub) ?></span>
                          <form method="post" style="display:inline-block">
                            <input type="hidden" name="delete_subtype_parent" value="<?= htmlspecialchars($t) ?>">
                            <input type="hidden" name="delete_subtype_name" value="<?= htmlspecialchars($sub) ?>">
                            <button type="submit" name="delete_subtype" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle"></i> Sil</button>
                          </form>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
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
          $notifFile = 'bildirimler/notifications_' . ($currentUser['username'] ?? '') . '.json';
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
<!-- Açıklama popup -->
<div id="descPopup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); min-width:320px; max-width:90vw; background:#fff; border-radius:16px; box-shadow:0 8px 40px rgba(0,0,0,0.18); z-index:9999; padding:2.2rem 2.2rem 1.5rem 2.2rem; font-size:1.15em; line-height:1.5;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <span style="font-size:1.25em;font-weight:600;">Açıklama</span>
    <button class="btn btn-light btn-sm" style="opacity:0.7;font-size:1.5em;line-height:1;" onclick="closeDescPopup()">&times;</button>
  </div>
  <div id="descPopupContent" style="max-height:60vh; overflow:auto; word-break:break-word;"></div>
</div>
<div id="descOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9998;" onclick="closeDescPopup()"></div>
<script>
function showDescPopup(desc) {
  document.getElementById('descPopupContent').innerText = desc;
  document.getElementById('descPopup').style.display = 'block';
  document.getElementById('descOverlay').style.display = 'block';
}
function closeDescPopup() {
  document.getElementById('descPopup').style.display = 'none';
  document.getElementById('descOverlay').style.display = 'none';
}
</script>
<!-- Mesaj bubble -->
<style>
.msg-bubble {
  display: block;
  position: absolute;
  left: 50%;
  bottom: 120%;
  transform: translateX(-50%);
  min-width: 200px;
  max-width: 340px;
  background: #fff;
  color: #005ca9;
  border-radius: 10px;
  box-shadow: 0 6px 32px rgba(0,92,169,0.18);
  padding: 14px 18px;
  font-size: 1.08em;
  z-index: 1000;
  white-space: pre-line;
  word-break: break-word;
  border: 1.5px solid #b6d4fe;
}
.msg-bubble::after {
  content: '';
  position: absolute;
  left: 50%;
  top: 100%;
  transform: translateX(-50%);
  border-width: 8px;
  border-style: solid;
  border-color: #fff transparent transparent transparent;
  filter: drop-shadow(0 2px 2px #b6d4fe);
}
</style>
<script>
let msgBubbleEl = null;
let openCardbox = null;
let msgBubbleTimeout = null;
function showMsgBubble(icon, msg) {
  hideMsgBubble();
  msgBubbleEl = document.createElement('div');
  msgBubbleEl.className = 'msg-bubble';
  msgBubbleEl.innerText = msg;
  icon.parentElement.appendChild(msgBubbleEl);
  msgBubbleEl.addEventListener('mouseenter', function() {
    if (msgBubbleTimeout) clearTimeout(msgBubbleTimeout);
  });
  msgBubbleEl.addEventListener('mouseleave', function() {
    hideMsgBubble();
  });
}
function hideMsgBubble() {
  if (msgBubbleTimeout) clearTimeout(msgBubbleTimeout);
  if (msgBubbleEl && msgBubbleEl.parentElement) msgBubbleEl.parentElement.removeChild(msgBubbleEl);
  msgBubbleEl = null;
}
function toggleMsgBubble(icon, msg) {
  if (msgBubbleEl) { hideMsgBubble(); return; }
  showMsgBubble(icon, msg);
}
const problemMessages = <?php
$messagesArr = [];
if (file_exists(PROBLEM_LOG_FILE)) {
    $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry && !empty($entry['message']) && !empty($entry['trackingNo'])) {
            $messagesArr[$entry['trackingNo']] = $entry['message'];
        }
    }
}
$out = json_encode($messagesArr, JSON_UNESCAPED_UNICODE);
echo ($out && $out !== 'null') ? $out : '{}';
?>;
</script>
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
<!-- Durum Düzenleme Popup -->
<div id="statusEditOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:9998;" onclick="closeStatusEditPopup(event)"></div>
<div id="statusEditPopup" style="display:none; position:fixed; min-width:320px; background:#fff; border-radius:16px; box-shadow:0 8px 40px rgba(0,0,0,0.18); z-index:9999; padding:2rem 2rem 1.5rem 2rem;">
    <form method="post" id="statusEditForm">
        <input type="hidden" name="update_trackingNo" id="statusEdit_trackingNo">
        <div class="mb-3">
            <label class="form-label" for="statusEdit_status">Durum</label>
            <select name="update_status" id="statusEdit_status" class="form-select" required>
<?php foreach ($faultStatuses as $key => $label): ?>
    <option value="<?= $key ?>"><?= $label ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Teknisyen</label>
            <select name="update_technician" id="statusEdit_technician" class="form-select">
                <option value="">(Atanmamış)</option>
                <?php foreach ($teknikPersonel as $t): ?>
                    <option value="<?= htmlspecialchars($t['username']) ?>">
                        <?= htmlspecialchars($t['username']) ?><?php if (!empty($t['profession'])): ?> (<?= htmlspecialchars($t['profession']) ?>)<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Mesaj</label>
            <textarea name="update_message" id="statusEdit_message" class="form-control" rows="3" placeholder="Bu arıza için not veya bilgi mesajı yazın..."></textarea>
        </div>
        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" onclick="closeStatusEditPopup()">İptal</button>
            <button type="submit" class="btn btn-success">Kaydet</button>
        </div>
    </form>
</div>
<script>
function closeStatusEditPopup(e) {
    if (!e || e.target === document.getElementById('statusEditOverlay')) {
        document.getElementById('statusEditOverlay').style.display = 'none';
        document.getElementById('statusEditPopup').style.display = 'none';
    }
}
</script>
<script>
function resetFilters() {
    window.location.href = window.location.pathname + '?tab=arizalar';
}
</script>
<script>
function toggleFields() {
    var roleEl = document.getElementById('role');
    if (!roleEl) return;
    var role = roleEl.value;
    var departmentField = document.getElementById('departmentField');
    var adminField = document.getElementById('adminField');
    if (role === 'Admin') {
        departmentField.style.display = '';
        adminField.style.display = 'none';
    } else if (role === 'TeknikPersonel') {
        departmentField.style.display = 'none';
        adminField.style.display = '';
    } else {
        departmentField.style.display = 'none';
        adminField.style.display = 'none';
    }
}
var roleEl = document.getElementById('role');
if (roleEl) {
  roleEl.addEventListener('change', toggleFields);
}
document.addEventListener('DOMContentLoaded', toggleFields);
function fillTechDepartment() {
    var adminSelect = document.getElementById('manager_id');
    var depSelect = document.getElementById('department');
    var selected = adminSelect.options[adminSelect.selectedIndex];
    var dep = selected.getAttribute('data-department');
    if (adminSelect.value && dep) {
        depSelect.value = dep;
        depSelect.disabled = true;
    } else {
        depSelect.disabled = false;
    }
}
</script>
<script>
// Global error handler
window.onerror = function(message, source, lineno, colno, error) {
  return false;
};
// Durum edit popup fonksiyonu
function openStatusEditPopup(badgeEl, trackingNo, status, technician, message) {
  var popup = document.getElementById('statusEditPopup');
  var overlay = document.getElementById('statusEditOverlay');
  document.getElementById('statusEdit_trackingNo').value = trackingNo;
  document.getElementById('statusEdit_status').value = status;
  document.getElementById('statusEdit_technician').value = technician || '';
  document.getElementById('statusEdit_message').value = message || '';
  popup.style.top = '50%';
  popup.style.left = '50%';
  popup.style.transform = 'translate(-50%, -50%)';
  popup.style.display = 'block';
  overlay.style.display = 'block';
}
// Detay popup fonksiyonu
function openDetailCardbox(trackingNo, btn, event) {
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
    if (p.message && p.message !== '') {
      html += `<div class='mb-2'><b>Teknik Personel Mesajı:</b><br><span>${p.message}</span></div>`;
    }
    html += `<div class='mb-2'><b>Birim:</b> ${p.department ?? '-'}</div>`;
    html += `<div class='mb-2'><b>Arıza Türü:</b> ${faultTypes[p.faultType] ?? p.faultType}</div>`;
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
}
setTimeout(() => {
  document.addEventListener('click', function(e) {
      if (openCardbox && !openCardbox.contains(e.target) && !e.target.classList.contains('detail-btn')) {
          openCardbox.remove();
          openCardbox = null;
      }
  });
}, 0);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Karanlık mod fonksiyonu (tüm panellerle birebir aynı)
const darkToggle = document.getElementById('darkModeToggle');
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
// Modal butonları debug
const notifBtn = document.getElementById('notifBtn');
notifBtn && notifBtn.addEventListener('click', function() {
});
const helpBtn = document.getElementById('helpBtn');
helpBtn && helpBtn.addEventListener('click', function() {
});
// Toast'ları otomatik ve çarpıdan kapatılabilir yap
var toastElList = [].slice.call(document.querySelectorAll('.toast'));
toastElList.forEach(function (toastEl) {
  new bootstrap.Toast(toastEl, { delay: 4000 }).show();
});
</script>
<script>
(function() {
  var notifDot = document.getElementById('notifDot');
  var notifBtn = document.getElementById('notifBtn');
  var notifCount = 0;
  <?php
  $notifFile = 'bildirimler/notifications_' . ($currentUser['username'] ?? '') . '.json';
  $notifs = file_exists($notifFile) ? json_decode(file_get_contents($notifFile), true) : [];
  if (!empty($notifs)) {
      echo 'notifCount = ' . count($notifs) . ';';
  }
  ?>
  if (notifDot && notifCount > 0) {
    notifDot.classList.remove('d-none');
    notifDot.innerText = notifCount;
  } else if (notifDot) {
    notifDot.classList.add('d-none');
    notifDot.innerText = '';
  }
  if (notifBtn) {
    notifBtn.addEventListener('click', function() {
      if (notifDot) {
        notifDot.classList.add('d-none');
        notifDot.innerText = '';
      }
    });
  }
})();
</script>
</body>
</html> 