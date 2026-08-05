<?php
/**
 * Media Management Admin Panel.
 * Lists all uploaded product images and allows deleting unused (orphan) files to clean hosting storage.
 */

require_once __DIR__ . '/../config/auth.php';
require_admin();

$db = getDBConnection();
$error = '';
$success = '';

$uploadDir = __DIR__ . '/../uploads/products';

// Handle Image Deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $token = $_GET['csrf_token'] ?? '';
    $filename = $_GET['file'] ?? '';
    
    if (!verify_csrf_token($token)) {
        $error = "Geçersiz güvenlik tokeni. İşlem iptal edildi.";
    } elseif (!preg_match('/^[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/i', $filename)) {
        $error = "Geçersiz veya güvensiz dosya adı.";
    } else {
        $filePath = $uploadDir . '/' . $filename;
        $thumbPath = $uploadDir . '/thumb_' . $filename;
        
        // Check if file is physically present
        if (file_exists($filePath)) {
            // Delete main file
            if (unlink($filePath)) {
                // Delete thumbnail if exists
                if (file_exists($thumbPath)) {
                    unlink($thumbPath);
                }
                
                // Also clean up database references if any exist
                try {
                    $db->prepare("DELETE FROM product_images WHERE image_path LIKE ?")->execute(['%' . $filename]);
                } catch (Exception $e) {
                    error_log("Database cleanup failed during media delete: " . $e->getMessage());
                }
                
                log_audit_action($_SESSION['user_id'], 'DELETE_MEDIA', 'media', 0);
                $success = "Görsel sunucudan başarıyla silindi.";
            } else {
                $error = "Dosya silinirken bir sunucu hatası oluştu.";
            }
        } else {
            $error = "Silinmek istenen dosya sunucuda bulunamadı.";
        }
    }
}

// Fetch all uploaded images
$imagesList = [];
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        // Skip directories and thumbnails
        if ($file === '.' || $file === '..' || $file === 'index.html' || strpos($file, 'thumb_') === 0) {
            continue;
        }
        
        $filePath = $uploadDir . '/' . $file;
        if (is_file($filePath)) {
            $fileSize = filesize($filePath);
            $modTime = filemtime($filePath);
            
            // Check if this file is linked to any active products in database
            $productInfo = null;
            try {
                $stmt = $db->prepare("
                    SELECT p.id, p.name, p.sku 
                    FROM product_images pi
                    JOIN products p ON pi.product_id = p.id
                    WHERE pi.image_path LIKE ? AND p.deleted_at IS NULL
                    LIMIT 1
                ");
                $stmt->execute(['%' . $file]);
                $productInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Check database link failed for $file: " . $e->getMessage());
            }
            
            $imagesList[] = [
                'filename' => $file,
                'size' => $fileSize,
                'date' => $modTime,
                'product' => $productInfo
            ];
        }
    }
}

// Sort images by modification time desc
usort($imagesList, function($a, $b) {
    return $b['date'] <=> $a['date'];
});

// Setup Page Details
$pageTitle = "Medya Yönetimi";
$pageSubtitle = "Sunucudaki ürün görsellerini inceleyin ve kullanılmayan atıl dosyaları temizleyin";

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

<!-- Media Files Overview -->
<div class="card-panel">
  <div class="card-panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <span class="card-panel-title">Tüm Ürün Görselleri (<?php echo count($imagesList); ?>)</span>
  </div>
  
  <div style="padding: 1.5rem;">
    <?php if (empty($imagesList)): ?>
      <div style="text-align: center; color: var(--text-secondary); padding: 4rem; border: 1px dashed var(--border-color); border-radius: var(--radius-lg);">
        Sunucuda henüz kayıtlı görsel bulunmamaktadır.
      </div>
    <?php else: ?>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.5rem;">
        <?php foreach ($imagesList as $img): ?>
          <?php 
            $thumbFile = 'thumb_' . $img['filename'];
            $thumbUrl = file_exists($uploadDir . '/' . $thumbFile) ? '/uploads/products/' . $thumbFile : '/uploads/products/' . $img['filename'];
          ?>
          <div class="media-card" style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; display: flex; flex-direction: column; transition: var(--transition);">
            <!-- Preview Box -->
            <div style="position: relative; width: 100%; padding-top: 100%; background: #000; overflow: hidden; border-bottom: 1px solid var(--border-color);">
              <img src="<?php echo htmlspecialchars($thumbUrl); ?>" alt="<?php echo htmlspecialchars($img['filename']); ?>" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain;">
            </div>
            
            <!-- Metadata & Actions -->
            <div style="padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem; flex-grow: 1;">
              <div style="font-size: 0.75rem; color: var(--text-muted); word-break: break-all; font-family: monospace;">
                <?php echo htmlspecialchars($img['filename']); ?>
              </div>
              <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 500;">
                Boyut: <?php echo number_format($img['size'] / 1024, 1); ?> KB
              </div>
              
              <!-- Connection details -->
              <div style="margin-top: auto; padding-top: 0.5rem; border-top: 1px solid var(--border-color); font-size: 0.8rem;">
                <?php if ($img['product']): ?>
                  <span class="badge badge-success" style="display: inline-block; margin-bottom: 0.35rem; font-size: 0.7rem;">Kullanılıyor</span>
                  <div style="font-weight: 600; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($img['product']['name']); ?>">
                    <a href="/admin/products.php?action=edit&id=<?php echo $img['product']['id']; ?>" style="color: var(--color-primary); text-decoration: none;">
                      <?php echo htmlspecialchars($img['product']['name']); ?>
                    </a>
                  </div>
                <?php else: ?>
                  <span class="badge badge-secondary" style="display: inline-block; margin-bottom: 0.35rem; font-size: 0.7rem; background: #b91c1c; color: #fee2e2;">Kullanılmıyor</span>
                  <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 0.25rem;">
                    <span style="color: var(--text-muted); font-size: 0.75rem;">Atıl Dosya</span>
                    <a href="/admin/media.php?action=delete&file=<?php echo urlencode($img['filename']); ?>&csrf_token=<?php echo urlencode(generate_csrf_token()); ?>" 
                       onclick="return confirm('Bu kullanılmayan görseli sunucudan kalıcı olarak silmek istediğinizden emin misiniz?');"
                       class="btn btn-action" 
                       style="background: #ef4444; color: #fff; border: 0; padding: 0.25rem 0.5rem; font-size: 0.75rem; width: auto; border-radius: var(--radius-sm);">
                      Sil
                    </a>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
require_once __DIR__ . '/layout/footer.php';
?>
