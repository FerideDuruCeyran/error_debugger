<?php
session_start();
$page = 'tracking';
require_once 'config.php';

$result = null;
$error = '';

// Ortak arıza durumları
$faultStatuses = [
    'Bekliyor' => 'Bekliyor',
    'Onaylandı' => 'Onaylandı',
    'Tamamlandı' => 'Tamamlandı'
];
$faultStatusBadges = [
    'Bekliyor' => 'warning',
    'Onaylandı' => 'info',
    'Tamamlandı' => 'success'
];

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

// Arıza türleri dizisi (id => isim)
$faultTypeNames = [
    1 => "MAKİNE/TESİSAT",
    2 => "ELEKTRİK",
    3 => "İNŞAAT"
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trackingNo = trim($_POST['trackingNo'] ?? '');
    if ($trackingNo === '') {
        $error = 'Takip numarası giriniz.';
    } else {
        if (file_exists(PROBLEM_LOG_FILE)) {
            $lines = file(PROBLEM_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry && isset($entry['trackingNo']) && strtoupper($entry['trackingNo']) === strtoupper($trackingNo)) {
                    $result = $entry;
                    break;
                }
            }
            if (!$result) {
                $error = 'Bu takip numarasına ait kayıt bulunamadı.';
            }
        } else {
            $error = 'Henüz hiç arıza bildirimi yapılmamış.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Arıza Takip - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="https://upload.wikimedia.org/wikipedia/tr/d/dc/Akdeniz_%C3%9Cniversitesi_logosu.IMG_0838.png" class="akdeniz-logo" alt="Akdeniz Üniversitesi">
      Akdeniz Üniversitesi
    </a>
    <div class="d-flex ms-auto align-items-center gap-2">
      <button class="btn-icon" id="darkModeToggle" title="Karanlık Mod"><i class="bi bi-moon"></i></button>
      <button class="btn-icon position-relative" id="notifBtn" title="Bildirimler" data-bs-toggle="modal" data-bs-target="#notifModal"><i class="bi bi-bell"></i></button>
      <button class="btn-icon" id="helpBtn" title="Yardım" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="bi bi-question-circle"></i></button>
      <a class="btn btn-outline-light me-2" href="fault_form.php">Arıza Bildir</a>
      <a class="btn btn-outline-light me-2" href="tracking.php">Takip</a>
      <a class="btn btn-outline-light me-2" href="index.php"><i class="bi bi-house"></i> Ana Sayfa</a>
      <a class="btn btn-outline-light ms-2" href="login.php">Yönetici Girişi</a>
    </div>
  </div>
</nav>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="mb-4"><i class="bi bi-search"></i> Arıza Takip</h2>
                    <form method="post" class="mb-4">
                        <div class="mb-3">
                            <label class="form-label">Takip Numaranız</label>
                            <input type="text" name="trackingNo" class="form-control" value="<?= htmlspecialchars($_POST['trackingNo'] ?? '') ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Sorgula</button>
                    </form>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
                    <?php endif; ?>
                    <?php if ($result): ?>
                        <h3>Arıza Bilgileri</h3>
                        <ul class="list-group mb-3">
                            <li class="list-group-item"><b>Birim:</b> <?= htmlspecialchars($result['department'] ?? '-') ?></li>
                            <li class="list-group-item"><b>Arıza Türü:</b> <?= htmlspecialchars($faultTypeNames[$result['faultType']] ?? $result['faultType'] ?? '-') ?></li>
                            <li class="list-group-item"><b>Alt Tür:</b> <?= htmlspecialchars($subFaultTypes[$result['subFaultType']] ?? '-') ?></li>
                            <li class="list-group-item"><b>Durum:</b> <?= htmlspecialchars($result['status'] ?? '-') ?></li>
                            <li class="list-group-item"><b>Tarih:</b> <span class="server-date" data-server-date="<?= htmlspecialchars($result['date'] ?? '-') ?>"><?= htmlspecialchars($result['date'] ?? '-') ?></span></li>
                            <li class="list-group-item"><b>Açıklama:</b> <?= htmlspecialchars($result['description'] ?? '-') ?></li>
                            <li class="list-group-item"><b>Takip No:</b> <?= htmlspecialchars($result['trackingNo'] ?? '-') ?></li>
                            <li class="list-group-item"><b>İletişim:</b> <i class="bi bi-telephone"></i> <?php $c = $result['contact'] ?? ''; echo $c ? str_repeat('*', max(0, strlen($c)-4)) . substr($c, -4) : '-'; ?></li>
                            <li class="list-group-item"><b>Teknisyen:</b> <?php
                                if (!empty($result['assignedTo'])) {
                                    echo htmlspecialchars($result['assignedTo']);
                                } else {
                                    echo 'Bir atama yapılmadı';
                                }
                            ?></li>
                            <?php if (!empty($result['filePath'])): ?>
                                <li class="list-group-item"><b>Dosya:</b> <a href="uploads/<?= htmlspecialchars(basename($result['filePath'])) ?>" target="_blank"><i class="bi bi-file-earmark-arrow-down"></i> Dosyayı Görüntüle</a></li>
                            <?php endif; ?>
                            <?php /* IP bilgisi gösterilmeyecek */ ?>
                            <?php if (!empty($result['user'])): ?>
                                <li class="list-group-item"><b>Kullanıcı:</b> <?= htmlspecialchars($result['user']) ?></li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
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
          <li class="list-group-item"><i class="bi bi-info-circle text-primary"></i> Hoşgeldiniz! Arıza bildirimi yapmak için yukarıdaki butonları kullanabilirsiniz.</li>
          <li class="list-group-item"><i class="bi bi-check-circle text-success"></i> Bildirimleriniz burada görünecek.</li>
          <li class="list-group-item"><i class="bi bi-chat-dots text-info"></i> Destek için iletişime geçebilirsiniz.</li>
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
          <li><b>Arıza bildirimi nasıl yapılır?</b><br>"Arıza Bildir" butonunu kullanarak formu doldurabilirsiniz.</li>
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
// Karanlık mod toggle
const darkToggle = document.getElementById('darkModeToggle');
function setDarkMode(on) {
  if (on) {
    document.body.classList.add('dark-mode');
    darkToggle.innerHTML = '<i class=\"bi bi-brightness-high\"></i>';
    localStorage.setItem('darkMode', '1');
  } else {
    document.body.classList.remove('dark-mode');
    darkToggle.innerHTML = '<i class=\"bi bi-moon\"></i>';
    localStorage.setItem('darkMode', '0');
  }
}
darkToggle.onclick = () => setDarkMode(!document.body.classList.contains('dark-mode'));
if (localStorage.getItem('darkMode') === '1') setDarkMode(true);

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

// Local time gösterimi
function convertToLocalTime() {
  document.querySelectorAll('.server-date').forEach(function(el) {
    var serverDate = el.getAttribute('data-server-date');
    if (serverDate) {
      // Sunucu tarihi formatı: 'YYYY-MM-DD HH:mm:ss'
      var dateParts = serverDate.split(' ');
      if (dateParts.length === 2) {
        var date = dateParts[0].split('-');
        var time = dateParts[1].split(':');
        // new Date(year, monthIndex, day, hour, minute, second)
        var jsDate = new Date(
          Number(date[0]),
          Number(date[1]) - 1,
          Number(date[2]),
          Number(time[0]),
          Number(time[1]),
          Number(time[2])
        );
        el.textContent = jsDate.toLocaleString();
      }
    }
  });
}
convertToLocalTime();
</script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</body>
</html> 