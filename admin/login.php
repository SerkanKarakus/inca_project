<?php
/**
 * Administrator Secure Login Page.
 */

// Include configurations and authentication functions
require_once __DIR__ . '/../config/auth.php';

// If already logged in, redirect straight to dashboard
if (is_admin()) {
    header("Location: /admin/dashboard.php");
    exit();
}

$error = '';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    
    // 1. Verify CSRF Token
    if (!verify_csrf_token($submittedToken)) {
        header("HTTP/1.1 403 Forbidden");
        die("Invalid CSRF Token. Session expired or form tampered.");
    }
    
    // 2. Retrieve and clean inputs
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Lütfen tüm alanları doldurun.";
    } else {
        try {
            $db = getDBConnection();
            
            // 3. Query the user by username (prepared statement prevents SQLi)
            $stmt = $db->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            // 4. Verify password if user exists
            if ($user && password_verify($password, $user['password_hash'])) {
                // Check if user is an admin
                if ($user['role'] === 'admin') {
                    // Success: Initialize login session
                    login_user($user['id'], $user['username'], $user['role']);
                    
                    // Log successful login
                    log_audit_action($user['id'], 'LOGIN_SUCCESS', 'users', $user['id']);
                    
                    // Redirect to dashboard
                    header("Location: /admin/dashboard.php");
                    exit();
                } else {
                    $error = "Yetkisiz erişim denemesi.";
                    log_audit_action($user['id'], 'LOGIN_FAILED_UNAUTHORIZED', 'users', $user['id']);
                }
            } else {
                // Generic error to prevent username harvesting
                $error = "Geçersiz kullanıcı adı veya şifre.";
                log_audit_action(null, 'LOGIN_FAILED', 'users');
            }
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "Sistem hatası oluştu. Lütfen daha sonra tekrar deneyiniz.";
        }
    }
}

// Generate new CSRF token for the form
$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Yönetim Paneli Girişi | INCA</title>
  
  <!-- Styling -->
  <link rel="stylesheet" href="/assets/css/admin.css">
  
  <!-- SEO Meta -->
  <meta name="robots" content="noindex, nofollow">
</head>
<body>

  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <h1>INCA</h1>
        <p>Katalog Yönetim Paneli</p>
      </div>
      
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger" id="error-alert">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
          </svg>
          <span><?php echo htmlspecialchars($error); ?></span>
        </div>
      <?php endif; ?>
      
      <form action="" method="POST" autocomplete="off">
        <!-- Hidden CSRF field -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        
        <div class="form-group">
          <label for="username" class="form-label">Kullanıcı Adı</label>
          <input type="text" id="username" name="username" class="form-control" placeholder="admin" required autofocus autocomplete="off">
        </div>
        
        <div class="form-group">
          <label for="password" class="form-label">Şifre</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="off">
        </div>
        
        <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Giriş Yap</button>
      </form>
    </div>
  </div>

</body>
</html>
