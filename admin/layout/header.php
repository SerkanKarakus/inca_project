<?php
/**
 * Admin Panel Header Layout.
 * Opens the main HTML wrapper and includes the sidebar navigation.
 */

// Double check authorization
if (!function_exists('is_admin') || !is_admin()) {
    header("Location: /admin/login.php");
    exit();
}

$current_user = $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | INCA Yönetim' : 'INCA Yönetim Paneli'; ?></title>
  
  <!-- CSS -->
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>

  <div class="admin-layout">
    
    <!-- Sidebar Navigation -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="admin-main">
      
      <!-- Top Header -->
      <header class="admin-header">
        <button class="header-toggle" id="sidebar-toggle" aria-label="Menüyü Aç/Kapat">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path>
          </svg>
        </button>
        
        <a href="/" target="_blank" class="view-site-link">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
          </svg>
          <span>Siteyi Görüntüle</span>
        </a>

        <div class="user-profile">
          <div class="user-avatar">
            <?php echo strtoupper(substr($current_user, 0, 1)); ?>
          </div>
          <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($current_user); ?></div>
            <div class="user-role">Sistem Yöneticisi</div>
          </div>
        </div>
      </header>
      
      <!-- Page Wrapper -->
      <main class="admin-content">
        
        <!-- Dynamic Page Title -->
        <div class="page-header">
          <h1 class="page-title"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Yönetim Paneli'; ?></h1>
          <?php if (isset($pageSubtitle)): ?>
            <p class="page-subtitle"><?php echo htmlspecialchars($pageSubtitle); ?></p>
          <?php endif; ?>
        </div>
