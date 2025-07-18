<div class="container mt-5 d-flex flex-column align-items-center justify-content-center" style="min-height:70vh;">
  <div class="card shadow-sm p-4" style="max-width: 900px; width:100%;">
    <h2 class="mb-4 text-center fw-bold"><i class="bi bi-tags"></i> Tür/Birim & Alt Tür Yönetimi</h2>
    <?php
      $typeMsg = '';
      // Departman ekle
      if (isset($_POST['add_type']) && !empty($_POST['type_name'])) {
        $name = trim($_POST['type_name']);
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Departments WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() == 0) {
          $stmt = $conn->prepare("INSERT INTO Departments (name) VALUES (?)");
          $stmt->execute([$name]);
          $typeMsg = 'Tür/Birim eklendi!';
        } else {
          $typeMsg = 'Bu tür/birim zaten var!';
        }
      }
      // Departman sil
      if (isset($_POST['delete_type']) && !empty($_POST['delete_type_name'])) {
        $name = $_POST['delete_type_name'];
        // Atanmış admin var mı kontrol et
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Users WHERE department_id = (SELECT id FROM Departments WHERE name = ?)");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() > 0) {
          $typeMsg = 'Bu tür/birime atanmış bir admin olduğu için silinemez!';
        } else {
          // Alt türleri de sil
          $stmt = $conn->prepare("DELETE FROM SubDepartments WHERE department_id = (SELECT id FROM Departments WHERE name = ?)");
          $stmt->execute([$name]);
          $stmt = $conn->prepare("DELETE FROM Departments WHERE name = ?");
          $stmt->execute([$name]);
          $typeMsg = 'Tür/Birim silindi!';
        }
      }
      // Alt tür ekle
      if (isset($_POST['add_subtype']) && !empty($_POST['subtype_name']) && !empty($_POST['parent_type'])) {
        $parent = $_POST['parent_type'];
        $sub = trim($_POST['subtype_name']);
        $stmt = $conn->prepare("SELECT id FROM Departments WHERE name = ?");
        $stmt->execute([$parent]);
        $deptId = $stmt->fetchColumn();
        if ($deptId) {
          $stmt = $conn->prepare("SELECT COUNT(*) FROM SubDepartments WHERE name = ? AND department_id = ?");
          $stmt->execute([$sub, $deptId]);
          if ($stmt->fetchColumn() == 0) {
            $stmt = $conn->prepare("INSERT INTO SubDepartments (name, department_id) VALUES (?, ?)");
            $stmt->execute([$sub, $deptId]);
          $typeMsg = 'Alt tür eklendi!';
        } else {
          $typeMsg = 'Bu alt tür zaten var!';
          }
        }
      }
      // Alt tür sil
      if (isset($_POST['delete_subtype']) && !empty($_POST['delete_subtype_name']) && !empty($_POST['delete_subtype_parent'])) {
        $parent = $_POST['delete_subtype_parent'];
        $sub = $_POST['delete_subtype_name'];
        $stmt = $conn->prepare("SELECT id FROM Departments WHERE name = ?");
        $stmt->execute([$parent]);
        $deptId = $stmt->fetchColumn();
        if ($deptId) {
          $stmt = $conn->prepare("DELETE FROM SubDepartments WHERE name = ? AND department_id = ?");
          $stmt->execute([$sub, $deptId]);
          $typeMsg = 'Alt tür silindi!';
        }
      }
      // Departman ve alt türleri çek
      $departments = [];
      $stmt = $conn->query("SELECT id, name FROM Departments ORDER BY name");
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['subdepartments'] = [];
        $stmt2 = $conn->prepare("SELECT name FROM SubDepartments WHERE department_id = ? ORDER BY name");
        $stmt2->execute([$row['id']]);
        $row['subdepartments'] = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        $departments[] = $row;
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
            <?php foreach ($departments as $d): ?>
              <option value="<?= htmlspecialchars($d['name']) ?>"><?= htmlspecialchars($d['name']) ?></option>
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
            <h5 class="mb-3 text-center fw-bold"><i class="bi bi-list-ul"></i> Mevcut Türler ve Alt Türler</h5>
            <ul class="list-group list-group-flush">
              <?php foreach ($departments as $d): ?>
                <li class="list-group-item">
                  <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-primary"><i class="bi bi-tag"></i> <?= htmlspecialchars($d['name']) ?></span>
                    <form method="post" style="display:inline-block">
                      <input type="hidden" name="delete_type_name" value="<?= htmlspecialchars($d['name']) ?>">
                      <button type="submit" name="delete_type" class="btn btn-danger btn-sm" onclick="return confirm('Bu türü silmek istediğinize emin misiniz?')"><i class="bi bi-trash"></i> Sil</button>
                    </form>
                  </div>
                  <?php if ($d['subdepartments']): ?>
                    <ul class="list-group mt-2 ms-4">
                      <?php foreach ($d['subdepartments'] as $sub): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                          <span><i class="bi bi-chevron-right"></i> <?= htmlspecialchars($sub) ?></span>
                          <form method="post" style="display:inline-block">
                            <input type="hidden" name="delete_subtype_parent" value="<?= htmlspecialchars($d['name']) ?>">
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