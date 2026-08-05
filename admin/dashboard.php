<?php
/**
 * Administrator Central Dashboard.
 */

// Include security functions
require_once __DIR__ . '/../config/auth.php';

// Force administrator authorization
require_admin();

$totalProducts = 0;
$totalCategories = 0;
$recentProducts = [];

try {
    $db = getDBConnection();
    
    // 1. Fetch Total Products (excluding soft-deleted ones)
    $stmt = $db->query("SELECT COUNT(*) AS total FROM products WHERE deleted_at IS NULL");
    $totalProducts = $stmt->fetch()['total'] ?? 0;
    
    // 2. Fetch Total Categories
    $stmt = $db->query("SELECT COUNT(*) AS total FROM categories");
    $totalCategories = $stmt->fetch()['total'] ?? 0;
    
    // 3. Fetch Recent Products (linked to categories)
    $stmt = $db->query("
        SELECT p.id, p.name, p.sku, p.is_active, p.created_at, c.name AS category_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.deleted_at IS NULL
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $recentProducts = $stmt->fetchAll() ?: [];
    
} catch (Exception $e) {
    // Graceful fallback for setups without imported schemas
    error_log("Dashboard data fetch error: " . $e->getMessage());
}

// Layout Metadata
$pageTitle = "Kontrol Paneli";
$pageSubtitle = "Genel site istatistikleri ve son eklenen ürünler";

// Load header
require_once __DIR__ . '/layout/header.php';
?>

<!-- Statistics Overview -->
<div class="stat-grid">
  <!-- Products Stat -->
  <div class="stat-card">
    <div class="stat-card-info">
      <span class="stat-card-title">Toplam Ürün</span>
      <span class="stat-card-value"><?php echo number_format($totalProducts); ?></span>
    </div>
    <div class="stat-card-icon primary">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
      </svg>
    </div>
  </div>
  
  <!-- Categories Stat -->
  <div class="stat-card">
    <div class="stat-card-info">
      <span class="stat-card-title">Toplam Kategori</span>
      <span class="stat-card-value"><?php echo number_format($totalCategories); ?></span>
    </div>
    <div class="stat-card-icon success">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
      </svg>
    </div>
  </div>
</div>

<!-- Recent Products Panel -->
<div class="card-panel">
  <div class="card-panel-header">
    <span class="card-panel-title">Son Eklenen Ürünler</span>
    <a href="/admin/products.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; width: auto;">Tüm Ürünleri Yönet</a>
  </div>
  
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Ürün Adı</th>
          <th>SKU</th>
          <th>Kategori</th>
          <th>Durum</th>
          <th>Eklenme Tarihi</th>
          <th>İşlemler</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recentProducts)): ?>
          <tr>
            <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 2rem;">
              Sistemde henüz kayıtlı ürün bulunmuyor. Ürün eklemek için ürün yönetim sayfasını kullanabilirsiniz.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($recentProducts as $product): ?>
            <tr>
              <td style="font-weight: 600;"><?php echo htmlspecialchars($product['name']); ?></td>
              <td><code><?php echo htmlspecialchars($product['sku']); ?></code></td>
              <td><?php echo htmlspecialchars($product['category_name'] ?? 'Kategorisiz'); ?></td>
              <td>
                <?php if ($product['is_active']): ?>
                  <span class="badge badge-success">Aktif</span>
                <?php else: ?>
                  <span class="badge badge-secondary">Pasif</span>
                <?php endif; ?>
              </td>
              <td><?php echo date('d.m.Y H:i', strtotime($product['created_at'])); ?></td>
              <td>
                <a href="/admin/products.php?action=edit&id=<?php echo $product['id']; ?>" class="btn btn-primary btn-action">Düzenle</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
// Load footer
require_once __DIR__ . '/layout/footer.php';
?>
