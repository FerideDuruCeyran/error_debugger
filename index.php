<?php
session_start();
$page = 'index';
if (isset($_SESSION['user'])) {
    $usersFile = 'users.json';
    $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
    $currentUser = null;
    foreach ($users as $u) {
        if ($u['username'] === $_SESSION['user']) {
            $currentUser = $u;
            break;
        }
    }
    if ($currentUser) {
        if ($currentUser['role'] === 'MainAdmin') {
            header('Location: main_admin.php');
            exit;
        } elseif ($currentUser['role'] === 'Admin') {
            header('Location: admin.php');
            exit;
        } elseif ($currentUser['role'] === 'TeknikPersonel') {
            header('Location: teknik_personel.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Arıza Takip Sistemi - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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
      <a class="btn btn-outline-light me-2" href="<?= $panel ?>"><i class="bi bi-house"></i> Ana Sayfa</a>
      <a class="btn btn-outline-light ms-2" href="login.php">Yönetici Girişi</a>
    </div>
  </div>
</nav>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h2 class="mb-4">Akdeniz Üniversitesi Arıza Takip Sistemi</h2>
                    <p class="lead">Hoşgeldiniz! Arıza bildirimi yapmak veya mevcut bildiriminizi takip etmek için yukarıdaki butonları kullanabilirsiniz.</p>
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
// Geri bildirim formu
const feedbackForm = document.getElementById('feedbackForm');
if (feedbackForm) {
  feedbackForm.onsubmit = function(e) {
    e.preventDefault();
    document.getElementById('feedbackMsg').innerHTML = '<span class="text-success">Teşekkürler, geri bildiriminiz alındı.</span>';
    feedbackForm.reset();
  };
}
</script>
</body>
</html> 