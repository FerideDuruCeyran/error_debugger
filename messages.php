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
$activeUser = $_GET['user'] ?? ($chatUsers[0] ?? null);
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
</style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="https://upload.wikimedia.org/wikipedia/tr/d/dc/Akdeniz_%C3%9Cniversitesi_logosu.IMG_0838.png" class="akdeniz-logo" alt="Akdeniz Üniversitesi">
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
      <button class="btn-icon" id="darkModeToggle" title="Karanlık Mod"><i class="bi bi-moon"></i></button>
      <a class="btn btn-outline-light ms-2" href="<?= $panel ?>"><i class="bi bi-arrow-left"></i> Geri</a>
      <a class="btn btn-outline-light ms-2" href="logout.php"><i class="bi bi-box-arrow-right"></i> Çıkış</a>
    </div>
  </div>
</nav>
<div class="container">
  <div class="row">
    <div class="col-md-4 mb-3">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">Sohbetler</div>
        <ul class="list-group list-group-flush chat-list">
          <?php foreach ($chatUsers as $u): ?>
            <a href="messages.php?user=<?= urlencode($u) ?>" class="list-group-item list-group-item-action<?= $activeUser===$u?' chat-active':'' ?>">
              <span class="chat-user"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($u) ?></span>
              <?php
              $unread = 0;
              foreach ($messages as $m) {
                if ($m['from'] === $u && $m['to'] === $currentUser['username'] && !$m['read']) $unread++;
              }
              if ($unread): ?>
                <span class="badge bg-danger ms-2"><?= $unread ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
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
            <input type="hidden" name="to" value="<?= htmlspecialchars($activeUser) ?>">
            <input type="text" name="message" class="form-control" placeholder="Mesajınızı yazın..." required autocomplete="off">
            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>
          </form>
        </div>
        <?php endif; ?>
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
</script>
</body>
</html> 