<?php
session_start();
require_once 'config.php';
require_once 'log_error.php';

// Birimler (görselden alınan örnekler)
$departments = [
    "Diş Hekimliği Fakültesi", "Eczacılık Fakültesi", "Edebiyat Fakültesi", "Eğitim Fakültesi", "Fen Fakültesi", "Güzel Sanatlar Fakültesi", "Hemşirelik Fakültesi", "Hukuk Fakültesi", "İktisadi ve İdari Bilimler Fakültesi", "İlahiyat Fakültesi", "İletişim Fakültesi", "Kemer Denizcilik Fakültesi", "Kumluca Sağlık Bilimleri Fakültesi", "Hukuk Müşavirliği", "Ziraat Fakültesi", "Adalet Meslek Yüksekokulu", "Alanya Meslek Yüksekokulu", "Demre Dr. Hasan Ünal Meslek Yüksekokulu", "Elmalı Meslek Yüksekokulu", "Finike Meslek Yüksekokulu", "Gastronomi ve Mutfak Sanatları Meslek Yüksekokulu", "Korkuteli Meslek Yüksekokulu", "Kumluca Meslek Yüksekokulu", "Manavgat Meslek Yüksekokulu", "Serik Meslek Yüksekokulu", "Sosyal Bilimler Meslek Yüksekokulu", "Teknik Bilimler Meslek Yüksekokulu", "Turizm İşletmeciliği ve Otelcilik Yüksekokulu", "Antalya Devlet Konservatuvarı", "Yabancı Diller Yüksekokulu", "Akdeniz Uygarlıkları Araştırma Enstitüsü", "Eğitim Bilimleri Enstitüsü", "Fen Bilimleri Enstitüsü", "Güzel Sanatlar Enstitüsü", "Prof.Dr.Tuncer Karpuzoğlu Organ Nakli Enstitüsü", "Sağlık Bilimleri Enstitüsü", "Sosyal Bilimler Enstitüsü", "Atatürk İlkeleri ve İnkılap Tarihi Bölüm Başkanlığı", "Beden Eğitimi ve Spor Bölüm Başkanlığı", "Enformatik Bölüm Başkanlığı", "Güzel Sanatlar Bölüm Başkanlığı", "Türk Dili Bölüm Başkanlığı", "Hukuk Müşavirliği", "Kütüphane ve Dokümantasyon Daire Başkanlığı", "Öğrenci İşleri Daire Başkanlığı", "Sağlık Kültür ve Spor Daire Başkanlığı", "Strateji Geliştirme Daire Başkanlığı", "Uluslararası İlişkiler Ofisi", "Yapı İşleri ve Teknik Daire Başkanlığı", "Basın Yayın ve Halkla İlişkiler Müdürlüğü", "Döner Sermaye İşletme Müdürlüğü", "Hastane", "İdari ve Mali İşler Daire Başkanlığı", "İnsan Kaynakları Daire Başkanlığı", "Kariyer Planlama ve Mezun İzleme Uygulama ve Araştırma Merkezi", "Kütüphane ve Dokümantasyon Daire Başkanlığı", "Öğrenci İşleri Daire Başkanlığı", "Sağlık Kültür ve Spor Daire Başkanlığı", "Strateji Geliştirme Daire Başkanlığı", "Teknoloji Transfer Ofisi", "TÖMER", "Yabancı Diller Yüksekokulu", "Diğer (liste dışı birim)"
];

// Arıza türleri ve alt türler (örnekler)
$faultTypes = [
    ["id"=>1, "name"=>"MAKİNE/TESİSAT"],
    ["id"=>2, "name"=>"ELEKTRİK"],
    ["id"=>3, "name"=>"İNŞAAT"]
];
$subFaultTypes = [
    ["id"=>1, "name"=>"Temiz Su Sistemi", "parent_id"=>1],
    ["id"=>2, "name"=>"Pis Su Sistemi", "parent_id"=>1],
    ["id"=>3, "name"=>"Buhar Sistemi", "parent_id"=>1],
    ["id"=>4, "name"=>"Yangın Sistemi", "parent_id"=>1],
    ["id"=>5, "name"=>"Klima Sistemi", "parent_id"=>1],
    ["id"=>6, "name"=>"Havalandırma", "parent_id"=>1],
    ["id"=>7, "name"=>"Makine/Teknik", "parent_id"=>1],
    ["id"=>8, "name"=>"Yangın Algılama", "parent_id"=>2],
    ["id"=>9, "name"=>"Aydınlatma", "parent_id"=>2],
    ["id"=>10, "name"=>"Enerji Dağıtım", "parent_id"=>2],
    ["id"=>11, "name"=>"Enerji Kaynağı", "parent_id"=>2],
    ["id"=>12, "name"=>"Kampüs Aydınlatma", "parent_id"=>2],
    ["id"=>13, "name"=>"Elektrik Raporu", "parent_id"=>2],
    ["id"=>14, "name"=>"Çatı/Duvar", "parent_id"=>3],
    ["id"=>15, "name"=>"Boya", "parent_id"=>3],
    ["id"=>16, "name"=>"Kapı/Pencere", "parent_id"=>3],
    ["id"=>17, "name"=>"Zemin Kaplama", "parent_id"=>3],
    ["id"=>18, "name"=>"Kaynak/Montaj", "parent_id"=>3],
    ["id"=>19, "name"=>"Nem ve Küf", "parent_id"=>3],
    ["id"=>20, "name"=>"İnşaat Raporu", "parent_id"=>3]
];

$success = false;
$error = '';
$trackingNo = '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $faultType = trim($_POST['faultType'] ?? '');
    $subFaultType = trim($_POST['subFaultType'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $detailedDescription = trim($_POST['detailedDescription'] ?? '');
    $date = date('Y-m-d H:i:s');
    $status = 'Bekliyor';
    $trackingNo = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    $filePath = '';
    $missing = [];
    if ($faultType === '') $missing[] = 'Arıza Türü';
    if ($department === '') $missing[] = 'Birim';
    if ($contact === '') $missing[] = 'İletişim Bilgisi';
    if ($detailedDescription === '') $missing[] = 'Detaylı Tanımlama';
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
            'subFaultType' => $subFaultType,
            'department' => $department,
            'date' => $date,
            'status' => $status,
            'trackingNo' => $trackingNo,
            'contact' => $contact,
            'detailedDescription' => $detailedDescription,
            'filePath' => $filePath,
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
    <div class="d-flex ms-auto align-items-center gap-2">
      <button class="btn-icon" id="darkModeToggle" title="Karanlık Mod"><i class="bi bi-moon"></i></button>
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
                            <label class="form-label">Birim</label>
                            <select name="department" class="form-select" required>
                                <option value="">Seçiniz</option>
                                <?php foreach ($departments as $dep): ?>
                                    <option value="<?= htmlspecialchars($dep) ?>" <?= (isset($_POST['department']) && $_POST['department']==$dep)?'selected':'' ?>><?= htmlspecialchars($dep) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Arıza Türü</label>
                            <select name="faultType" id="faultType" class="form-select" required>
                                <option value="">Seçiniz</option>
                                <?php foreach ($faultTypes as $ft): ?>
                                    <option value="<?= $ft['id'] ?>" <?= (isset($_POST['faultType']) && $_POST['faultType']==$ft['id'])?'selected':'' ?>><?= htmlspecialchars($ft['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3" id="subFaultTypeBox" style="display:none;">
                            <label class="form-label">Alt Arıza Türü</label>
                            <select name="subFaultType" id="subFaultType" class="form-select">
                                <option value="">Seçiniz</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Detaylı Tanımlama</label>
                            <textarea name="detailedDescription" class="form-control" rows="4" placeholder="Arızanın tüm detaylarını, varsa bilgisayar veya ekipman özelliklerini, gözlemlerinizi ve açıklamalarınızı buraya yazınız." required><?= htmlspecialchars($_POST['detailedDescription'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dosya Ekle</label>
                            <input type="file" name="file" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">İletişim Bilgisi</label>
                            <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Gönder</button>
                    </form>
                    <hr>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Alt arıza türleri verisi (JS'ye aktarılıyor)
const subFaultTypes = <?= json_encode($subFaultTypes, JSON_UNESCAPED_UNICODE) ?>;
document.addEventListener('DOMContentLoaded', function() {
    const faultTypeSel = document.getElementById('faultType');
    const subBox = document.getElementById('subFaultTypeBox');
    const subSel = document.getElementById('subFaultType');
    function updateSubTypes() {
        const parentId = parseInt(faultTypeSel.value);
        subSel.innerHTML = '<option value="">Seçiniz</option>';
        if (!isNaN(parentId)) {
            let found = false;
            subFaultTypes.forEach(function(sub) {
                if (sub.parent_id === parentId) {
                    found = true;
                    const opt = document.createElement('option');
                    opt.value = sub.id;
                    opt.textContent = sub.name;
                    subSel.appendChild(opt);
                }
            });
            subBox.style.display = found ? 'block' : 'none';
        } else {
            subBox.style.display = 'none';
        }
    }
    faultTypeSel.addEventListener('change', updateSubTypes);
    // İlk yüklemede de çalışsın (edit durumunda)
    updateSubTypes();
});

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