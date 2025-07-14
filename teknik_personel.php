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
if (!$currentUser || $currentUser['role'] !== 'TeknikPersonel') {
    header('Location: login.php');
    exit;
}
$tab = $_GET['tab'] ?? 'assigned';
// Ortak arıza durumları
$faultStatuses = [
    'Bekliyor' => 'Bekliyor',
    'Onaylandı' => 'Onaylandı',
    'Tamamlandı' => 'Tamamlandı'
];
// Add this near the top, after session and config:
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
function getSubFaultTypeName($id, $subFaultTypes) {
    return $subFaultTypes[$id] ?? $id;
}
// --- GÜNCELLEME İŞLEMİ ---
$successMsg = $errorMsg = '';
// Bildirim gösterimi (admin.php'deki gibi)
$notification = '';
$notifFile = 'bildirimler/notifications_' . ($_SESSION['user'] ?? '') . '.json';
if (file_exists($notifFile)) {
    $notifs = json_decode(file_get_contents($notifFile), true);
    if (!empty($notifs)) {
        $notification = $notifs[0]['msg'];
        // Bildirimi gösterdikten sonra sil
        array_shift($notifs);
        file_put_contents($notifFile, json_encode($notifs, JSON_UNESCAPED_UNICODE));
    }
}
// Bildirim temizleme işlemi
if (isset($_POST['clear_notifs'])) {
    $notifFile = 'bildirimler/notifications_' . ($_SESSION['user'] ?? '') . '.json';
    file_put_contents($notifFile, json_encode([], JSON_UNESCAPED_UNICODE));
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}
if (isset($_POST['update_trackingNo'], $_POST['update_message'])) {
    $trackingNo = $_POST['update_trackingNo'];
    $newMessage = $_POST['update_message'] ?? '';
    $newStatus = $_POST['update_status'] ?? '';
    $updated = false;
    if (file_exists(PROBLEM_LOG_FILE)) {
        $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $i => $line) {
            $entry = json_decode($line, true);
            $assignedUser = $entry['assignedTo'] ?? ($entry['assigned'] ?? null);
            if (
                $entry &&
                isset($entry['trackingNo']) && $entry['trackingNo'] === $trackingNo &&
                $assignedUser &&
                mb_strtolower(trim($assignedUser)) === mb_strtolower(trim($currentUser['username']))
            ) {
                $entry['message'] = $newMessage;
                $entry['status'] = $newStatus;
                // Bildirim: Mesaj varsa, ilgili kişilere gönder
                if (!empty($newMessage)) {
                    if (!empty($entry['username'])) {
                        addNotification($entry['username'], 'Takip No: ' . $trackingNo . ' için yeni mesaj: ' . $newMessage);
                    }
                    if (!empty($entry['assignedBy'])) {
                        addNotification($entry['assignedBy'], 'Takip No: ' . $trackingNo . ' için yeni mesaj: ' . $newMessage);
                    }
                }
                $lines[$i] = json_encode($entry, JSON_UNESCAPED_UNICODE);
                $updated = true;
                break;
            }
        }
        file_put_contents(PROBLEM_LOG_FILE, implode("\n", $lines) . "\n");
        if ($updated) {
            $successMsg = 'Mesaj ve durum başarıyla güncellendi.';
        } else {
            $errorMsg = 'Güncelleme başarısız. Kayıt bulunamadı veya yetkiniz yok.';
        }
    } else {
        $errorMsg = 'Kayıt dosyası bulunamadı.';
    }
}
// --- Atanan arızaları $problems dizisine aktar ---
$date1 = $_GET['date1'] ?? '';
$department = $_GET['department'] ?? '';
$status = $_GET['status'] ?? '';

$reports = [];
$logFile = PROBLEM_LOG_FILE;
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        $assignedUser = $entry['assignedTo'] ?? ($entry['assigned'] ?? null);
        if ($entry && $assignedUser && mb_strtolower(trim($assignedUser)) === mb_strtolower(trim($currentUser['username']))) {
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
$problems = $reports;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Teknik Personel Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
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
.desc-hover {
  cursor: pointer;
  color: #0d6efd;
  max-width: 220px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: middle;
  display: inline-block;
  transition: font-size 0.18s, background 0.18s, color 0.18s;
}
.desc-hover:hover {
  font-size: 1.18em;
  background: #e3f0fa;
  color: #005ca9;
  z-index: 2;
}
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
  border: 1.5px solid #b6d4fa;
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
  filter: drop-shadow(0 2px 2px #b6d4fa);
}
</style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="<?php echo htmlspecialchars($panel); ?>">
      <img src="https://upload.wikimedia.org/wikipedia/tr/d/dc/Akdeniz_%C3%9Cniversitesi_logosu.IMG_0838.png" class="akdeniz-logo" alt="Akdeniz Üniversitesi">
      <span>Akdeniz Üniversitesi</span>
    </a>
    <div class="d-flex ms-auto align-items-center gap-2">
      <span class="badge bg-light text-primary me-2">
        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($currentUser['username'] ?? 'Misafir') ?>
        <span class="badge bg-secondary ms-1"><?= htmlspecialchars($currentUser['role'] ?? '') ?></span>
      </span>
      <button class="btn-icon" id="darkModeToggle" title="Karanlık Modu Aç/Kapat" aria-label="Karanlık Mod"><i class="bi bi-moon"></i></button>
      <!-- Bildirim simgesi, badge veya sayı olmadan -->
      <a class="btn-icon" id="notifBtn" title="Bildirimler" data-bs-toggle="modal" data-bs-target="#notifModal"><i class="bi bi-bell"></i></a>
      <a class="btn-icon" id="helpBtn" title="Yardım" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="bi bi-question-circle"></i></a>
      <a class="btn btn-outline-light" href="index.php"><i class="bi bi-house"></i> Ana Sayfa</a>
      <a class="btn btn-outline-light" href="messages.php"><i class="bi bi-chat-dots"></i> Mesajlar</a>
      <a class="btn btn-outline-light" href="logout.php"><i class="bi bi-box-arrow-right"></i> Çıkış</a>
    </div>
  </div>
</nav>
<?php if ($notification): ?>
<div class="container mt-2">
  <div class="alert alert-success alert-dismissible fade show" role="alert" style="background-color:#d1f7d6; color:#155724; border-color:#b6e6bd;">
    <?= htmlspecialchars($notification) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
  </div>
</div>
<?php endif; ?>
<div class="container mb-4">
  <div class="row align-items-center mb-3">
    <div class="col-md-8">
      <h2 class="mb-0">Teknik Personel Paneli</h2>
      <div class="text-muted small">Hoşgeldiniz, <b><?= htmlspecialchars($currentUser['username']) ?></b><?php if (!empty($currentUser['profession'])): ?> (<?= htmlspecialchars($currentUser['profession']) ?>)<?php endif; ?></div>
    </div>
    <div class="col-md-4 text-md-end mt-2 mt-md-0">
      <ul class="nav nav-tabs justify-content-end" style="border-bottom: 2px solid #e3f0fa;">
        <li class="nav-item"><a class="nav-link<?= $tab=='assigned'?' active':'' ?>" href="teknik_personel.php?tab=assigned"><i class="bi bi-list-task"></i> Atanan İşler</a></li>
        <li class="nav-item"><a class="nav-link<?= $tab=='profile'?' active':'' ?>" href="teknik_personel.php?tab=profile"><i class="bi bi-person"></i> Profil</a></li>
      </ul>
    </div>
  </div>
  <form method="get" class="row g-3 mb-4" id="filterForm">
    <div class="col-md-2">
      <label class="form-label">Tarih</label>
      <input type="date" name="date1" class="form-control" value="<?= htmlspecialchars($date1) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Birim</label>
      <select name="department" class="form-select">
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
      <label class="form-label">Durum</label>
      <select name="status" class="form-select">
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
    window.location.href = window.location.pathname + '?tab=assigned';
  }
  </script>
</div>
<div class="container">
<?php if ($tab=='assigned'): ?>
    <div class="alert alert-info">
        <b>Oturumdaki kullanıcı:</b> <?= htmlspecialchars($currentUser['username']) ?><br>
    </div>
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3 gap-2">
          <h4 class="mb-0"><i class="bi bi-list-task text-primary"></i> Atanan İşlerim</h4>
          <input type="text" id="tableSearch" class="form-control w-auto" style="max-width:260px;" placeholder="Tabloda ara...">
        </div>
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
        <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover align-middle detail-table shadow-sm" id="assignedTable">
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
<tr>
    <td><i class="bi bi-building text-muted"></i> <?= htmlspecialchars($p['department']) ?></td>
    <td class="text-center"><span class="badge bg-secondary"><?= htmlspecialchars(getFaultTypeName($p['faultType'], $faultTypes)) ?></span></td>
    <td class="text-center"><?php $subId = $p['subFaultType'] ?? null; echo $subId && isset($subFaultTypes[$subId]) ? htmlspecialchars($subFaultTypes[$subId]) : '-'; ?></td>
    <td class="text-center fw-bold text-primary"><?= htmlspecialchars($p['trackingNo']) ?></td>
    <td>
      <?php if (!empty($p['description'])): ?>
        <span class="desc-hover" onclick="showDescPopup(`<?= htmlspecialchars(addslashes($p['description'])) ?>`)" title="<?= htmlspecialchars($p['description']) ?>">
          <?= htmlspecialchars(mb_strimwidth($p['description'], 0, 60, '...')) ?>
        </span>
      <?php endif; ?>
    </td>
    <td class="text-center">
      <?php if (!empty($p['contact'])): ?>
        <span style="cursor:pointer; color:#0d6efd;" data-bs-toggle="tooltip" data-bs-title="<?= htmlspecialchars($p['contact']) ?>">
          <i class="bi bi-telephone"></i>
        </span>
      <?php endif; ?>
    </td>
    <td class="text-center"><small><?= htmlspecialchars($p['date'] ?? '-') ?></small></td>
    <td class="text-center">
      <span style="cursor:pointer;display:inline-block;vertical-align:middle;"
      onclick='openUpdatePopup(
        <?= json_encode($p["trackingNo"] ?? "") ?>,
        <?= json_encode($p["status"] ?? "") ?>,
        <?= json_encode($p["message"] ?? "") ?>
      )'>
        <span class="badge bg-<?= $p['status'] === 'Bekliyor' ? 'warning' : ($p['status'] === 'Onaylandı' ? 'info' : 'success') ?>">
          <i class="bi <?= $p['status'] === 'Bekliyor' ? 'bi-clock' : ($p['status'] === 'Onaylandı' ? 'bi-check-circle' : 'bi-check2-all') ?>"></i> <?= htmlspecialchars($p['status']) ?>
          <i class="bi bi-pencil ms-1" style="font-size:0.95em;opacity:0.7;"></i>
        </span>
      </span>
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
    <td class="text-center">
      <?php if (!empty($p['assignedTo'])): ?>
        <span style="cursor:pointer; color:#0d6efd;" data-bs-toggle="tooltip" data-bs-title="<?= htmlspecialchars($p['assignedTo']) ?>">
          <i class="bi bi-person-workspace"></i>
        </span>
      <?php endif; ?>
    </td>
    <td class="text-center">
      <button type="button" class="btn btn-outline-info btn-sm detail-btn"
        onclick="openDetailCardbox('<?= htmlspecialchars($p['trackingNo']) ?>', this, event)"
        title="Detay">
        <i class="bi bi-search"></i> Detay
      </button>
    </td>
    <?php if ($currentUser && $currentUser['role'] === 'MainAdmin'): ?>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteProblem('<?= htmlspecialchars($p['trackingNo']) ?>')">
                <i class="bi bi-trash"></i> Sil
            </button>
        </td>
    <?php endif; ?>
</tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
      </div>
    </div>
    <script>
    // Tablo arama
    document.addEventListener('DOMContentLoaded', function() {
      var search = document.getElementById('tableSearch');
      if (search) {
        search.addEventListener('keyup', function() {
          var val = this.value.toLowerCase();
          var rows = document.querySelectorAll('#assignedTable tbody tr');
          rows.forEach(function(row) {
            row.style.display = row.textContent.toLowerCase().indexOf(val) > -1 ? '' : 'none';
          });
        });
      }
      // Bootstrap tooltip
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
      });
    });
    </script>
<?php elseif ($tab=='profile'): ?>
    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-6">
        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <h4 class="mb-3"><i class="bi bi-person"></i> Profilim</h4>
            <ul class="list-group mb-3">
                <li class="list-group-item"><b>Kullanıcı Adı:</b> <?= htmlspecialchars($currentUser['username']) ?></li>
                <li class="list-group-item"><b>Rol:</b> <?= htmlspecialchars($currentUser['role']) ?></li>
                <?php if (!empty($currentUser['profession'])): ?>
                <li class="list-group-item"><b>Meslek:</b> <?= htmlspecialchars($currentUser['profession']) ?></li>
                <?php endif; ?>
            </ul>
            <h5 class="mb-3">Şifre Değiştir</h5>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Mevcut Şifre</label>
                    <input type="password" name="old_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Yeni Şifre</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary"><i class="bi bi-key"></i> Değiştir</button>
            </form>
            <?php
            $successMsg = $errorMsg = '';
            if (isset($_POST['change_password'])) {
                $old = $_POST['old_password'] ?? '';
                $new = $_POST['new_password'] ?? '';
                if ($currentUser && isset($currentUser['password']) && $old === $currentUser['password']) {
                    foreach ($users as &$u) {
                        if ($u['username'] === $currentUser['username']) {
                            $u['password'] = $new;
                            break;
                        }
                    }
                    file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    $successMsg = 'Şifre başarıyla değiştirildi.';
                } else {
                    $errorMsg = 'Mevcut şifreniz yanlış.';
                }
            }
            if ($successMsg): ?>
                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                    <?= $successMsg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
            <?php elseif ($errorMsg): ?>
                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                    <?= $errorMsg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
<?php endif; ?>
</div>
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
        <div class="text-end mt-2">
    <form method="post" class="d-inline">
      <button type="submit" name="clear_notifs" class="btn btn-sm btn-outline-secondary">Tümünü Temizle</button>
    </form>
  </div>
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
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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
<!-- Güncelleme popup'ı: -->
<div id="updateOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9998;" onclick="closeUpdatePopup(event)"></div>
<div id="updatePopup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); min-width:340px; background:#fff; border-radius:16px; box-shadow:0 8px 40px rgba(0,0,0,0.18); z-index:9999; padding:2rem 2rem 1.5rem 2rem;">
    <form method="post" id="updateForm">
        <input type="hidden" name="update_trackingNo" id="update_trackingNo">
        <div class="mb-3">
            <label class="form-label">Durum</label>
            <select name="update_status" id="update_status" class="form-select" required>
                <option value="Bekliyor">Bekliyor</option>
                <option value="Onaylandı">Onaylandı</option>
                <option value="Tamamlandı">Tamamlandı</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Mesaj</label>
            <textarea name="update_message" id="update_message" class="form-control" rows="3" placeholder="Bu arıza için not veya bilgi mesajı yazın..."></textarea>
        </div>
        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" onclick="closeUpdatePopup()">İptal</button>
            <button type="submit" class="btn btn-success">Kaydet</button>
        </div>
    </form>
</div>
<script>
function openUpdatePopup(trackingNo, status, message) {
    document.getElementById('update_trackingNo').value = trackingNo;
    document.getElementById('update_status').value = status;
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
</script>
<script>
// PHP'den JS'ye arıza verilerini dizi (array) olarak aktar
const faultTypes = <?= json_encode($faultTypes, JSON_UNESCAPED_UNICODE) ?>;
const subFaultTypes = <?= json_encode($subFaultTypes, JSON_UNESCAPED_UNICODE) ?>;
const problemsData = <?php echo json_encode($problems, JSON_UNESCAPED_UNICODE); ?>;
let openCardbox = null;
function openDetailCardbox(trackingNo, btn, event) {
    if (event) event.stopPropagation();
    if (openCardbox) openCardbox.remove();
    const p = problemsData.find(x => x.trackingNo === trackingNo);
    if (!p) return;
    let html = `<button class='close-btn' onclick='this.parentElement.remove(); openCardbox=null;'>×</button>`;
    html += `<div class='mb-2'><b>Takip No:</b> ${p.trackingNo}</div>`;
    html += `<div class='mb-2'><b>Açıklama:</b><br><span>${p.description ?? '-'}</span></div>`;
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
    html += `<div class='mb-2'><b>Arıza Türü:</b> ${faultTypes[p.faultType] ?? p.faultType}</div>`;
    html += `<div class='mb-2'><b>Alt Tür:</b> ${subFaultTypes[p.subFaultType] ?? p.subFaultType}</div>`;
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
<script>
// Karanlık mod toggle
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
darkToggle.onclick = () => setDarkMode(!document.body.classList.contains('dark-mode'));
if (localStorage.getItem('darkMode') === '1') setDarkMode(true);
// Bildirim ve yardım butonları (modal açma placeholder)
</script>
<!-- Detay Modalı -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="detailModalLabel"><i class="bi bi-search"></i> Arıza Detayı</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs mb-3" id="detailTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="detay-tab" data-bs-toggle="tab" data-bs-target="#detailTabDetayPane" type="button" role="tab">Detay</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="dosya-tab" data-bs-toggle="tab" data-bs-target="#detailTabDosyaPane" type="button" role="tab">Dosya</button>
          </li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="detailTabDetayPane" role="tabpanel">
            <div id="detailTabDetay"></div>
          </div>
          <div class="tab-pane fade" id="detailTabDosyaPane" role="tabpanel">
            <div id="detailTabDosya"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
let msgBubbleEl = null;
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
</script>
<script>
const problemMessages = <?= json_encode(array_column($problems, 'message', 'trackingNo'), JSON_UNESCAPED_UNICODE) ?>;
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