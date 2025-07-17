<?php
session_start();
require_once 'config.php';
require_once 'log_error.php';


// Ortak arıza durumları
$faultStatuses = [
    'Bekliyor' => 'Bekliyor',
    'Onaylandı' => 'Onaylandı',
    'Tamamlandı' => 'Tamamlandı'
];

// Dinamik tür ve alt türler
$typesFile = 'types.json';
$types = file_exists($typesFile) ? json_decode(file_get_contents($typesFile), true) : [];

$success = false;
$error = '';
$trackingNo = '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // reCAPTCHA validation
    $recaptcha_secret = RECAPTCHA_SECRET_KEY;
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    
    if (empty($recaptcha_response)) {
        $error = 'Lütfen "Ben robot değilim" kutucuğunu işaretleyin.';
    }

    $department = trim($_POST['department'] ?? '');
    $subFaultType = trim($_POST['subFaultType'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $description = trim($_POST['detailedDescription'] ?? '');
    $date = date('Y-m-d H:i:s', time() + 3 * 3600); // GMT+3
    $status = $faultStatuses['Bekliyor'];
    $trackingNo = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    $filePath = '';
    $missing = [];
    if ($department === '') $missing[] = 'Birim/Tür';
    if ($contact === '') $missing[] = 'İletişim Bilgisi';
    if ($description === '') $missing[] = 'Detaylı Tanımlama';
    if ($missing) {
        $error = 'Lütfen şu alanları doldurun: ' . implode(', ', $missing);

    } else {
        $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
        $recaptcha_data = [
            'secret' => $recaptcha_secret,
            'response' => $recaptcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        // Simplified reCAPTCHA verification
        $recaptcha_result = false;
        
        // Try cURL first (more reliable)
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $recaptcha_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($recaptcha_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            if (defined('SSL_CA_BUNDLE')) {
                curl_setopt($ch, CURLOPT_CAINFO, SSL_CA_BUNDLE);
            }
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            
            $recaptcha_result = curl_exec($ch);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                error_log('cURL error: ' . $curl_error);
            }
        }
        
        // Fallback to file_get_contents if cURL fails
        if ($recaptcha_result === false) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => http_build_query($recaptcha_data),
                    'timeout' => 15,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'cafile' => SSL_CA_BUNDLE
                ]
            ]);
            
            $recaptcha_result = @file_get_contents($recaptcha_url, false, $context);
        }
        
        $recaptcha_json = json_decode($recaptcha_result, true);
        
        // Debug logging
        error_log('reCAPTCHA response: ' . $recaptcha_result);
        error_log('reCAPTCHA JSON: ' . print_r($recaptcha_json, true));
        
        if (!$recaptcha_json || !isset($recaptcha_json['success']) || !$recaptcha_json['success']) {
            // Log the failure for debugging
            error_log('reCAPTCHA verification failed. Response: ' . $recaptcha_result);
            if (isset($recaptcha_json['error-codes'])) {
                error_log('reCAPTCHA errors: ' . implode(', ', $recaptcha_json['error-codes']));
            }
            
            $error = 'reCAPTCHA doğrulaması başarısız. Lütfen tekrar deneyin.';
        }
        
        // Continue with form processing only if reCAPTCHA passed
        if (empty($error)) {
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir);
                $fileName = basename($_FILES['file']['name']);
                $filePath = $uploadDir . $trackingNo . '_' . $fileName;
                move_uploaded_file($_FILES['file']['tmp_name'], $filePath);
            }
            // Arıza türüne göre ilgili müdürü bul
            $assigned_admin_id = null;
            if ($department && isset($types[$department])) {
                $target_department = $department;
                $users = json_decode(file_get_contents('users.json'), true);
                foreach ($users as $user) {
                    if (($user['role'] === 'AltAdmin' || $user['role'] === 'Mudur') && $user['department'] === $target_department) {
                        $assigned_admin_id = $user['id'];
                        break;
                    }
                }
            }
            $entry = [
                'department' => $department,
                'subFaultType' => $subFaultType,
                'date' => $date,
                'status' => $status,
                'trackingNo' => $trackingNo,
                'contact' => $contact,
                'description' => $description,
                'filePath' => $filePath,
                'userAgent' => $userAgent,
                'ip' => $ip,
                'user' => 'anonim',
                'assigned_admin_id' => $assigned_admin_id
            ];
            log_problem($entry);
            $success = true;
        }
    }
}
$page = 'fault_form';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Arıza Bildir - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
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
      <button class="btn-icon" id="darkModeToggle" title="Karanlık Mod"><i class="bi bi-moon"></i></button>
      <button class="btn-icon position-relative" id="notifBtn" title="Bildirimler" data-bs-toggle="modal" data-bs-target="#notifModal"><i class="bi bi-bell"></i><span id="notifDot" class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle d-none"></span></button>
      <button class="btn-icon" id="helpBtn" title="Yardım" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="bi bi-question-circle"></i></button>
      <a class="btn btn-outline-light me-2<?= $page=='fault_form'?' active':'' ?>" href="fault_form.php">Arıza Bildir</a>
      <a class="btn btn-outline-light me-2<?= $page=='tracking'?' active':'' ?>" href="tracking.php">Takip</a>
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
                    <h2 class="mb-4">Arıza Bildirim Formu</h2>
                    <?php if ($success): ?>
                        <div class="alert alert-success">Arıza bildiriminiz başarıyla alındı. Takip Numaranız: <b><?= htmlspecialchars($trackingNo) ?></b></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label" for="department">Birim/Tür</label>
                            <select name="department" id="department" class="form-select" required>
                                <option value="">Seçiniz</option>
                                <?php foreach ($types as $type => $subs): ?>
                                  <option value="<?= htmlspecialchars($type) ?>" <?= (isset($_POST['department']) && $_POST['department']==$type)?'selected':'' ?>><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3" id="subFaultTypeBox">
                            <label class="form-label" for="subFaultType">Alt Tür</label>
                            <select name="subFaultType" id="subFaultType" class="form-select">
                                <option value="">Seçiniz</option>
                                <?php
                                $selectedType = $_POST['department'] ?? '';
                                if ($selectedType && isset($types[$selectedType])) {
                                  foreach ($types[$selectedType] as $sub) {
                                    echo '<option value="'.htmlspecialchars($sub).'"'.((isset($_POST['subFaultType']) && $_POST['subFaultType']==$sub)?' selected':'').'>'.htmlspecialchars($sub).'</option>';
                                  }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="detailedDescription">Detaylı Tanımlama</label>
                            <textarea name="detailedDescription" id="detailedDescription" class="form-control" rows="4" placeholder="Arızanın tüm detaylarını, varsa bilgisayar veya ekipman özelliklerini, gözlemlerinizi ve açıklamalarınızı buraya yazınız." required><?= htmlspecialchars($_POST['detailedDescription'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="file">Dosya Ekle</label>
                            <input type="file" name="file" id="file" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="contact">İletişim Bilgisi</label>
                            <input type="text" name="contact" id="contact" class="form-control" value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <div class="g-recaptcha" data-sitekey="<?= RECAPTCHA_SITE_KEY ?>"></div>
                        </div>
                        <button type="submit" class="btn btn-primary">Gönder</button>
                    </form>
                    <hr>
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
            <label class="form-label" for="feedback">Görüşünüz</label>
            <textarea class="form-control" name="feedback" id="feedback" rows="2" required></textarea>
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
// Bildirimleri temizle
function clearNotifs() {
  document.querySelector('#notifModal .list-group').innerHTML = '<li class="list-group-item text-muted">Tüm bildirimler temizlendi.</li>';
}
</script>
<script>
const feedbackForm = document.getElementById('feedbackForm');
if (feedbackForm) {
  feedbackForm.onsubmit = function(e) {
    e.preventDefault();
    document.getElementById('feedbackMsg').innerHTML = '<span class="text-success">Teşekkürler, geri bildiriminiz alındı.</span>';
    feedbackForm.reset();
  };
}
</script>
<script>
const types = <?= json_encode($types, JSON_UNESCAPED_UNICODE) ?>;
document.addEventListener('DOMContentLoaded', function() {
  var typeSel = document.getElementById('department');
  var subSel = document.getElementById('subFaultType');
  function updateSubtypes() {
    var selected = typeSel.value;
    subSel.innerHTML = '<option value="">Seçiniz</option>';
    if (types[selected]) {
      types[selected].forEach(function(sub) {
        var opt = document.createElement('option');
        opt.value = sub;
        opt.textContent = sub;
        subSel.appendChild(opt);
      });
    }
  }
  typeSel.addEventListener('change', updateSubtypes);
  updateSubtypes();
});
</script>
</body>
</html> 