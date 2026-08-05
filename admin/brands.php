<?php
/**
 * Brands Administration CRUD.
 */

require_once __DIR__ . '/../config/auth.php';
require_admin();

$db = getDBConnection();
$error = '';
$success = '';

// Active Action
$action = $_GET['action'] ?? 'list';
$editBrand = null;

// Handle Delete Action
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    $token = $_GET['csrf_token'] ?? '';
    
    if (!verify_csrf_token($token)) {
        $error = "Geçersiz CSRF token. İşlem iptal edildi.";
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM brands WHERE id = ?");
            $stmt->execute([$id]);
            log_audit_action($_SESSION['user_id'], 'DELETE_BRAND', 'brands', $id);
            $success = "Marka başarıyla silindi.";
        } catch (Exception $e) {
            error_log("Delete brand error: " . $e->getMessage());
            $error = "Marka silinemedi. Bu markaya bağlı ürünler olabilir.";
        }
    }
    $action = 'list'; // Redirect to list
}

// Handle Form Submission (Add or Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($token)) {
        $error = "Geçersiz CSRF token. Form gönderimi reddedildi.";
    } else {
        $name = trim($_POST['name'] ?? '');
        
        if (empty($name)) {
            $error = "Marka adı alanı boş bırakılamaz.";
        } else {
            if ($action === 'edit') {
                $id = (int)$_POST['id'];
                
                try {
                    $stmt = $db->prepare("UPDATE brands SET name = ? WHERE id = ?");
                    $stmt->execute([$name, $id]);
                    log_audit_action($_SESSION['user_id'], 'UPDATE_BRAND', 'brands', $id);
                    $success = "Marka başarıyla güncellendi.";
                    $action = 'list';
                } catch (Exception $e) {
                    error_log("Edit brand error: " . $e->getMessage());
                    $error = "Marka güncellenirken bir hata oluştu.";
                }
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO brands (name) VALUES (?)");
                    $stmt->execute([$name]);
                    $newId = $db->lastInsertId();
                    log_audit_action($_SESSION['user_id'], 'CREATE_BRAND', 'brands', $newId);
                    $success = "Marka başarıyla eklendi.";
                    $action = 'list';
                } catch (Exception $e) {
                    error_log("Add brand error: " . $e->getMessage());
                    $error = "Marka eklenirken bir hata oluştu.";
                }
            }
        }
    }
}

// Fetch Brand for Editing
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT id, name FROM brands WHERE id = ?");
    $stmt->execute([$id]);
    $editBrand = $stmt->fetch();
    
    if (!$editBrand) {
        $error = "Düzenlenmek istenen marka bulunamadı.";
        $action = 'list';
    }
}

// Fetch All Brands for List
$brands = [];
try {
    $stmt = $db->query("SELECT id, name FROM brands ORDER BY name ASC");
    $brands = $stmt->fetchAll() ?: [];
} catch (Exception $e) {
    error_log("Fetch brands error: " . $e->getMessage());
}

// Setup Page Details
$pageTitle = $action === 'edit' ? "Marka Düzenle" : "Markalar";
$pageSubtitle = $action === 'edit' ? "Marka adını güncelleyin" : "Katalog markalarını ekleyin, güncelleyin veya silin";

require_once __DIR__ . '/layout/header.php';
?>

<!-- Alert Feedback -->
<?php if (!empty($error)): ?>
  <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
    </svg>
    <span><?php echo htmlspecialchars($error); ?></span>
  </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
  <div class="alert alert-success" style="margin-bottom: 1.5rem; background: var(--color-success-light); border: 1px solid rgba(16, 185, 129, 0.2); color: #a7f3d0;">
    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
    </svg>
    <span><?php echo htmlspecialchars($success); ?></span>
  </div>
<?php endif; ?>

<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); align-items: start;">
  
  <!-- Brand Table Panel -->
  <?php if ($action === 'list' || $action === 'edit'): ?>
  <div class="card-panel" style="margin-bottom: 0;">
    <div class="card-panel-header">
      <span class="card-panel-title">Marka Listesi</span>
    </div>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Marka Adı</th>
            <th style="text-align: right;">İşlemler</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($brands)): ?>
            <tr>
              <td colspan="3" style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                Henüz kayıtlı marka bulunmuyor.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($brands as $brand): ?>
              <tr>
                <td><code>#<?php echo $brand['id']; ?></code></td>
                <td style="font-weight: 600;"><?php echo htmlspecialchars($brand['name']); ?></td>
                <td style="text-align: right;">
                  <a href="/admin/brands.php?action=edit&id=<?php echo $brand['id']; ?>" class="btn btn-primary btn-action">Düzenle</a>
                  <a href="/admin/brands.php?action=delete&id=<?php echo $brand['id']; ?>&csrf_token=<?php echo htmlspecialchars(generate_csrf_token()); ?>" class="btn btn-action" style="background: var(--color-danger-light); color: #fca5a5;" onclick="return confirm('Bu markayı silmek istediğinizden emin misiniz?');">Sil</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Add / Edit Form Panel -->
  <div class="card-panel" style="margin-bottom: 0;">
    <div class="card-panel-header">
      <span class="card-panel-title">
        <?php echo $action === 'edit' ? "Markayı Düzenle" : "Yeni Marka Ekle"; ?>
      </span>
      <?php if ($action === 'edit'): ?>
        <a href="/admin/brands.php" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; background: rgba(255,255,255,0.05); color: var(--text-secondary);">İptal Et</a>
      <?php endif; ?>
    </div>
    
    <div style="padding: 1.5rem;">
      <form action="/admin/brands.php<?php echo $action === 'edit' ? '?action=edit' : ''; ?>" method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
        
        <?php if ($action === 'edit'): ?>
          <input type="hidden" name="id" value="<?php echo $editBrand['id']; ?>">
        <?php endif; ?>
        
        <div class="form-group">
          <label for="name" class="form-label">Marka Adı</label>
          <input type="text" id="name" name="name" class="form-control" placeholder="Örn: Samsung" value="<?php echo $action === 'edit' ? htmlspecialchars($editBrand['name']) : ''; ?>" required>
        </div>
        
        <button type="submit" class="btn btn-primary" style="margin-top: 1rem; width: 100%;">
          <?php echo $action === 'edit' ? "Güncellemeleri Kaydet" : "Marka Ekle"; ?>
        </button>
      </form>
    </div>
  </div>

</div>

<?php
require_once __DIR__ . '/layout/footer.php';
?>
