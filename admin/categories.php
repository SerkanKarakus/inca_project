<?php
/**
 * Categories Administration CRUD.
 */

require_once __DIR__ . '/../config/auth.php';
require_admin();

$db = getDBConnection();
$error = '';
$success = '';

// Active Action
$action = $_GET['action'] ?? 'list';
$editCategory = null;

// Handle Delete Action
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    $token = $_GET['csrf_token'] ?? '';
    
    if (!verify_csrf_token($token)) {
        $error = "Geçersiz CSRF token. İşlem iptal edildi.";
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            log_audit_action($_SESSION['user_id'], 'DELETE_CATEGORY', 'categories', $id);
            $success = "Kategori başarıyla silindi.";
        } catch (Exception $e) {
            error_log("Delete category error: " . $e->getMessage());
            $error = "Kategori silinemedi. Bu kategoriye bağlı ürünler olabilir.";
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
        $slug = trim($_POST['slug'] ?? '');
        
        // Auto-generate slug if left blank
        if (empty($slug)) {
            $slug = slugify($name);
        } else {
            $slug = slugify($slug);
        }
        
        if (empty($name)) {
            $error = "Kategori adı alanı boş bırakılamaz.";
        } else {
            if ($action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                
                try {
                    // Check if slug is taken by another category
                    $stmt = $db->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
                    $stmt->execute([$slug, $id]);
                    if ($stmt->fetch()) {
                        $error = "Bu URL slug değeri başka bir kategori tarafından kullanılıyor.";
                    } else {
                        // Update Category
                        $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
                        $stmt->execute([$name, $slug, $id]);
                        log_audit_action($_SESSION['user_id'], 'UPDATE_CATEGORY', 'categories', $id);
                        $success = "Kategori başarıyla güncellendi.";
                        $action = 'list'; // Return to list
                    }
                } catch (Exception $e) {
                    error_log("Edit category error: " . $e->getMessage());
                    $error = "Kategori güncellenirken bir hata oluştu.";
                }
            } else {
                // Add new category
                try {
                    // Check if slug exists
                    $stmt = $db->prepare("SELECT id FROM categories WHERE slug = ?");
                    $stmt->execute([$slug]);
                    if ($stmt->fetch()) {
                        $error = "Bu URL slug değeri başka bir kategori tarafından kullanılıyor.";
                    } else {
                        // Insert Category
                        $stmt = $db->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
                        $stmt->execute([$name, $slug]);
                        $newId = $db->lastInsertId();
                        log_audit_action($_SESSION['user_id'], 'CREATE_CATEGORY', 'categories', $newId);
                        $success = "Kategori başarıyla eklendi.";
                        $action = 'list';
                    }
                } catch (Exception $e) {
                    error_log("Add category error: " . $e->getMessage());
                    $error = "Kategori eklenirken bir hata oluştu.";
                }
            }
        }
    }
}

// Fetch Category for Editing
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT id, name, slug FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $editCategory = $stmt->fetch();
    
    if (!$editCategory) {
        $error = "Düzenlenmek istenen kategori bulunamadı.";
        $action = 'list';
    }
}

// Fetch All Categories for List
$categories = [];
try {
    $stmt = $db->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll() ?: [];
} catch (Exception $e) {
    error_log("Fetch categories error: " . $e->getMessage());
}

// Setup Page Details
$pageTitle = $action === 'edit' ? "Kategori Düzenle" : "Kategoriler";
$pageSubtitle = $action === 'edit' ? "Kategori bilgilerini güncelleyin" : "Katalog kategorilerini ekleyin, güncelleyin veya silin";

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
  
  <!-- Category Table Panel -->
  <?php if ($action === 'list' || $action === 'edit'): ?>
  <div class="card-panel" style="margin-bottom: 0;">
    <div class="card-panel-header">
      <span class="card-panel-title">Kategori Listesi</span>
    </div>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Kategori Adı</th>
            <th>URL Slug</th>
            <th style="text-align: right;">İşlemler</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($categories)): ?>
            <tr>
              <td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                Henüz kayıtlı kategori bulunmuyor.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($categories as $cat): ?>
              <tr>
                <td><code>#<?php echo $cat['id']; ?></code></td>
                <td style="font-weight: 600;"><?php echo htmlspecialchars($cat['name']); ?></td>
                <td><code>/category/<?php echo htmlspecialchars($cat['slug']); ?></code></td>
                <td style="text-align: right;">
                  <a href="/admin/categories.php?action=edit&id=<?php echo $cat['id']; ?>" class="btn btn-primary btn-action">Düzenle</a>
                  <a href="/admin/categories.php?action=delete&id=<?php echo $cat['id']; ?>&csrf_token=<?php echo htmlspecialchars(generate_csrf_token()); ?>" class="btn btn-action" style="background: var(--color-danger-light); color: #fca5a5;" onclick="return confirm('Bu kategoriyi silmek istediğinizden emin misiniz?');">Sil</a>
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
        <?php echo $action === 'edit' ? "Kategoriyi Düzenle" : "Yeni Kategori Ekle"; ?>
      </span>
      <?php if ($action === 'edit'): ?>
        <a href="/admin/categories.php" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; background: rgba(255,255,255,0.05); color: var(--text-secondary);">İptal Et</a>
      <?php endif; ?>
    </div>
    
    <div style="padding: 1.5rem;">
      <form action="/admin/categories.php<?php echo $action === 'edit' ? '?action=edit' : ''; ?>" method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
        
        <?php if ($action === 'edit'): ?>
          <input type="hidden" name="id" value="<?php echo $editCategory['id']; ?>">
        <?php endif; ?>
        
        <div class="form-group">
          <label for="name" class="form-label">Kategori Adı</label>
          <input type="text" id="name" name="name" class="form-control" placeholder="Örn: Ev Aletleri" value="<?php echo $action === 'edit' ? htmlspecialchars($editCategory['name']) : ''; ?>" required>
        </div>
        
        <div class="form-group">
          <label for="slug" class="form-label">URL Slug (İsteğe Bağlı)</label>
          <input type="text" id="slug" name="slug" class="form-control" placeholder="Örn: ev-aletleri" value="<?php echo $action === 'edit' ? htmlspecialchars($editCategory['slug']) : ''; ?>">
          <small style="display: block; margin-top: 0.25rem; color: var(--text-muted); font-size: 0.75rem;">
            Boş bırakırsanız kategori adından otomatik olarak oluşturulur.
          </small>
        </div>
        
        <button type="submit" class="btn btn-primary" style="margin-top: 1rem; width: 100%;">
          <?php echo $action === 'edit' ? "Güncellemeleri Kaydet" : "Kategori Ekle"; ?>
        </button>
      </form>
    </div>
  </div>

</div>

<?php
require_once __DIR__ . '/layout/footer.php';
?>
