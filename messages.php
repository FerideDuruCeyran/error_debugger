<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$usersFile = 'users.json';
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
$currentUser = null;
foreach ($users as $u) {
    if ($u['username'] === $_SESSION['user']) {
        $currentUser = $u;
        break;
    }
}
if (!$currentUser) {
    header('Location: login.php');
    exit;
}
$role = $currentUser['role'];
$allUsers = array_column($users, 'username');
// Mesajlar dosyası
$messagesFile = 'messages.json';
if (!file_exists($messagesFile)) file_put_contents($messagesFile, '[]');
$messages = json_decode(file_get_contents($messagesFile), true);

$toList = [];
if ($currentUser['role'] === 'Admin') {
    // Kendi teknik personelleri
    foreach ($users as $u) {
        if ($u['role'] === 'TeknikPersonel' && $u['department'] === $currentUser['department']) {
            $toList[] = $u;
        }
    }
    // Diğer adminler
    foreach ($users as $u) {
        if ($u['role'] === 'Admin' && $u['username'] !== $currentUser['username']) {
            $toList[] = $u;
        }
    }
    // MainAdmin/GenelAdmin
    foreach ($users as $u) {
        if (in_array($u['role'], ['MainAdmin', 'GenelAdmin'])) {
            $toList[] = $u;
        }
    }
} elseif (in_array($currentUser['role'], ['MainAdmin', 'GenelAdmin'])) {
    // Main admin sadece adminlere mesaj atabilir
    foreach ($users as $u) {
        if ($u['role'] === 'Admin') {
            $toList[] = $u;
        }
    }
} elseif ($currentUser['role'] === 'TeknikPersonel') {
    // Teknik personel: kendi adminine ve diğer teknik personellere mesaj atabilir
    // Kendi adminini bul
    $myAdmin = null;
    foreach ($users as $u) {
        if ($u['role'] === 'Admin' && isset($u['department']) && $u['department'] === $currentUser['department']) {
            $myAdmin = $u;
            break;
        }
    }
    if ($myAdmin) {
        $toList[] = $myAdmin;
    }
    // Diğer teknik personeller
    foreach ($users as $u) {
        if ($u['role'] === 'TeknikPersonel' && $u['username'] !== $currentUser['username']) {
            $toList[] = $u;
        }
    }
} else {
    // Diğer roller için mevcut davranış (ör. farklı roller)
    foreach ($users as $u) {
        if ($u['username'] !== $currentUser['username']) {
            $toList[] = $u;
        }
    }
}

// Mesaj gönderme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['to'], $_POST['message'])) {
    $to = trim($_POST['to']);
    $msg = trim($_POST['message']);
    if ($to && $msg && in_array($to, $allUsers)) {
        $messages[] = [
            'from' => $currentUser['username'],
            'to' => $to,
            'message' => $msg,
            'date' => date('Y-m-d H:i:s'),
            'read' => false
        ];
        file_put_contents($messagesFile, json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        // Bildirim ekle
        $notifFile = 'bildirimler/notifications_' . $to . '.json';
        $notifs = file_exists($notifFile) ? json_decode(file_get_contents($notifFile), true) : [];
        $notifs[] = [ 'msg' => 'Yeni bir mesajınız var.', 'date' => date('Y-m-d H:i') ];
        file_put_contents($notifFile, json_encode($notifs, JSON_UNESCAPED_UNICODE));
        header('Location: messages.php?user=' . urlencode($to));
        exit;
    }
}
// Mesaj silme (geri alma)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delIndex = (int)$_GET['delete'];
    if (isset($messages[$delIndex]) && $messages[$delIndex]['from'] === $currentUser['username']) {
        array_splice($messages, $delIndex, 1);
        file_put_contents($messagesFile, json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        header('Location: messages.php?user=' . urlencode($activeUser));
        exit;
    }
}
// Sohbet edilebilecek kullanıcılar
$chatUsers = [];
if ($role === 'MainAdmin') {
    foreach ($users as $u) if ($u['role'] === 'Admin') $chatUsers[] = $u['username'];
} elseif ($role === 'Admin') {
    foreach ($users as $u) if ($u['role'] === 'MainAdmin') $chatUsers[] = $u['username'];
    foreach ($users as $u) if ($u['role'] === 'TeknikPersonel') $chatUsers[] = $u['username'];
} elseif ($role === 'TeknikPersonel') {
    foreach ($users as $u) if ($u['role'] === 'Admin') $chatUsers[] = $u['username'];
}
// Aktif sohbet
$activeUser = $_GET['user'] ?? ($chattedUserObjs[0]['username'] ?? null);
// Mesaj geçmişi
$chatHistory = [];
if ($activeUser) {
    foreach ($messages as $m) {
        if (($m['from'] === $currentUser['username'] && $m['to'] === $activeUser) ||
            ($m['from'] === $activeUser && $m['to'] === $currentUser['username'])) {
            $chatHistory[] = $m;
        }
    }
    // Okundu işaretle
    foreach ($messages as &$m) {
        if ($m['from'] === $activeUser && $m['to'] === $currentUser['username']) {
            $m['read'] = true;
        }
    }
    file_put_contents($messagesFile, json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
// Sohbet edilen kullanıcıları bul
$myUsername = $currentUser['username'];
$chattedUsers = [];
foreach ($messages as $msg) {
    if ($msg['from'] === $myUsername && $msg['to'] !== $myUsername) {
        $chattedUsers[$msg['to']] = true;
    }
    if ($msg['to'] === $myUsername && $msg['from'] !== $myUsername) {
        $chattedUsers[$msg['from']] = true;
    }
}
// Sohbet edilen kullanıcıların user objelerini bul
$chattedUserObjs = [];
if (in_array($currentUser['role'], ['MainAdmin', 'GenelAdmin'])) {
    // Main admin için sadece adminlerle olan sohbetler
    foreach ($users as $u) {
        if ($u['role'] === 'Admin' && isset($chattedUsers[$u['username']])) {
            $chattedUserObjs[] = $u;
        }
    }
} else {
    foreach ($users as $u) {
        if (isset($chattedUsers[$u['username']])) {
            $chattedUserObjs[] = $u;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Mesajlar - Akdeniz Üniversitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
    .chat-list { max-height: 70vh; overflow-y: auto; }
    .chat-bubble { border-radius: 16px; padding: 0.7em 1.2em; margin-bottom: 8px; max-width: 70%; display: inline-block; }
    .chat-bubble.me { background: #e3f0fa; align-self: flex-end; }
    .chat-bubble.them { background: #f7f7f7; align-self: flex-start; }
    .chat-date { font-size: 0.85em; color: #888; margin-top: 2px; }
    .chat-container { display: flex; flex-direction: column; gap: 0.5em; }
    .chat-user { font-weight: 500; }
    .chat-active { background: #e3f0fa; border-radius: 8px; }
    /* Dark mode için mesaj kutuları */
    body.dark-mode .chat-bubble.me, body.dark-mode .chat-bubble.them { background: #fff; color: #222; }
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
    <?php
    $panel = 'index.php';
    if (isset($currentUser['role'])) {
      if ($currentUser['role'] === 'MainAdmin') $panel = 'main_admin.php';
      elseif ($currentUser['role'] === 'Admin') $panel = 'admin.php';
      elseif ($currentUser['role'] === 'TeknikPersonel') $panel = 'teknik_personel.php';
    }
    ?>
    <div class="d-flex ms-auto align-items-center gap-2">
      <?php
      $panel = 'index.php';
      if (isset($currentUser['role'])) {
        if ($currentUser['role'] === 'MainAdmin') $panel = 'main_admin.php';
        elseif ($currentUser['role'] === 'Admin') $panel = 'admin.php';
        elseif ($currentUser['role'] === 'TeknikPersonel') $panel = 'teknik_personel.php';
      }
      ?>
      <a class="btn btn-outline-light me-2" href="javascript:window.history.back()"><i class="bi bi-arrow-left"></i> Geri</a>
      <button class="btn-icon" id="darkModeToggle" title="Karanlık Mod"><i class="bi bi-moon"></i></button>
      <button class="btn-icon position-relative" id="notifBtn" title="Bildirimler" data-bs-toggle="modal" data-bs-target="#notifModal"><i class="bi bi-bell"></i></button>
      <button class="btn-icon" id="helpBtn" title="Yardım" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="bi bi-question-circle"></i></button>
      <a class="btn btn-outline-light me-2" href="messages.php"><i class="bi bi-chat-dots"></i> Mesajlar</a>
      <a class="btn btn-outline-light ms-2" href="logout.php">Çıkış</a>
    </div>
  </div>
</nav>
<div class="container">
  <div class="row">
    <div class="col-md-4 mb-3">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">Sohbetler</div>
        <ul class="list-group chat-list">
          <?php if (empty($chattedUserObjs)): ?>
            <li class="list-group-item text-muted">Henüz mesajlaşma yok.</li>
          <?php else: ?>
            <?php foreach ($chattedUserObjs as $u): ?>
              <a href="messages.php?user=<?= urlencode($u['username']) ?>" class="list-group-item list-group-item-action<?= $activeUser===$u['username']?' chat-active':'' ?>">
                <span class="chat-user"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role']) ?>)</span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="col-md-8 mb-3">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-primary text-white">
          <?php if ($activeUser): ?>
            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($activeUser) ?> ile Sohbet
          <?php else: ?>
            Sohbet Seçin
          <?php endif; ?>
        </div>
        <div class="card-body chat-container" style="min-height:350px; max-height:60vh; overflow-y:auto;">
          <?php if ($activeUser): ?>
            <?php foreach ($chatHistory as $i => $msg): ?>
              <div class="chat-bubble <?= $msg['from']===$currentUser['username']?'me':'them' ?>">
                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                <div class="chat-date text-end">
                  <i class="bi bi-clock"></i> <?= htmlspecialchars($msg['date']) ?>
                  <?php if ($msg['from'] === $currentUser['username']): ?>
                    <a href="messages.php?user=<?= urlencode($activeUser) ?>&delete=<?= array_search($msg, $messages) ?>" class="btn btn-sm btn-outline-danger ms-2" onclick="return confirm('Mesajı geri almak istediğinize emin misiniz?')"><i class="bi bi-x-circle"></i> Geri Al</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($chatHistory)): ?>
              <div class="text-muted">Henüz mesaj yok.</div>
            <?php endif; ?>
          <?php else: ?>
            <div class="text-muted">Sohbet seçin.</div>
          <?php endif; ?>
        </div>
        <?php if ($activeUser): ?>
        <div class="card-footer bg-white">
          <form method="post" class="d-flex gap-2">
            <select name="to" class="form-select" required>
              <option value="">Kime mesaj göndereceksiniz?</option>
              <?php foreach ($toList as $u): ?>
                <option value="<?= htmlspecialchars($u['username']) ?>">
                  <?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="message" class="form-control" placeholder="Mesajınızı yazın..." required autocomplete="off">
            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>
          </form>
        </div>
        <?php endif; ?>
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
    darkToggle.innerHTML = '<i class=\"bi bi-brightness-high\"></i>';
    localStorage.setItem('darkMode', '1');
  } else {
    document.body.classList.remove('dark-mode');
    darkToggle.innerHTML = '<i class=\"bi bi-moon\"></i>';
    localStorage.setItem('darkMode', '0');
  }
}
darkToggle.onclick = () => setDarkMode(!document.body.classList.contains('dark-mode'));

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
// Navbar'da bildirim noktası ve sayı
</script>
</body>
</html> 