<?php
session_start();
require_once 'config.php';
require_once 'log_error.php';

$success = false;
$error = '';
$trackingNo = '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $faultType = trim($_POST['faultType'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $specs = trim($_POST['specs'] ?? '');
    $date = date('Y-m-d H:i:s');
    $status = 'Bekliyor';
    $trackingNo = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    $filePath = '';
    $missing = [];
    if ($faultType === '') $missing[] = 'Arıza Türü';
    if ($title === '') $missing[] = 'Arıza Başlığı';
    if ($content === '') $missing[] = 'Arıza İçeriği';
    if ($department === '') $missing[] = 'Birim';
    if ($contact === '') $missing[] = 'İletişim Bilgisi';
    if ($specs === '') $missing[] = 'Bilgisayar Özellikleri';
    if ($missing) {
        $error = 'Lütfen şu alanları doldurun: ' . implode(', ', $missing);
    } else {
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir);
            $fileName = basename($_FILES['file']['name']);
            $filePath = $uploadDir . $trackingNo . '_' . $fileName;
            move_uploaded_file($_FILES['file']['tmp_name'], $filePath);
        }
        $entry = [
            'faultType' => $faultType,
            'title' => $title,
            'content' => $content,
            'filePath' => $filePath,
            'department' => $department,
            'date' => $date,
            'status' => $status,
            'trackingNo' => $trackingNo,
            'contact' => $contact,
            'specs' => $specs,
            'userAgent' => $userAgent,
            'ip' => $ip,
            'user' => 'anonim'
        ];
        log_problem($entry);
        $success = true;
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
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="https://upload.wikimedia.org/wikipedia/tr/d/dc/Akdeniz_%C3%9Cniversitesi_logosu.IMG_0838.png" class="akdeniz-logo" alt="Akdeniz Üniversitesi">
      Akdeniz Üniversitesi
    </a>
    <div>
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
                            <label class="form-label">Arıza Türü</label>
                            <select name="faultType" id="faultType" class="form-select" required onchange="document.getElementById('otherTypeBox').style.display = this.value==='Diğer' ? 'block' : 'none';">
                                <option value="">Seçiniz</option>
                                <option value="Bilgisayar">Bilgisayar</option>
                                <option value="Ağ">Ağ</option>
                                <option value="Yazıcı">Yazıcı</option>
                                <option value="Elektrik">Elektrik</option>
                                <option value="İnternet">İnternet</option>
                                <option value="Donanım">Donanım</option>
                                <option value="Yazılım">Yazılım</option>
                                <option value="Ortam">Ortam</option>
                                <option value="Diğer">Diğer</option>
                            </select>
                        </div>
                        <div class="mb-3" id="otherTypeBox" style="display:none;">
                            <label class="form-label">Diğer Arıza Türü</label>
                            <input type="text" name="otherFaultType" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Arıza Başlığı</label>
                            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Arıza İçeriği</label>
                            <textarea name="content" class="form-control" rows="4" required><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dosya Ekle</label>
                            <input type="file" name="file" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bilgisayar Özellikleri</label>
                            <textarea name="specs" class="form-control" rows="2" placeholder="Marka/Model, İşletim Sistemi, RAM, vb." required><?= htmlspecialchars($_POST['specs'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Birim</label>
                            <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">İletişim Bilgisi</label>
                            <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Gönder</button>
                    </form>
                    <hr>
                    <div class="text-muted small">
                        <b>Tarayıcı:</b> <?= htmlspecialchars($userAgent) ?><br>
                        <b>IP:</b> <?= htmlspecialchars($ip) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('faultType');
    var box = document.getElementById('otherTypeBox');
    if (sel.value === 'Diğer') box.style.display = 'block';
});
</script>
</body>
</html> 