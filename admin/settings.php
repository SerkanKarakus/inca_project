<?php
/**
 * Site Settings Configuration Admin Panel.
 * Manages slider content, SEO meta fields, and contact information dynamically.
 */

require_once __DIR__ . '/../config/auth.php';
require_admin();

$db = getDBConnection();
$error = '';
$success = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($token)) {
        $error = "Geçersiz CSRF token. Form gönderimi reddedildi.";
    } else {
        $submittedSettings = $_POST['settings'] ?? [];
        
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?");
            
            foreach ($submittedSettings as $key => $value) {
                // Ensure key exists and update value
                $stmt->execute([trim($value), $key]);
            }
            
            $db->commit();
            log_audit_action($_SESSION['user_id'], 'UPDATE_SETTINGS', 'site_settings', 0);
            $success = "Tüm ayarlar başarıyla güncellendi.";
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Update settings error: " . $e->getMessage());
            $error = "Ayarlar kaydedilirken bir hata oluştu.";
        }
    }
}

// Fetch Existing Settings
$settings = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    error_log("Fetch settings error: " . $e->getMessage());
    $error = "Mevcut ayarlar yüklenemedi.";
}

// Setup Page Details
$pageTitle = "Site Ayarları";
$pageSubtitle = "Ana sayfa içeriklerini, SEO alanlarını ve iletişim bilgilerini yönetin";

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

<form action="/admin/settings.php" method="POST" autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">

  <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); align-items: start; gap: 1.5rem;">
    
    <!-- Left Column: SEO & Home Content -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
      
      <!-- SEO Parameters Panel -->
      <div class="card-panel" style="margin-bottom: 0;">
        <div class="card-panel-header">
          <span class="card-panel-title">SEO & Sayfa Başlıkları</span>
        </div>
        <div style="padding: 1.5rem;">
          <div class="form-group">
            <label for="homepage_title" class="form-label">Ana Sayfa Tarayıcı Başlığı (Title)</label>
            <input type="text" id="homepage_title" name="settings[homepage_title]" class="form-control" value="<?php echo htmlspecialchars($settings['homepage_title'] ?? ''); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="homepage_description" class="form-label">Meta Açıklaması (Description)</label>
            <textarea id="homepage_description" name="settings[homepage_description]" class="form-control" style="height: 100px; resize: vertical;" required><?php echo htmlspecialchars($settings['homepage_description'] ?? ''); ?></textarea>
          </div>
          
          <div class="form-group">
            <label for="homepage_keywords" class="form-label">Meta Anahtar Kelimeler (Keywords)</label>
            <input type="text" id="homepage_keywords" name="settings[homepage_keywords]" class="form-control" value="<?php echo htmlspecialchars($settings['homepage_keywords'] ?? ''); ?>">
            <small style="display: block; margin-top: 0.25rem; color: var(--text-muted); font-size: 0.75rem;">Kelimeleri virgülle ayırın.</small>
          </div>
        </div>
      </div>
      
      <!-- Slider & Banner Panel -->
      <div class="card-panel" style="margin-bottom: 0;">
        <div class="card-panel-header">
          <span class="card-panel-title">Ana Sayfa Slider İçeriği</span>
        </div>
        <div style="padding: 1.5rem;">
          <div class="form-group">
            <label for="homepage_slider_title" class="form-label">Büyük Manşet Başlığı (Slider Title)</label>
            <input type="text" id="homepage_slider_title" name="settings[homepage_slider_title]" class="form-control" value="<?php echo htmlspecialchars($settings['homepage_slider_title'] ?? ''); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="homepage_slider_desc" class="form-label">Manşet Açıklaması (Slider Description)</label>
            <textarea id="homepage_slider_desc" name="settings[homepage_slider_desc]" class="form-control" style="height: 100px; resize: vertical;" required><?php echo htmlspecialchars($settings['homepage_slider_desc'] ?? ''); ?></textarea>
          </div>
        </div>
      </div>

    </div>
    
    <!-- Right Column: Corporate & Social -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
      
      <!-- Corporate Settings Panel -->
      <div class="card-panel" style="margin-bottom: 0;">
        <div class="card-panel-header">
          <span class="card-panel-title">İletişim & Firma Bilgileri</span>
        </div>
        <div style="padding: 1.5rem;">
          <div class="form-group">
            <label for="company_phone" class="form-label">Telefon Numarası</label>
            <input type="text" id="company_phone" name="settings[company_phone]" class="form-control" value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>">
          </div>
          
          <div class="form-group">
            <label for="company_email" class="form-label">E-posta Adresi</label>
            <input type="email" id="company_email" name="settings[company_email]" class="form-control" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>">
          </div>
          
          <div class="form-group">
            <label for="company_address" class="form-label">Firma Açık Adresi</label>
            <textarea id="company_address" name="settings[company_address]" class="form-control" style="height: 80px; resize: vertical;"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
          </div>
        </div>
      </div>

      <!-- Social Media Panel -->
      <div class="card-panel" style="margin-bottom: 0;">
        <div class="card-panel-header">
          <span class="card-panel-title">Sosyal Medya Bağlantıları</span>
        </div>
        <div style="padding: 1.5rem;">
          <div class="form-group">
            <label for="facebook_url" class="form-label">Facebook Sayfa Adresi (URL)</label>
            <input type="url" id="facebook_url" name="settings[facebook_url]" class="form-control" placeholder="https://..." value="<?php echo htmlspecialchars($settings['facebook_url'] ?? ''); ?>">
          </div>
          
          <div class="form-group">
            <label for="instagram_url" class="form-label">Instagram Profil Adresi (URL)</label>
            <input type="url" id="instagram_url" name="settings[instagram_url]" class="form-control" placeholder="https://..." value="<?php echo htmlspecialchars($settings['instagram_url'] ?? ''); ?>">
          </div>
        </div>
      </div>
      
      <!-- Save Button -->
      <button type="submit" class="btn btn-primary" style="padding: 1rem; font-size: 1rem; width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-weight: 700;">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
        </svg>
        <span>Ayarları Kaydet</span>
      </button>

    </div>
    
  </div>
</form>

<?php
require_once __DIR__ . '/layout/footer.php';
?>
