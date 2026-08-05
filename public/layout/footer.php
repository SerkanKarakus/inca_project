<?php
/**
 * Public Website Footer Layout.
 * Closes wrappers and runs Vanilla JS for mobile nav toggles.
 */
?>
  <!-- Site Footer -->
  <footer class="site-footer">
    <div class="container">
      <ul class="footer-links">
        <li><a href="/" class="footer-link">Ana Sayfa</a></li>
        <li><a href="/products" class="footer-link">Ürünler</a></li>
        <li><a href="/contact" class="footer-link">İletişim</a></li>
        <li><a href="/admin/login.php" class="footer-link">Yönetici Girişi</a></li>
      </ul>
      <p>&copy; <?php echo date('Y'); ?> İncaksesuar. Tüm Hakları Saklıdır. <?php if (defined('LAST_UPDATE')): ?>| Son Güncelleme: <?php echo htmlspecialchars(LAST_UPDATE); ?><?php endif; ?></p>
    </div>
  </footer>

  <!-- Vanilla JS for Mobile Nav Toggle -->
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const menuToggle = document.getElementById("menu-toggle");
      const navLinks = document.getElementById("nav-links");
      
      if (menuToggle && navLinks) {
        menuToggle.addEventListener("click", function(e) {
          e.stopPropagation();
          navLinks.classList.toggle("active");
        });
        
        // Close menu if user clicks outside of it
        document.addEventListener("click", function(e) {
          if (navLinks.classList.contains("active") && !navLinks.contains(e.target) && !menuToggle.contains(e.target)) {
            navLinks.classList.remove("active");
          }
        });
        
        // Remove active class on window resize
        window.addEventListener("resize", function() {
          if (window.innerWidth > 768) {
            navLinks.classList.remove("active");
          }
        });
      }
    });
  </script>
</body>
</html>
