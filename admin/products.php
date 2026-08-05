<?php
/**
 * Products Administration CRUD.
 * Supports Search, Pagination, Relationships, and Soft Deletes.
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/upload.php';
require_admin();

$db = getDBConnection();
$error = '';
$success = '';

// Active Action
$action = $_GET['action'] ?? 'list';
$product = null;
$productImages = [];
$productFiles = [];

// Handle Soft Delete Action
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    $token = $_GET['csrf_token'] ?? '';
    
    if (!verify_csrf_token($token)) {
        $error = "Geçersiz CSRF token. Silme işlemi iptal edildi.";
    } else {
        try {
            // Soft delete product by setting deleted_at timestamp
            $stmt = $db->prepare("UPDATE products SET deleted_at = NOW(), is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            log_audit_action($_SESSION['user_id'], 'SOFT_DELETE_PRODUCT', 'products', $id);
            $success = "Ürün başarıyla yayından kaldırıldı (silindi).";
        } catch (Exception $e) {
            error_log("Soft delete product error: " . $e->getMessage());
            $error = "Ürün silinirken bir hata oluştu.";
        }
    }
    $action = 'list';
}

// Handle Form Submission (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($token)) {
        $error = "Geçersiz CSRF token. Form gönderimi reddedildi.";
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $technical_specs = trim($_POST['technical_specs'] ?? '');
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // SEO Fields
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $keywords = trim($_POST['keywords'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        
        // Slug generation
        if (empty($slug)) {
            $slug = slugify($name);
        } else {
            $slug = slugify($slug);
        }
        
        // Validations
        if (empty($name) || empty($sku)) {
            $error = "Ürün Adı ve SKU alanları zorunludur.";
        } else {
            try {
                // Check duplicate SKU or Slug
                if ($action === 'edit') {
                    $stmt = $db->prepare("SELECT id FROM products WHERE (sku = ? OR slug = ?) AND id != ? AND deleted_at IS NULL");
                    $stmt->execute([$sku, $slug, $id]);
                } else {
                    $stmt = $db->prepare("SELECT id FROM products WHERE (sku = ? OR slug = ?) AND deleted_at IS NULL");
                    $stmt->execute([$sku, $slug]);
                }
                
                if ($stmt->fetch()) {
                    $error = "Bu SKU kodu veya URL slug değeri sistemde zaten kullanımda.";
                } else {
                    if ($action === 'edit') {
                        // Update product
                        $stmt = $db->prepare("UPDATE products SET name = ?, slug = ?, sku = ?, description = ?, technical_specs = ?, category_id = ?, brand_id = ?, is_featured = ?, is_active = ?, meta_title = ?, meta_description = ?, keywords = ?, video_url = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$name, $slug, $sku, $description, $technical_specs, $category_id, $brand_id, $is_featured, $is_active, $meta_title, $meta_description, $keywords, $video_url, $id]);
                        
                        $productId = $id;
                        log_audit_action($_SESSION['user_id'], 'UPDATE_PRODUCT', 'products', $productId);
                        $success = "Ürün başarıyla güncellendi.";
                    } else {
                        // Insert product
                        $stmt = $db->prepare("INSERT INTO products (name, slug, sku, description, technical_specs, category_id, brand_id, is_featured, is_active, meta_title, meta_description, keywords, video_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $slug, $sku, $description, $technical_specs, $category_id, $brand_id, $is_featured, $is_active, $meta_title, $meta_description, $keywords, $video_url]);
                        $productId = $db->lastInsertId();
                        
                        log_audit_action($_SESSION['user_id'], 'CREATE_PRODUCT', 'products', $productId);
                        $success = "Ürün başarıyla eklendi.";
                    }
                    
                    // --- PROCESS FILE UPLOADS ---
                    
                    // 1. Handle Multiple Image Uploads
                    if (!empty($_FILES['images']['name'][0])) {
                        $totalFiles = count($_FILES['images']['name']);
                        $uploadDir = __DIR__ . '/../uploads/products/';
                        
                        for ($i = 0; $i < $totalFiles; $i++) {
                            $fileArray = [
                                'name'     => $_FILES['images']['name'][$i],
                                'type'     => $_FILES['images']['type'][$i],
                                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                                'error'    => $_FILES['images']['error'][$i],
                                'size'     => $_FILES['images']['size'][$i]
                            ];
                            
                            if ($fileArray['error'] === UPLOAD_ERR_OK) {
                                $uploadResult = handle_secure_upload($fileArray, $uploadDir, true);
                                if ($uploadResult['success']) {
                                    $imagePath = '/uploads/products/' . $uploadResult['filename'];
                                    // Insert image reference in database
                                    $stmtImg = $db->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)");
                                    $stmtImg->execute([$productId, $imagePath, $i]);
                                } else {
                                    $error .= ($error ? ' | ' : '') . "Resim yükleme hatası: " . $uploadResult['error'];
                                }
                            }
                        }
                    }
                    
                    // 2. Handle PDF Catalog Upload
                    if (!empty($_FILES['pdf_catalog']['name']) && $_FILES['pdf_catalog']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = __DIR__ . '/../uploads/pdf/';
                        $uploadResult = handle_secure_upload($_FILES['pdf_catalog'], $uploadDir, false);
                        
                        if ($uploadResult['success']) {
                            $filePath = '/uploads/pdf/' . $uploadResult['filename'];
                            
                            // Delete physical file of the old PDF catalog if exists
                            $stmtOld = $db->prepare("SELECT file_path FROM product_files WHERE product_id = ? AND file_type = 'pdf'");
                            $stmtOld->execute([$productId]);
                            $oldFile = $stmtOld->fetch();
                            if ($oldFile && file_exists(__DIR__ . '/..' . $oldFile['file_path'])) {
                                @unlink(__DIR__ . '/..' . $oldFile['file_path']);
                            }
                            
                            // Delete database reference of the old PDF catalog
                            $stmtDel = $db->prepare("DELETE FROM product_files WHERE product_id = ? AND file_type = 'pdf'");
                            $stmtDel->execute([$productId]);
                            
                            // Insert new PDF catalog reference in database
                            $stmtFile = $db->prepare("INSERT INTO product_files (product_id, file_path, file_type) VALUES (?, ?, 'pdf')");
                            $stmtFile->execute([$productId, $filePath]);
                        } else {
                            $error .= ($error ? ' | ' : '') . "PDF yükleme hatası: " . $uploadResult['error'];
                        }
                    }
                    
                    // If no critical errors occurred, redirect to list
                    if (empty($error)) {
                        $action = 'list';
                    }
                }
            } catch (Exception $e) {
                error_log("Save product error: " . $e->getMessage());
                $error = "Ürün kaydedilirken hata oluştu: " . $e->getMessage();
            }
        }
    }
}

// Fetch categories and brands for dropdown lists
$categories = [];
$brands = [];
try {
    $categories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
    $brands = $db->query("SELECT id, name FROM brands ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    error_log("Fetch dropdown options failed: " . $e->getMessage());
}

// Fetch Product detail for Edit mode
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $error = "Düzenlenmek istenen ürün bulunamadı.";
        $action = 'list';
    } else {
        // Fetch existing images and files
        $stmtImg = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
        $stmtImg->execute([$id]);
        $productImages = $stmtImg->fetchAll();
        
        $stmtFile = $db->prepare("SELECT * FROM product_files WHERE product_id = ? AND file_type = 'pdf'");
        $stmtFile->execute([$id]);
        $productFiles = $stmtFile->fetchAll();
    }
}

// --- SEARCH & PAGINATION FOR LIST VIEW ---
$search = trim($_GET['search'] ?? '');
$catFilter = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$brandFilter = !empty($_GET['brand_id']) ? (int)$_GET['brand_id'] : null;

$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$products = [];
$totalProducts = 0;
$totalPages = 1;

if ($action === 'list') {
    try {
        // Base Query builder
        $countQuery = "SELECT COUNT(*) as total FROM products p WHERE p.deleted_at IS NULL";
        $dataQuery = "
            SELECT p.id, p.name, p.sku, p.slug, p.is_featured, p.is_active, p.created_at, 
                   c.name as category_name, b.name as brand_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.deleted_at IS NULL
        ";
        
        $params = [];
        $conditions = "";
        
        if (!empty($search)) {
            $conditions .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($catFilter) {
            $conditions .= " AND p.category_id = ?";
            $params[] = $catFilter;
        }
        if ($brandFilter) {
            $conditions .= " AND p.brand_id = ?";
            $params[] = $brandFilter;
        }
        
        // Execute Count
        $stmt = $db->prepare($countQuery . $conditions);
        $stmt->execute($params);
        $totalProducts = $stmt->fetch()['total'] ?? 0;
        $totalPages = ceil($totalProducts / $limit);
        if ($totalPages < 1) $totalPages = 1;
        
        // Execute Data with pagination
        $paginationQuery = $dataQuery . $conditions . " ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($paginationQuery);
        $stmt->execute($params);
        $products = $stmt->fetchAll() ?: [];
        
    } catch (Exception $e) {
        error_log("Fetch products list failed: " . $e->getMessage());
        $error = "Ürün listesi yüklenemedi.";
    }
}

// Page Metadata
$pageTitle = $action === 'edit' ? "Ürün Düzenle" : ($action === 'add' ? "Yeni Ürün Ekle" : "Ürün Yönetimi");
$pageSubtitle = $action === 'edit' ? "Mevcut ürün detaylarını güncelleyin" : ($action === 'add' ? "Kataloğa yeni bir ürün ekleyin" : "Ürünleri listeleyin, filtreleyin, ekleyin ve düzenleyin");

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

<!-- ==========================================
     LIST VIEW
     ========================================== -->
<?php if ($action === 'list'): ?>
  <!-- Filters Panel -->
  <div class="card-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
    <form action="/admin/products.php" method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) 120px; gap: 1rem; align-items: end;">
      <div class="form-group" style="margin-bottom: 0;">
        <label for="search" class="form-label">Arama Terimi</label>
        <input type="text" id="search" name="search" class="form-control" placeholder="İsim veya SKU..." value="<?php echo htmlspecialchars($search); ?>">
      </div>
      
      <div class="form-group" style="margin-bottom: 0;">
        <label for="category_filter" class="form-label">Kategori</label>
        <select id="category_filter" name="category_id" class="form-control">
          <option value="">Tümü</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo $catFilter === (int)$cat['id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($cat['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin-bottom: 0;">
        <label for="brand_filter" class="form-label">Marka</label>
        <select id="brand_filter" name="brand_id" class="form-control">
          <option value="">Tümü</option>
          <?php foreach ($brands as $br): ?>
            <option value="<?php echo $br['id']; ?>" <?php echo $brandFilter === (int)$br['id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($br['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <button type="submit" class="btn btn-primary" style="height: 42px; margin-top: 0;">Filtrele</button>
    </form>
  </div>

  <!-- Products Datatable Panel -->
  <div class="card-panel">
    <div class="card-panel-header">
      <span class="card-panel-title">Ürün Listesi (<?php echo $totalProducts; ?> Ürün)</span>
      <a href="/admin/products.php?action=add" class="btn btn-primary" style="width: auto;">Yeni Ürün Ekle</a>
    </div>
    
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Ürün Adı</th>
            <th>Kategori</th>
            <th>Marka</th>
            <th>Öne Çıkan</th>
            <th>Durum</th>
            <th style="text-align: right;">İşlemler</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($products)): ?>
            <tr>
              <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 2.5rem;">
                Aranan kriterlere uygun ürün bulunamadı.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($products as $prod): ?>
              <tr>
                <td><code><?php echo htmlspecialchars($prod['sku']); ?></code></td>
                <td style="font-weight: 600;"><?php echo htmlspecialchars($prod['name']); ?></td>
                <td><?php echo htmlspecialchars($prod['category_name'] ?? 'Kategorisiz'); ?></td>
                <td><?php echo htmlspecialchars($prod['brand_name'] ?? 'Markasız'); ?></td>
                <td>
                  <?php if ($prod['is_featured']): ?>
                    <span class="badge badge-success">Evet</span>
                  <?php else: ?>
                    <span class="badge badge-secondary">Hayır</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($prod['is_active']): ?>
                    <span class="badge badge-success">Aktif</span>
                  <?php else: ?>
                    <span class="badge badge-secondary">Pasif</span>
                  <?php endif; ?>
                </td>
                <td style="text-align: right;">
                  <a href="/admin/products.php?action=edit&id=<?php echo $prod['id']; ?>" class="btn btn-primary btn-action">Düzenle</a>
                  <a href="/admin/products.php?action=delete&id=<?php echo $prod['id']; ?>&csrf_token=<?php echo htmlspecialchars(generate_csrf_token()); ?>" class="btn btn-action" style="background: var(--color-danger-light); color: #fca5a5;" onclick="return confirm('Bu ürünü silmek istediğinizden emin misiniz?');">Sil</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <!-- Pagination Footer -->
    <?php if ($totalPages > 1): ?>
      <div style="padding: 1rem 1.5rem; display: flex; justify-content: center; gap: 0.5rem; border-top: 1px solid var(--border-color);">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="/admin/products.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $catFilter; ?>&brand_id=<?php echo $brandFilter; ?>" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; width: auto; background: <?php echo $i === $page ? 'var(--color-primary)' : 'rgba(255,255,255,0.04)'; ?>; color: #ffffff;">
            <?php echo $i; ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>

<!-- ==========================================
     ADD / EDIT VIEW
     ========================================== -->
<?php elseif ($action === 'add' || $action === 'edit'): ?>
  <div class="card-panel">
    <div class="card-panel-header">
      <span class="card-panel-title">
        <?php echo $action === 'edit' ? "Ürün Bilgilerini Güncelle" : "Yeni Ürün Kaydet"; ?>
      </span>
      <a href="/admin/products.php" class="btn" style="width: auto; background: rgba(255,255,255,0.05); color: var(--text-secondary);">Listeye Dön</a>
    </div>
    
    <div style="padding: 2rem;">
      <form action="/admin/products.php<?php echo $action === 'edit' ? '?action=edit' : '?action=add'; ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
        
        <?php if ($action === 'edit'): ?>
          <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
          
          <!-- General Details (Left Column) -->
          <div>
            <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1.25rem; color: var(--color-primary);">Genel Bilgiler</h3>
            
            <div class="form-group">
              <label for="name" class="form-label">Ürün Adı *</label>
              <input type="text" id="name" name="name" class="form-control" placeholder="Örn: X-500 Robot Süpürge" value="<?php echo $action === 'edit' ? htmlspecialchars($product['name']) : ''; ?>" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label for="sku" class="form-label">Stok Kodu (SKU) *</label>
                <input type="text" id="sku" name="sku" class="form-control" placeholder="Örn: INCA-X500" value="<?php echo $action === 'edit' ? htmlspecialchars($product['sku']) : ''; ?>" required>
              </div>
              
              <div class="form-group">
                <label for="slug" class="form-label">URL Slug</label>
                <input type="text" id="slug" name="slug" class="form-control" placeholder="Örn: x-500-robot-supurge" value="<?php echo $action === 'edit' ? htmlspecialchars($product['slug']) : ''; ?>">
              </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label for="category_id" class="form-label">Kategori</label>
                <select id="category_id" name="category_id" class="form-control">
                  <option value="">Seçiniz</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo ($action === 'edit' && $product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div class="form-group">
                <label for="brand_id" class="form-label">Marka</label>
                <select id="brand_id" name="brand_id" class="form-control">
                  <option value="">Seçiniz</option>
                  <?php foreach ($brands as $br): ?>
                    <option value="<?php echo $br['id']; ?>" <?php echo ($action === 'edit' && $product['brand_id'] == $br['id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($br['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            
            <div class="form-group">
              <label for="description" class="form-label">Ürün Açıklaması</label>
              <textarea id="description" name="description" class="form-control" rows="5" placeholder="Ürün detaylı tanıtım metni..."><?php echo $action === 'edit' ? htmlspecialchars($product['description']) : ''; ?></textarea>
            </div>
            
            <div class="form-group">
              <label for="technical_specs" class="form-label">Teknik Özellikler (JSON veya Düz Metin)</label>
              <textarea id="technical_specs" name="technical_specs" class="form-control" rows="4" placeholder="Renk: Siyah&#10;Batarya: 5000 mAh&#10;Ağırlık: 3.2 kg"><?php echo $action === 'edit' ? htmlspecialchars($product['technical_specs']) : ''; ?></textarea>
            </div>
            
            <div class="form-group" style="display: flex; gap: 1.5rem; margin-top: 1.5rem;">
              <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" name="is_featured" value="1" <?php echo ($action === 'edit' && $product['is_featured']) ? 'checked' : ''; ?>>
                <span>Ana Sayfada Göster (Öne Çıkar)</span>
              </label>
              
              <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" <?php echo ($action === 'add' || ($action === 'edit' && $product['is_active'])) ? 'checked' : ''; ?>>
                <span>Aktif (Yayında)</span>
              </label>
            </div>
          </div>
          
          <!-- SEO & Media (Right Column) -->
          <div>
            <!-- SEO Fields -->
            <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1.25rem; color: var(--color-primary);">SEO Alanları</h3>
            
            <div class="form-group">
              <label for="meta_title" class="form-label">Meta Başlığı (Title)</label>
              <input type="text" id="meta_title" name="meta_title" class="form-control" placeholder="Arama motoru başlığı..." value="<?php echo $action === 'edit' ? htmlspecialchars($product['meta_title'] ?? '') : ''; ?>">
            </div>
            
            <div class="form-group">
              <label for="meta_description" class="form-label">Meta Açıklaması (Description)</label>
              <textarea id="meta_description" name="meta_description" class="form-control" rows="2" placeholder="Maksimum 160 karakter arama motoru özeti..."><?php echo $action === 'edit' ? htmlspecialchars($product['meta_description'] ?? '') : ''; ?></textarea>
            </div>
            
            <div class="form-group">
              <label for="keywords" class="form-label">Anahtar Kelimeler (Keywords)</label>
              <input type="text" id="keywords" name="keywords" class="form-control" placeholder="kelime1, kelime2, kelime3..." value="<?php echo $action === 'edit' ? htmlspecialchars($product['keywords'] ?? '') : ''; ?>">
            </div>
            
            <div class="form-group">
              <label for="video_url" class="form-label">YouTube Video Linki (Kurulum / Tanıtım)</label>
              <input type="url" id="video_url" name="video_url" class="form-control" placeholder="Örn: https://www.youtube.com/watch?v=..." value="<?php echo $action === 'edit' ? htmlspecialchars($product['video_url'] ?? '') : ''; ?>">
              <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Desteklenen formatlar: youtube.com/watch?v=ID veya youtu.be/ID</small>
            </div>
            
            <!-- Media Upload Placeholders (Fully wired in Phase 6) -->
            <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-top: 2rem; margin-bottom: 1.25rem; color: var(--color-primary);">Medya ve Dosyalar</h3>
            
            <div class="form-group">
              <label for="images" class="form-label">Ürün Görselleri (Çoklu Seçim)</label>
              <input type="file" id="images" name="images[]" class="form-control" multiple accept="image/jpeg, image/png, image/webp">
              <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Desteklenen formatlar: JPG, PNG, WEBP. Maks 5MB.</small>
              
              <!-- Existing Images Display -->
              <?php if (!empty($productImages)): ?>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1rem;">
                  <?php foreach ($productImages as $img): ?>
                    <div style="position: relative; width: 80px; height: 80px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); overflow: hidden; background: rgba(0,0,0,0.2);">
                      <img src="<?php echo htmlspecialchars($img['image_path']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            
            <div class="form-group">
              <label for="pdf_catalog" class="form-label">Teknik PDF Kataloğu (Tek Dosya)</label>
              <input type="file" id="pdf_catalog" name="pdf_catalog" class="form-control" accept="application/pdf">
              <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Sadece PDF dosyası yüklenebilir. Maks 10MB.</small>
              
              <!-- Existing PDF Display -->
              <?php if (!empty($productFiles)): ?>
                <div style="margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                  <svg width="20" height="20" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                  </svg>
                  <a href="<?php echo htmlspecialchars($productFiles[0]['file_path']); ?>" target="_blank" style="color: var(--color-primary); text-decoration: none; font-size: 0.875rem;">Yüklü Kataloğu Görüntüle (PDF)</a>
                </div>
              <?php endif; ?>
            </div>
            
            <div style="background: rgba(99, 102, 241, 0.05); border: 1px dashed rgba(99, 102, 241, 0.2); padding: 1rem; border-radius: var(--radius-md); margin-top: 1.5rem;">
              <p style="font-size: 0.8rem; color: var(--text-secondary); line-height: 1.4;">
                <strong>Bilgi:</strong> Ürün medya yükleme ve PDF dosyası işleme mekanizması (Phase 6) kapsamında güvenlik testleri, isim şifreleme ve thumbnail oluşturma yetenekleriyle tam olarak aktifleştirilecektir.
              </p>
            </div>
          </div>
        </div>
        
        <div style="margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem; display: flex; justify-content: flex-end; gap: 1rem;">
          <a href="/admin/products.php" class="btn" style="width: auto; background: rgba(255,255,255,0.05); color: var(--text-secondary);">Vazgeç</a>
          <button type="submit" class="btn btn-primary" style="width: auto; padding: 0.75rem 2rem;">Ürünü Kaydet</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/layout/footer.php';
?>
