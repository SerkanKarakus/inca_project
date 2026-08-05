<?php
/**
 * Public Homepage (index.php).
 * Displays search bar, product categories, and featured products.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$db = getDBConnection();

// Fetch Featured Products (must be active and not soft-deleted)
$featuredProducts = [];
try {
    $stmt = $db->query("
        SELECT p.id, p.name, p.sku, p.slug, c.name AS category_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC LIMIT 1) AS main_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_featured = 1 AND p.is_active = 1 AND p.deleted_at IS NULL
        ORDER BY p.id DESC
        LIMIT 4
    ");
    $featuredProducts = $stmt->fetchAll() ?: [];
} catch (Exception $e) {
    error_log("Fetch featured products failed: " . $e->getMessage());
}

// SEO Parameters
$pageTitle = "Ana Sayfa";
$metaTitle = "INCA | Kurumsal Ürün Kataloğu";
$metaDescription = "Yüksek teknolojili ve dayanıklı INCA ürünlerini keşfedin. Detaylı teknik özellikler ve ürün PDF katalogları.";
$metaKeywords = "inca, ürün kataloğu, kurumsal ürünler, sanayi, teknoloji, cihazlar";

require_once __DIR__ . '/layout/header.php';
?>

<!-- Hero Banner & Search Section -->
<section class="hero">
  <div class="container">
    <h1><?php echo htmlspecialchars($siteSettings['homepage_slider_title'] ?? 'Geleceğin Teknolojisini Keşfedin'); ?></h1>
    <p><?php echo htmlspecialchars($siteSettings['homepage_slider_desc'] ?? 'INCA\'nın inovatif, verimli ve profesyonel ihtiyaçlarınıza yönelik olarak geliştirdiği geniş ürün kataloğunu inceleyin.'); ?></p>
    
    <div class="search-wrapper">
      <form action="/products" method="GET">
        <input type="text" name="search" class="search-input" placeholder="Ürün adı, model veya SKU kodu ile arayın..." required>
        <button type="submit" class="search-btn" aria-label="Ara">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
        </button>
      </form>
    </div>
  </div>
</section>

<!-- Featured Products Section -->
<section class="container" style="padding-top: 1rem; padding-bottom: 4rem;">
  <div class="section-header">
    <h2 class="section-title">Öne Çıkan Ürünler</h2>
    <a href="/products" style="color: var(--primary); font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; gap: 0.25rem;">
      Tümünü Gör
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
      </svg>
    </a>
  </div>
  
  <div class="product-grid">
    <?php if (empty($featuredProducts)): ?>
      <div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 3rem; border: 1px dashed var(--border-color); border-radius: var(--radius-lg);">
        Şu anda öne çıkarılmış bir ürün bulunmamaktadır.
      </div>
    <?php else: ?>
      <?php foreach ($featuredProducts as $prod): ?>
        <div class="product-card">
          <span class="product-card-badge">Öne Çıkan</span>
          <div class="product-image-box">
            <?php if (!empty($prod['main_image'])): ?>
              <img src="<?php echo htmlspecialchars($prod['main_image']); ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>" class="product-card-img" loading="lazy">
            <?php else: ?>
              <!-- Responsive modern CSS gradient placeholder -->
              <div class="product-card-img" style="background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.15);">
                <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
              </div>
            <?php endif; ?>
          </div>
          
          <div class="product-card-content">
            <span class="product-card-category"><?php echo htmlspecialchars($prod['category_name'] ?? 'Genel'); ?></span>
            <h3 class="product-card-title"><?php echo htmlspecialchars($prod['name']); ?></h3>
            <span class="product-card-sku">SKU: <?php echo htmlspecialchars($prod['sku']); ?></span>
            <a href="/product/<?php echo htmlspecialchars($prod['slug']); ?>" class="product-card-btn">İncele</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php
require_once __DIR__ . '/layout/footer.php';
?>
