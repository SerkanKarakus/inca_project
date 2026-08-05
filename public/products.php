<?php
/**
 * Public Product Listing Page (products.php).
 * Includes Keyword Search, Category/Brand filtering, and Pagination.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$db = getDBConnection();

// Get filter inputs
$search = trim($_GET['search'] ?? '');
$catFilter = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$brandFilter = !empty($_GET['brand_id']) ? (int)$_GET['brand_id'] : null;

// Pagination variables
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$limit = 9;
$offset = ($page - 1) * $limit;

$products = [];
$totalProducts = 0;
$totalPages = 1;

try {
    // 1. Build Query Conditions
    $countQuery = "SELECT COUNT(*) as total FROM products p WHERE p.is_active = 1 AND p.deleted_at IS NULL";
    
    $dataQuery = "
        SELECT p.id, p.name, p.sku, p.slug, p.is_featured, c.name AS category_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC LIMIT 1) AS main_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.is_active = 1 AND p.deleted_at IS NULL
    ";
    
    $params = [];
    $conditions = "";
    
    if (!empty($search)) {
        $conditions .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
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
    
    // 2. Count results
    $stmt = $db->prepare($countQuery . $conditions);
    $stmt->execute($params);
    $totalProducts = $stmt->fetch()['total'] ?? 0;
    
    $totalPages = ceil($totalProducts / $limit);
    if ($totalPages < 1) $totalPages = 1;
    
    // 3. Fetch products
    $dataQuery .= $conditions . " ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($dataQuery);
    $stmt->execute($params);
    $products = $stmt->fetchAll() ?: [];
    
} catch (Exception $e) {
    error_log("Fetch public products list error: " . $e->getMessage());
}

// Fetch all categories & brands for filter options
$categories = [];
$brands = [];
try {
    $categories = $db->query("SELECT id, name, slug FROM categories ORDER BY name ASC")->fetchAll() ?: [];
    $brands = $db->query("SELECT id, name FROM brands ORDER BY name ASC")->fetchAll() ?: [];
} catch (Exception $e) {
    error_log("Fetch filter options error: " . $e->getMessage());
}

// Page Metadata
$pageTitle = "Ürünlerimiz";
$metaTitle = "Ürünlerimiz | INCA Katalog";
$metaDescription = "INCA teknolojik ürün grubunu inceleyin. Detaylı aramalar yapın, teknik tabloları görüntüleyin.";
$metaKeywords = "ürün listesi, modeller, inca modelleri, arama, filtreleme";

require_once __DIR__ . '/layout/header.php';
?>

<!-- Section Header -->
<section class="container" style="margin-top: 3rem; margin-bottom: 2rem;">
  <div class="section-header" style="flex-direction: column; align-items: flex-start; gap: 0.5rem;">
    <h1 class="section-title" style="font-size: 2.5rem;">Tüm Ürünler</h1>
    <p style="color: var(--text-muted);">Geniş filtreleri kullanarak aradığınız modeli hızlıca bulun.</p>
  </div>
</section>

<!-- Filters and Catalog Grid -->
<div class="container" style="display: grid; grid-template-columns: 280px 1fr; gap: 2.5rem; align-items: start; margin-bottom: 5rem;">
  
  <!-- Left Side Filters Panel (responsive hidden on mobile inside CSS) -->
  <aside style="background: var(--bg-card); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: var(--radius-lg); position: sticky; top: 100px;">
    <h2 style="font-size: 1.15rem; margin-bottom: 1.25rem; font-weight: 700; color: #ffffff; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">Katalog Filtreleri</h2>
    
    <form action="/products" method="GET">
      <!-- Search Input -->
      <div style="margin-bottom: 1.25rem;">
        <label for="search" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem;">Kelime ile Ara</label>
        <input type="text" id="search" name="search" class="form-control" style="font-size: 0.875rem;" placeholder="İsim veya SKU..." value="<?php echo htmlspecialchars($search); ?>">
      </div>
      
      <!-- Category Filter -->
      <div style="margin-bottom: 1.25rem;">
        <label for="category_id" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem;">Kategori</label>
        <select id="category_id" name="category_id" class="form-control" style="font-size: 0.875rem;">
          <option value="">Tümü</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo $catFilter === (int)$cat['id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($cat['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <!-- Brand Filter -->
      <div style="margin-bottom: 1.5rem;">
        <label for="brand_id" style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem;">Marka</label>
        <select id="brand_id" name="brand_id" class="form-control" style="font-size: 0.875rem;">
          <option value="">Tümü</option>
          <?php foreach ($brands as $br): ?>
            <option value="<?php echo $br['id']; ?>" <?php echo $brandFilter === (int)$br['id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($br['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <button type="submit" class="btn btn-primary" style="width: 100%; height: 42px;">Filtreleri Uygula</button>
      
      <?php if (!empty($search) || $catFilter || $brandFilter): ?>
        <a href="/products" class="btn" style="width: 100%; text-align: center; margin-top: 0.5rem; background: rgba(255,255,255,0.05); font-size: 0.85rem; border: 1px solid var(--border-color); color: var(--text-muted); display: block; padding: 0.6rem 0; border-radius: var(--radius-md);">Temizle</a>
      <?php endif; ?>
    </form>
  </aside>

  <!-- Right Side Grid & Pagination -->
  <main>
    <div class="product-grid" style="margin-bottom: 2rem;">
      <?php if (empty($products)): ?>
        <div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 4rem; border: 1px dashed var(--border-color); border-radius: var(--radius-lg);">
          <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="margin-bottom: 1rem; color: var(--text-muted);">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <p style="font-size: 1.1rem; font-weight: 500;">Aradığınız kriterlere uygun ürün bulunamadı.</p>
          <p style="font-size: 0.9rem; margin-top: 0.25rem;">Lütfen filtreleri sıfırlayıp tekrar deneyin.</p>
        </div>
      <?php else: ?>
        <?php foreach ($products as $prod): ?>
          <div class="product-card">
            <?php if ($prod['is_featured']): ?>
              <span class="product-card-badge">Öne Çıkan</span>
            <?php endif; ?>
            <div class="product-image-box">
              <?php if (!empty($prod['main_image'])): ?>
                <img src="<?php echo htmlspecialchars($prod['main_image']); ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>" class="product-card-img" loading="lazy">
              <?php else: ?>
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
              <a href="/product/<?php echo htmlspecialchars($prod['slug']); ?>" class="product-card-btn">Detayları Gör</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Pagination links -->
    <?php if ($totalPages > 1): ?>
      <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 3rem;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="/products?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $catFilter; ?>&brand_id=<?php echo $brandFilter; ?>" class="btn" style="padding: 0.5rem 1rem; font-size: 0.9rem; width: auto; background: <?php echo $i === $page ? 'var(--primary)' : 'var(--bg-card)'; ?>; border: 1px solid var(--border-color); color: #ffffff;">
            <?php echo $i; ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<?php
require_once __DIR__ . '/layout/footer.php';
?>
