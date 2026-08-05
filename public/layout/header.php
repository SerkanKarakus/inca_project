<?php
/**
 * Public Website Header Layout.
 * Handles SEO dynamic tags, metadata parameters, and responsive navigation.
 */
$current_page = basename($_SERVER['PHP_SELF']);

// Load Database Connection
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

$db = getDBConnection();
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

$menuStructure = [];
try {
    $allCats = $db->query("SELECT id, name, slug FROM categories ORDER BY name ASC")->fetchAll() ?: [];
    
    // Group categories
    foreach ($mainCategoryNames as $fullName => $prefix) {
        $slug = slugify($fullName);
        $id = 0;
        
        // Find existing parent category
        foreach ($allCats as $c) {
            if (strcasecmp($c['name'], $fullName) === 0) {
                $id = $c['id'];
                $slug = $c['slug'];
                break;
            }
        }
        
        $menuStructure[$fullName] = [
            'id' => $id,
            'slug' => $slug,
            'name' => $fullName,
            'subs' => []
        ];
    }
    
    // Distribute subcategories
    foreach ($allCats as $c) {
        foreach ($mainCategoryNames as $parentName => $prefix) {
            if (strcasecmp($c['name'], $parentName) === 0) {
                continue;
            }
            
            // Check if it starts with the prefix + space
            if (stripos($c['name'], $prefix . ' ') === 0) {
                $displayName = substr($c['name'], strlen($prefix) + 1);
                $menuStructure[$parentName]['subs'][] = [
                    'id' => $c['id'],
                    'slug' => $c['slug'],
                    'name' => mb_convert_case($displayName, MB_CASE_TITLE, "UTF-8")
                ];
            }
        }
    }
} catch (Exception $e) {
    error_log("Header menu query failed: " . $e->getMessage());
}

// Fetch site settings globally
$siteSettings = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings");
    while ($row = $stmt->fetch()) {
        $siteSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    error_log("Header site settings load error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Dynamic SEO Meta Tags -->
  <title><?php echo isset($metaTitle) && !empty($metaTitle) ? htmlspecialchars($metaTitle) : (isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' . htmlspecialchars($siteSettings['homepage_title'] ?? 'INCA') : htmlspecialchars($siteSettings['homepage_title'] ?? 'INCA Ürün Kataloğu')); ?></title>
  <meta name="description" content="<?php echo isset($metaDescription) && !empty($metaDescription) ? htmlspecialchars($metaDescription) : htmlspecialchars($siteSettings['homepage_description'] ?? 'INCA yenilikçi ve yüksek performanslı ürün katoloğu.'); ?>">
  <meta name="keywords" content="<?php echo isset($metaKeywords) && !empty($metaKeywords) ? htmlspecialchars($metaKeywords) : htmlspecialchars($siteSettings['homepage_keywords'] ?? 'inca, ürün kataloğu, teknoloji, cihazlar'); ?>">
  
  <!-- Stylesheet -->
  <link rel="stylesheet" href="/assets/css/style.css">
  
  <script>
    // Apply theme immediately to prevent FOUC
    (function() {
      const savedTheme = localStorage.getItem('inca-theme') || 'dark';
      document.documentElement.setAttribute('data-theme', savedTheme);
    })();
  </script>
</head>
<body>

  <!-- Site Navigation Header -->
  <header class="site-header">
    <div class="container navbar">
      <!-- Logo Block -->
      <div class="logo-block">
        <a href="/" class="logo-wrapper">
          <span class="logo-text">İncaksesuar</span>
          <span class="logo-sub">HIRDAVAT & MOBİLYA</span>
        </a>
      </div>
      
      <!-- Center Search Bar -->
      <div class="header-search">
        <form action="/products" method="GET" class="search-form-header">
          <input type="text" name="search" placeholder="Ne arıyorsunuz?" required class="search-input-header">
          <button type="submit" class="search-submit-header" aria-label="Ara">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
          </button>
        </form>
      </div>

      <!-- Right Actions (Badges + Login + Theme Toggle) -->
      <div class="header-actions">
        <a href="/products?featured=1" class="action-badge badge-campaign">
          <span class="badge-icon">%</span>
          <span class="badge-text">Kampanyalı Ürünler</span>
        </a>
        <a href="/products" class="action-badge badge-new">
          <svg class="badge-icon" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
          </svg>
          <span class="badge-text">Yeni Ürünler</span>
        </a>

        <a href="/admin/login.php" class="nav-btn">Yönetici Girişi</a>

        <!-- Theme Toggle Button -->
        <button id="theme-toggle-btn" class="theme-toggle-btn" aria-label="Temayı Değiştir">
          <!-- Sun icon (shown in dark mode) -->
          <svg class="theme-icon sun-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="5"></circle>
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>
          </svg>
          <!-- Moon icon (shown in light mode) -->
          <svg class="theme-icon moon-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
          </svg>
        </button>
      </div>
      
      <!-- Hamburger Menu for Mobile -->
      <button class="menu-toggle" id="menu-toggle" aria-label="Menüyü Göster/Gizle">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
      </button>

      <!-- Mobile navigation links drawer -->
      <ul class="nav-links" id="nav-links">
        <li class="mobile-search-item">
          <form action="/products" method="GET" class="search-form-header">
            <input type="text" name="search" placeholder="Ürün ara..." required class="search-input-header">
            <button type="submit" class="search-submit-header" aria-label="Ara">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
              </svg>
            </button>
          </form>
        </li>
        <li>
          <a href="/" class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">Ana Sayfa</a>
        </li>
        <li>
          <a href="/products" class="nav-link <?php echo $current_page === 'products.php' || $current_page === 'category.php' || $current_page === 'product.php' ? 'active' : ''; ?>">Ürünler</a>
        </li>
        <li>
          <a href="/contact" class="nav-link <?php echo $current_page === 'contact.php' ? 'active' : ''; ?>">İletişim</a>
        </li>
        <li>
          <a href="/admin/login.php" class="nav-btn" style="display: block; text-align: center;">Yönetici Girişi</a>
        </li>
      </ul>
    </div>
  </header>

  <!-- Category Mega Menu Bar -->
  <nav class="category-bar">
    <div class="container">
      <ul class="category-nav">
        <?php foreach ($menuStructure as $parentName => $menuItem): ?>
          <li class="category-item <?php echo !empty($menuItem['subs']) ? 'has-dropdown' : ''; ?>">
            <a href="/category/<?php echo htmlspecialchars($menuItem['slug']); ?>" class="category-link">
              <span><?php echo htmlspecialchars($menuItem['name']); ?></span>
              <?php if (!empty($menuItem['subs'])): ?>
                <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
                </svg>
              <?php endif; ?>
            </a>
            
            <?php if (!empty($menuItem['subs'])): ?>
              <ul class="dropdown-menu">
                <?php foreach ($menuItem['subs'] as $sub): ?>
                  <li>
                    <a href="/category/<?php echo htmlspecialchars($sub['slug']); ?>" class="dropdown-link">
                      <?php echo htmlspecialchars($sub['name']); ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </nav>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const themeToggleBtn = document.getElementById('theme-toggle-btn');
      if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
          const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
          const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
          document.documentElement.setAttribute('data-theme', newTheme);
          localStorage.setItem('inca-theme', newTheme);
        });
      }
    });
  </script>

