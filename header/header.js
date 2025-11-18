// Header JavaScript for interactive features

document.addEventListener('DOMContentLoaded', function() {
  // User menu toggle
  const userMenuBtn = document.getElementById('userMenuBtn');
  const userMenu = document.querySelector('.user-menu');
  
  if (userMenuBtn && userMenu) {
    userMenuBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      userMenu.classList.toggle('active');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
      if (!userMenu.contains(e.target)) {
        userMenu.classList.remove('active');
      }
    });
  }
  
  // Menu toggle for mobile (can be extended for sidebar)
  const menuToggle = document.getElementById('menuToggle');
  if (menuToggle) {
    menuToggle.addEventListener('click', function() {
      console.log('Menu toggle clicked');
      // Add your sidebar toggle logic here
      // Example: document.querySelector('.sidebar').classList.toggle('active');
    });
  }
  
  // Search box functionality
  const searchInput = document.querySelector('.search-box input');
  if (searchInput) {
    searchInput.addEventListener('input', function(e) {
      const query = e.target.value.trim();
      if (query.length > 2) {
        console.log('Searching for:', query);
        // Add your search logic here
      }
    });
  }
  
  // Notification button (can be extended)
  const notificationBtn = document.querySelector('.notification-btn');
  if (notificationBtn) {
    notificationBtn.addEventListener('click', function() {
      console.log('Notifications clicked');
      // Add your notification panel logic here
    });
  }
});
