<?php
/**
 * Public Contact Page (contact.php).
 * Displays contact details and a secure contact form with database insertion.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$db = getDBConnection();
$error = '';
$success = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    // 1. Verify CSRF
    if (!verify_csrf_token($token)) {
        $error = "Geçersiz CSRF token. Lütfen sayfayı yenileyip tekrar deneyin.";
    } else {
        // 2. Sanitize and retrieve inputs
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        // 3. Validation
        if (empty($name) || empty($email) || empty($message)) {
            $error = "Lütfen tüm zorunlu alanları doldurunuz.";
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Lütfen geçerli bir e-posta adresi yazınız.";
        } else {
            try {
                // 4. Save to Database using prepared statements (SQLi safe)
                $stmt = $db->prepare("INSERT INTO contact_messages (name, email, message, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$name, $email, $message]);
                
                $success = "Mesajınız başarıyla alınmıştır. En kısa sürede sizinle iletişime geçeceğiz.";
                
                // Clear post fields
                $name = $email = $message = '';
            } catch (Exception $e) {
                error_log("Save contact message error: " . $e->getMessage());
                $error = "Mesaj kaydedilirken teknik bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.";
            }
        }
    }
}

// Generate CSRF token
$csrfToken = generate_csrf_token();

// Page Metadata
$pageTitle = "İletişim";
$metaTitle = "İletişim | INCA";
$metaDescription = "INCA ekibiyle iletişime geçin. Ürün detayları ve katalog talepleriniz için formu doldurabilirsiniz.";
$metaKeywords = "iletişim, mesaj gönder, eposta, telefon, inca adres";

require_once __DIR__ . '/layout/header.php';
?>

<div class="container">
  
  <div class="contact-grid">
    
    <!-- Left Column: Contact info panel -->
    <div class="contact-info-panel" style="margin-top: 2rem;">
      <h2>Bizimle İletişime Geçin</h2>
      <p style="color: var(--text-muted); font-size: 1.05rem;">
        Sorularınız, ürün talepleriniz veya iş birliği önerileriniz için aşağıdaki iletişim kanallarını kullanabilir ya da formu doldurarak doğrudan bize mesaj atabilirsiniz.
      </p>
      
      <ul class="contact-list">
        <!-- Address -->
        <li class="contact-item">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
          </svg>
          <div>
            <div class="contact-item-title">Adres</div>
            <div class="contact-item-val"><?php echo htmlspecialchars($siteSettings['company_address'] ?? 'İkitelli Keresteciler Sitesi 21. Blok No:15, Başakşehir / İstanbul'); ?></div>
          </div>
        </li>
        
        <!-- Phone -->
        <li class="contact-item">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
          </svg>
          <div>
            <div class="contact-item-title">Telefon</div>
            <div class="contact-item-val"><?php echo htmlspecialchars($siteSettings['company_phone'] ?? '0555 066 33 27'); ?></div>
          </div>
        </li>
        
        <!-- E-Mail -->
        <li class="contact-item">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
          </svg>
          <div>
            <div class="contact-item-title">E-Posta</div>
            <div class="contact-item-val"><?php echo htmlspecialchars($siteSettings['company_email'] ?? 'info@incahirdavat.com'); ?></div>
          </div>
        </li>
      </ul>
    </div>
    
    <!-- Right Column: Form Panel -->
    <div class="contact-form-panel">
      
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
      
      <form action="/contact" method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        
        <div class="form-group">
          <label for="name" class="form-label">Adınız Soyadınız *</label>
          <input type="text" id="name" name="name" class="form-control" placeholder="Örn: Ahmet Yılmaz" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required>
        </div>
        
        <div class="form-group">
          <label for="email" class="form-label">E-Posta Adresiniz *</label>
          <input type="email" id="email" name="email" class="form-control" placeholder="Örn: ahmet@mail.com" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
        </div>
        
        <div class="form-group">
          <label for="message" class="form-label">Mesajınız *</label>
          <textarea id="message" name="message" class="form-control" rows="6" placeholder="Mesajınızı veya ürün talebinizi detaylı olarak buraya yazınız..." required><?php echo isset($message) ? htmlspecialchars($message) : ''; ?></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary" style="margin-top: 1rem; width: 100%;">Mesajı Gönder</button>
      </form>
      
    </div>
    
  </div>
  
</div>

<?php
require_once __DIR__ . '/layout/footer.php';
?>
