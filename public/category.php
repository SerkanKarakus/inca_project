<?php
/**
 * Public Category Specific Page (category.php).
 * Displays a split catalog view: subcategory menu on the left sidebar,
 * and either subcategory cards grid or product lists on the right content panel.
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
    // 1. Fetch Current Category Details
    $stmt = $db->prepare("SELECT id, name, slug FROM categories WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $category = $stmt->fetch();
    
    if (!$category) {
        header("Location: /products");
        exit();
    }
    
    $currentCategoryName = $category['name'];
    
    // Configured corporate main categories mapping
    $mainCategoryNames = [
        'BLUM ÜRÜNLERİ' => 'Blum',
        'MOBİLYA AKSESUARLARI' => 'Mobilya',
        'ALÜMİNYUM GRUBU' => 'Alüminyum',
        'HIRDAVAT MALZEMELERİ' => 'Hırdavat',
        'MUTFAK AKSESUARLARI' => 'Mutfak',
        'KAPI AKSESUARLARI' => 'Kapı',
        'SÜRGÜ SİSTEMLERİ' => 'Sürgü',
        'GARDOLAP AKSESUARLARI' => 'Gardolap',
        'ESKİTME ÜRÜNLERİ' => 'Eskitme',
        'EV GEREÇLERİ' => 'Ev'
    ];

    $parentCategory = null;
    $subCategories = [];
    $products = [];
    
    // 2. Detect Parent/Subcategory relationship
    $isParentActive = false;
    $parentCategoryName = '';
    $prefix = '';
    
    // Check if the current category is a main category
    if (array_key_exists(mb_strtoupper($currentCategoryName, 'UTF-8'), $mainCategoryNames)) {
        $isParentActive = true;
        $parentCategoryName = mb_strtoupper($currentCategoryName, 'UTF-8');
        $prefix = $mainCategoryNames[$parentCategoryName];
        
        $parentCategory = $category;
        $parentCategory['fullName'] = $parentCategoryName;
        $parentCategory['prefix'] = $prefix;
        $parentCategory['is_parent_active'] = true;
    } else {
        // Current is a subcategory. Detect parent category group
        foreach ($mainCategoryNames as $pName => $pref) {
            if (stripos($currentCategoryName, $pref . ' ') === 0) {
                $parentCategoryName = $pName;
                $prefix = $pref;
                break;
            }
        }
        
        if (!empty($parentCategoryName)) {
            $stmt = $db->prepare("SELECT id, name, slug FROM categories WHERE name = ? LIMIT 1");
            $stmt->execute([$parentCategoryName]);
            $parentCategory = $stmt->fetch();
            if ($parentCategory) {
                $parentCategory['fullName'] = $parentCategoryName;
                $parentCategory['prefix'] = $prefix;
                $parentCategory['is_parent_active'] = false;
            }
        }
    }
    
    // 3. Load Subcategories & Sibling Menu details if parent exists
    if ($parentCategory) {
        $prefix = $parentCategory['prefix'];
        $stmt = $db->prepare("SELECT id, name, slug FROM categories WHERE name LIKE ? ORDER BY name ASC");
        $stmt->execute([$prefix . ' %']);
        $subsRaw = $stmt->fetchAll() ?: [];
        
        foreach ($subsRaw as $subRow) {
            // Strip the prefix for display
            $displayName = substr($subRow['name'], strlen($prefix) + 1);
            $displayName = mb_convert_case($displayName, MB_CASE_TITLE, "UTF-8");
            
            // Get subcategory product count
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND is_active = 1 AND deleted_at IS NULL");
            $stmtCount->execute([$subRow['id']]);
            $subCount = (int)$stmtCount->fetchColumn();
            
            // Get representative image from the newest product inside the subcategory
            $stmtImg = $db->prepare("
                SELECT pi.image_path 
                FROM product_images pi
                JOIN products p ON pi.product_id = p.id
                WHERE p.category_id = ? AND p.is_active = 1 AND p.deleted_at IS NULL
                ORDER BY p.id DESC, pi.sort_order ASC 
                LIMIT 1
            ");
            $stmtImg->execute([$subRow['id']]);
            $subImage = $stmtImg->fetchColumn();
            
            $subCategories[] = [
                'id' => $subRow['id'],
                'name' => $displayName,
                'fullName' => $subRow['name'],
                'slug' => $subRow['slug'],
                'count' => $subCount,
                'image' => $subImage ?: ''
            ];
        }
        
        // Sum total parent product count (products in parent category + products in subcategories)
        $subIds = array_column($subCategories, 'id');
        if (!empty($subIds)) {
            $inClause = implode(',', array_map('intval', $subIds));
            $stmtCount = $db->prepare("
                SELECT COUNT(*) 
                FROM products 
                WHERE (category_id = ? OR category_id IN ($inClause)) 
                  AND is_active = 1 
                  AND deleted_at IS NULL
            ");
            $stmtCount->execute([$parentCategory['id']]);
            $parentCategory['count'] = (int)$stmtCount->fetchColumn();
        } else {
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND is_active = 1 AND deleted_at IS NULL");
            $stmtCount->execute([$parentCategory['id']]);
            $parentCategory['count'] = (int)$stmtCount->fetchColumn();
        }
    }
    
    // 4. Fetch Products (Only if we are displaying a leaf Subcategory)
    if (!$isParentActive) {
        $stmt = $db->prepare("
            SELECT p.id, p.name, p.sku, p.slug, p.is_featured,
                   (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC LIMIT 1) AS main_image
            FROM products p
            WHERE p.category_id = ? AND p.is_active = 1 AND p.deleted_at IS NULL
            ORDER BY p.id DESC
        ");
        $stmt->execute([$category['id']]);
        $products = $stmt->fetchAll() ?: [];
    }

} catch (Exception $e) {
    error_log("Category page error: " . $e->getMessage());
    $category = ['name' => 'Kategori', 'slug' => ''];
    $parentCategory = null;
    $subCategories = [];
    $products = [];
}

// SEO Parameters
$pageTitle = $category['name'];
$metaTitle = "{$category['name']} | İncaksesuar";
$metaDescription = "İncaksesuar {$category['name']} ürünlerini ve model detaylarını inceleyin.";
$metaKeywords = "{$category['name']}, incaksesuar modelleri, hırdavat aksesuarları";

require_once __DIR__ . '/layout/header.php';
?>

<div class="container-wide" style="margin-top: 2rem; margin-bottom: 5rem;">
  
  <!-- Breadcrumb Navigation -->
  <div class="breadcrumb" style="margin-bottom: 2rem; font-size: 0.875rem; color: var(--text-muted); font-weight: 500;">
    <a href="/" style="transition: var(--transition);">Ana Sayfa</a>
    <span style="margin: 0 0.5rem; opacity: 0.5;">/</span>
    <?php if ($parentCategory && !$parentCategory['is_parent_active']): ?>
      <a href="/category/<?php echo htmlspecialchars($parentCategory['slug']); ?>" style="transition: var(--transition);"><?php echo htmlspecialchars($parentCategory['name']); ?></a>
      <span style="margin: 0 0.5rem; opacity: 0.5;">/</span>
    <?php endif; ?>
    <span style="color: var(--text-main); font-weight: 600;"><?php echo htmlspecialchars($category['name']); ?></span>
  </div>

  <!-- Split Layout -->
  <div class="catalog-layout">
    
    <!-- Left Sidebar: Categories Navigation Menu -->
    <aside class="catalog-sidebar">
      <?php if ($parentCategory): ?>
        <div class="sidebar-menu-box">
          <a href="/category/<?php echo htmlspecialchars($parentCategory['slug']); ?>" class="sidebar-menu-header <?php echo $parentCategory['is_parent_active'] ? 'active' : ''; ?>">
            <span><?php echo htmlspecialchars($parentCategory['name']); ?></span>
            <span class="badge-count">(<?php echo $parentCategory['count']; ?>)</span>
          </a>
          <ul class="sidebar-menu-list">
            <?php foreach ($subCategories as $sub): ?>
              <li>
                <a href="/category/<?php echo htmlspecialchars($sub['slug']); ?>" class="sidebar-menu-item <?php echo $slug === $sub['slug'] ? 'active' : ''; ?>">
                  <span><?php echo htmlspecialchars($sub['name']); ?></span>
                  <span class="badge-count">(<?php echo $sub['count']; ?>)</span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php else: ?>
        <!-- Fallback sidebar if category group is flat -->
        <div class="sidebar-menu-box">
          <span class="sidebar-menu-header active">Kategoriler</span>
          <ul class="sidebar-menu-list">
            <li>
              <a href="/products" class="sidebar-menu-item">
                <span>Tüm Ürünler</span>
              </a>
            </li>
          </ul>
        </div>
      <?php endif; ?>
    </aside>

    <!-- Right Panel: Main Grid Contents -->
    <main class="catalog-content">
      
      <!-- Category Header -->
      <div class="content-header" style="margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.25rem;">
        <h1 class="content-title" style="font-size: 2rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem; text-transform: uppercase;">
          <?php echo htmlspecialchars($category['name']); ?>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; font-weight: 500;">
          <?php if ($parentCategory && $parentCategory['is_parent_active']): ?>
            <?php echo $parentCategory['count']; ?> ürün gösteriliyor
          <?php else: ?>
            <?php echo count($products); ?> ürün listeleniyor
          <?php endif; ?>
        </p>
      </div>

      <!-- Content Display Router -->
      <?php if ($parentCategory && $parentCategory['is_parent_active']): ?>
        
        <!-- Case A: Main Parent Category - Display Subcategories Grid (Hüner-İş style!) -->
        <div class="subcategory-grid">
          <?php if (empty($subCategories)): ?>
            <div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 4rem; border: 1px dashed var(--border-color); border-radius: var(--radius-lg);">
              Bu kategori grubuna ait alt kategori bulunamadı.
            </div>
          <?php else: ?>
            <?php foreach ($subCategories as $sub): ?>
              <a href="/category/<?php echo htmlspecialchars($sub['slug']); ?>" class="subcategory-card">
                <div class="subcategory-image-box">
                  <?php if (!empty($sub['image'])): ?>
                    <img src="<?php echo htmlspecialchars($sub['image']); ?>" alt="<?php echo htmlspecialchars($sub['name']); ?>" class="subcategory-img" loading="lazy">
                  <?php else: ?>
                    <div class="subcategory-img-placeholder">
                      <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.3;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                      </svg>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="subcategory-card-footer">
                  <span class="subcategory-card-title"><?php echo htmlspecialchars($sub['name']); ?></span>
                  <span class="subcategory-card-count">(<?php echo $sub['count']; ?>)</span>
                </div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      <?php else: ?>
        
        <!-- Case B: Leaf Subcategory - Display actual products belonging here -->
        <div class="product-grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
          <?php if (empty($products)): ?>
            <div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 4rem; border: 1px dashed var(--border-color); border-radius: var(--radius-lg);">
              <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="margin-bottom: 1rem; color: var(--text-muted); opacity: 0.4;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
              </svg>
              <p style="font-size: 1.05rem; font-weight: 500;">Bu alt kategoride henüz ürün bulunmuyor.</p>
              <a href="/category/<?php echo htmlspecialchars($parentCategory['slug'] ?? 'products'); ?>" class="btn btn-primary" style="width: auto; margin-top: 1rem; display: inline-flex; font-size: 0.85rem; padding: 0.5rem 1.25rem;">Geri Dön</a>
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
                      <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                      </svg>
                    </div>
                  <?php endif; ?>
                </div>
                
                <div class="product-card-content" style="padding: 1.25rem;">
                  <span class="product-card-category" style="font-size: 0.7rem;"><?php echo htmlspecialchars($category['name']); ?></span>
                  <h3 class="product-card-title" style="font-size: 1rem; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($prod['name']); ?></h3>
                  <span class="product-card-sku" style="font-size: 0.75rem; margin-bottom: 0.75rem;">SKU: <?php echo htmlspecialchars($prod['sku']); ?></span>
                  <a href="/product/<?php echo htmlspecialchars($prod['slug']); ?>" class="product-card-btn" style="padding: 0.5rem 0; font-size: 0.85rem;">Detayları Gör</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      <?php endif; ?>
      
    </main>
    
  </div>
</div>

<?php
require_once __DIR__ . '/layout/footer.php';
?>
