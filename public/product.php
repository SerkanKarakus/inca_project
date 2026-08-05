<?php
/**
 * Public Product Detail Page (product.php).
 * Displays full description, image galleries, spec grids, and PDF links.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$db = getDBConnection();
$slug = trim($_GET['slug'] ?? '');

if (empty($slug)) {
    header("Location: /products");
    exit();
}

try {
    // 1. Fetch Product details (with category and brand names)
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name, b.name as brand_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.slug = ? AND p.is_active = 1 AND p.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
    
    if (!$product) {
        // Redirect to list if product is inactive, soft-deleted, or doesn't exist
        header("Location: /products");
        exit();
    }
    
    $productId = $product['id'];
    
    // 2. Fetch all product images
    $stmt = $db->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll() ?: [];
    
    // 3. Fetch product files (PDF catalogues)
    $stmt = $db->prepare("SELECT file_path FROM product_files WHERE product_id = ? AND file_type = 'pdf' LIMIT 1");
    $stmt->execute([$productId]);
    $pdfCatalog = $stmt->fetch();
    
} catch (Exception $e) {
    error_log("Fetch product detail failed: " . $e->getMessage());
    header("Location: /products");
    exit();
}

// 4. Parse Technical Specifications (supports JSON and lines of "Key: Value")
$specsList = [];
if (!empty($product['technical_specs'])) {
    $decoded = json_decode($product['technical_specs'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $key => $val) {
            $specsList[] = ['label' => $key, 'value' => $val];
        }
    } else {
        $lines = explode("\n", str_replace("\r", "", $product['technical_specs']));
        foreach ($lines as $line) {
            $parts = explode(":", $line, 2);
            if (count($parts) === 2) {
                $specsList[] = ['label' => trim($parts[0]), 'value' => trim($parts[1])];
            } else if (!empty(trim($line))) {
                $specsList[] = ['label' => '', 'value' => trim($line)];
            }
        }
    }
}

// SEO Parameters
$pageTitle = $product['name'];
$metaTitle = !empty($product['meta_title']) ? $product['meta_title'] : "{$product['name']} | INCA Ürünleri";
$metaDescription = !empty($product['meta_description']) ? $product['meta_description'] : substr(strip_tags($product['description'] ?? ''), 0, 150);
$metaKeywords = !empty($product['keywords']) ? $product['keywords'] : "{$product['name']}, inca, {$product['sku']}, teknik özellikler";

require_once __DIR__ . '/layout/header.php';
?>

<div class="container" style="margin-top: 3rem; margin-bottom: 5rem;">
  
  <!-- Back to search/list -->
  <a href="/products" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem; font-weight: 500; transition: var(--transition);" class="hover-white">
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
    </svg>
    Ürün Listesine Dön
  </a>
  
  <div class="detail-grid">
    
    <!-- Left Column: Gallery -->
    <div class="detail-gallery">
      <div class="main-image-box">
        <?php if (!empty($images)): ?>
          <img src="<?php echo htmlspecialchars($images[0]['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="main-image" id="main-product-image">
        <?php else: ?>
          <div class="main-image" style="background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.15);">
            <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
          </div>
        <?php endif; ?>
      </div>
      
      <!-- Thumbnails -->
      <?php if (count($images) > 1): ?>
        <div class="thumbnail-row">
          <?php foreach ($images as $index => $img): ?>
            <div class="thumbnail-box <?php echo $index === 0 ? 'active' : ''; ?>" data-large="<?php echo htmlspecialchars($img['image_path']); ?>">
              <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Thumbnail">
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    
    <!-- Right Column: Product details -->
    <div>
      <h1 class="product-info-title"><?php echo htmlspecialchars($product['name']); ?></h1>
      
      <div class="product-meta-row">
        <span>SKU: <code><?php echo htmlspecialchars($product['sku']); ?></code></span>
        <?php if (!empty($product['category_name'])): ?>
          <span>Kategori: <strong><?php echo htmlspecialchars($product['category_name']); ?></strong></span>
        <?php endif; ?>
        <?php if (!empty($product['brand_name'])): ?>
          <span>Marka: <strong><?php echo htmlspecialchars($product['brand_name']); ?></strong></span>
        <?php endif; ?>
      </div>
      
      <!-- Description -->
      <div class="product-info-desc">
        <?php echo htmlspecialchars($product['description']); ?>
      </div>
      
      <!-- Specifications -->
      <?php if (!empty($specsList)): ?>
        <h2 class="specs-title">Teknik Özellikler</h2>
        <table class="specs-table">
          <tbody>
            <?php foreach ($specsList as $spec): ?>
              <tr>
                <td class="specs-label"><?php echo htmlspecialchars($spec['label']); ?></td>
                <td><?php echo htmlspecialchars($spec['value']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      
      <!-- PDF Download Button -->
      <?php if (!empty($pdfCatalog)): ?>
        <a href="<?php echo htmlspecialchars($pdfCatalog['file_path']); ?>" class="btn-download" target="_blank" download>
          <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
          </svg>
          <span>Teknik Kataloğu İndir (PDF)</span>
        </a>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- Product YouTube Video Embed -->
  <?php if (!empty($product['video_url'])): ?>
    <?php 
      $youtubeId = get_youtube_video_id($product['video_url']);
    ?>
    <?php if ($youtubeId): ?>
      <div style="margin-top: 4rem; border-top: 1px solid var(--border-color); padding-top: 2.5rem;">
        <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--text-main); display: flex; align-items: center; gap: 0.65rem;">
          <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" style="color: #ef4444; flex-shrink: 0;">
            <path d="M23.498 6.163a3.003 3.003 0 0 0-2.11-2.11C19.517 3.545 12 3.545 12 3.545s-7.517 0-9.388.508a3.003 3.003 0 0 0-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 0 0 2.11 2.11c1.871.508 9.388.508 9.388.508s7.517 0 9.388-.508a3.003 3.003 0 0 0 2.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
          </svg>
          <span>Ürün Tanıtım & Kurulum Videosu</span>
        </h2>
        <div style="max-width: 780px; margin: 0 auto;">
          <div class="video-container" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
            <iframe 
              style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"
              src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtubeId); ?>" 
              title="YouTube video player" 
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
              allowfullscreen>
            </iframe>
          </div>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Gallery Controller Script -->
<script>
  document.addEventListener("DOMContentLoaded", function() {
    const mainImage = document.getElementById("main-product-image");
    const thumbnails = document.querySelectorAll(".thumbnail-box");
    
    thumbnails.forEach(thumb => {
      thumb.addEventListener("click", function() {
        // Toggle active border styling
        thumbnails.forEach(t => t.classList.remove("active"));
        this.classList.add("active");
        
        // Swap large image path
        const largePath = this.getAttribute("data-large");
        if (mainImage && largePath) {
          mainImage.src = largePath;
        }
      });
    });
  });
</script>

<?php
require_once __DIR__ . '/layout/footer.php';
?>
