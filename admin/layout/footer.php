      </main> <!-- /admin-content -->
    </div> <!-- /admin-main -->
  </div> <!-- /admin-layout -->

  <!-- Vanilla JS for Responsive Sidebar Toggle -->
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const toggleBtn = document.getElementById("sidebar-toggle");
      const sidebar = document.getElementById("admin-sidebar");
      
      if (toggleBtn && sidebar) {
        toggleBtn.addEventListener("click", function(e) {
          e.stopPropagation();
          sidebar.classList.toggle("active");
        });
        
        // Close sidebar when clicking outside on mobile viewports
        document.addEventListener("click", function(e) {
          if (window.innerWidth <= 768 && sidebar.classList.contains("active")) {
            if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
              sidebar.classList.remove("active");
            }
          }
        });
        
        // Handle window resize behaviors
        window.addEventListener("resize", function() {
          if (window.innerWidth > 768) {
            sidebar.classList.remove("active");
          }
        });
      }
    });
  </script>
</body>
</html>
