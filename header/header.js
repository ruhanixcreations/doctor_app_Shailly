// Header Component JavaScript
(function() {
  'use strict';

  // Initialize function
  function initializeHeader() {
    console.log('Header: Initializing header...');
    
    // Get elements
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const menuCloseBtn = document.getElementById('menuCloseBtn');
    const hamburgerOverlay = document.getElementById('hamburgerOverlay');
    const hamburgerMenu = document.getElementById('hamburgerMenu');
    const profileIcon = document.getElementById('profileIcon');
    const profileLetter = document.getElementById('profileLetter');
    const menuLinks = document.querySelectorAll('.menu-link');

    console.log('Header: Elements found:', {
      hamburgerBtn: !!hamburgerBtn,
      menuCloseBtn: !!menuCloseBtn,
      hamburgerOverlay: !!hamburgerOverlay,
      hamburgerMenu: !!hamburgerMenu,
      profileIcon: !!profileIcon,
      profileLetter: !!profileLetter,
      menuLinksCount: menuLinks.length
    });

    // Add body class for padding
    document.body.classList.add('has-header');

    // Set profile letter from localStorage
    setProfileLetter();

    // Hamburger menu toggle
    if (hamburgerBtn) {
      console.log('Header: Adding click listener to hamburger button');
      hamburgerBtn.addEventListener('click', openMenu);
    } else {
      console.error('Header: Hamburger button not found!');
    }

    if (menuCloseBtn) {
      menuCloseBtn.addEventListener('click', closeMenu);
    }

    if (hamburgerOverlay) {
      hamburgerOverlay.addEventListener('click', closeMenu);
    }

    // Profile icon click to show logout
    if (profileIcon) {
      console.log('Header: Adding click listener to profile icon');
      profileIcon.addEventListener('click', toggleLogoutMenu);
    } else {
      console.error('Header: Profile icon not found!');
    }

    // Handle menu link clicks
    menuLinks.forEach(link => {
      link.addEventListener('click', handleMenuLinkClick);
    });

    // Close menu on ESC key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (hamburgerMenu && hamburgerMenu.classList.contains('active')) {
          closeMenu();
        }
        hideLogoutMenu();
      }
    });

    // Close logout menu when clicking outside
    document.addEventListener('click', (e) => {
      const logoutMenu = document.querySelector('.logout-menu');
      if (logoutMenu && !profileIcon.contains(e.target) && !logoutMenu.contains(e.target)) {
        hideLogoutMenu();
      }
    });

    // Apply role-based visibility to menu items
    applyRoleBasedVisibility();
  }

  function openMenu() {
    console.log('Header: Opening menu...');
    const hamburgerOverlay = document.getElementById('hamburgerOverlay');
    const hamburgerMenu = document.getElementById('hamburgerMenu');
    
    if (hamburgerMenu && hamburgerOverlay) {
      hamburgerMenu.classList.add('active');
      hamburgerOverlay.classList.add('active');
      document.body.style.overflow = 'hidden';
      console.log('Header: Menu opened successfully');
    } else {
      console.error('Header: Could not open menu - elements not found');
    }
  }

  function closeMenu() {
    const hamburgerOverlay = document.getElementById('hamburgerOverlay');
    const hamburgerMenu = document.getElementById('hamburgerMenu');
    
    if (hamburgerMenu && hamburgerOverlay) {
      hamburgerMenu.classList.remove('active');
      hamburgerOverlay.classList.remove('active');
      document.body.style.overflow = '';
    }
  }

  function toggleLogoutMenu(e) {
    console.log('Header: Toggling logout menu...');
    e.stopPropagation();
    
    // Create logout menu if it doesn't exist
    let logoutMenu = document.querySelector('.logout-menu');
    if (!logoutMenu) {
      console.log('Header: Creating logout menu');
      logoutMenu = document.createElement('div');
      logoutMenu.className = 'logout-menu';
      logoutMenu.textContent = 'Logout';
      logoutMenu.addEventListener('click', handleLogout);
      document.body.appendChild(logoutMenu);
    }
    
    logoutMenu.classList.toggle('show');
    console.log('Header: Logout menu toggled, visible:', logoutMenu.classList.contains('show'));
  }

  function hideLogoutMenu() {
    const logoutMenu = document.querySelector('.logout-menu');
    if (logoutMenu) {
      logoutMenu.classList.remove('show');
    }
  }

  function handleLogout() {
    if (confirm('Are you sure you want to logout?')) {
      // Show loading state
      showLoadingState('Logging out...');
      
      // Call logout.php
      fetch('../logout.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' }
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Clear localStorage
          localStorage.removeItem('isLoggedIn');
          localStorage.removeItem('user_id');
          localStorage.removeItem('client_id');
          localStorage.removeItem('userName');
          localStorage.removeItem('name');
          localStorage.removeItem('username');
          localStorage.removeItem('user');
          localStorage.removeItem('role');
          
          // Redirect to signin
          window.location.href = '../signin/signin.html';
        } else {
          alert('Logout failed: ' + (data.message || 'Unknown error'));
          hideLoadingState();
        }
      })
      .catch(error => {
        console.error('Logout error:', error);
        alert('Logout failed. Please try again.');
        hideLoadingState();
      });
    }
  }

  function setProfileLetter() {
    const profileLetter = document.getElementById('profileLetter');
    if (!profileLetter) return;

    // Try to get name from localStorage
    const userName = localStorage.getItem('name') || 
                     localStorage.getItem('username') || 
                     localStorage.getItem('user') ||
                     localStorage.getItem('userName');
    
    if (userName && userName.trim()) {
      // Get first letter of the name
      const firstLetter = userName.trim().charAt(0).toUpperCase();
      profileLetter.textContent = firstLetter;
    } else {
      // Default to 'U' if no name found
      profileLetter.textContent = 'U';
    }
  }

  function handleMenuLinkClick(e) {
    const link = e.currentTarget;
    const href = link.getAttribute('href');
    
    if (href && href !== '#') {
      // Show loading state
      showLoadingState('Opening page...');
      
      // Close menu before navigation
      closeMenu();
      
      // Small delay for smooth closing animation
      setTimeout(() => {
        window.location.href = href;
      }, 200);
      
      e.preventDefault();
    }
  }

  function showLoadingState(message) {
    // Create a simple loading indicator
    const existingLoader = document.getElementById('header-loader');
    if (existingLoader) return;

    const loader = document.createElement('div');
    loader.id = 'header-loader';
    loader.style.cssText = `
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: white;
      padding: 24px 32px;
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
      z-index: 9999;
      display: flex;
      align-items: center;
      gap: 16px;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      font-size: 16px;
      color: #2d3748;
    `;

    const spinner = document.createElement('div');
    spinner.style.cssText = `
      width: 24px;
      height: 24px;
      border: 3px solid rgba(0, 169, 165, 0.2);
      border-top-color: #00A9A5;
      border-radius: 50%;
      animation: header-spin 0.8s linear infinite;
    `;

    const text = document.createElement('span');
    text.textContent = message || 'Loading...';

    loader.appendChild(spinner);
    loader.appendChild(text);

    // Add animation if not exists
    if (!document.getElementById('header-loader-style')) {
      const style = document.createElement('style');
      style.id = 'header-loader-style';
      style.textContent = `
        @keyframes header-spin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }
      `;
      document.head.appendChild(style);
    }

    document.body.appendChild(loader);

    // Add overlay
    const overlay = document.createElement('div');
    overlay.id = 'header-loader-overlay';
    overlay.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.3);
      backdrop-filter: blur(2px);
      z-index: 9998;
    `;
    document.body.appendChild(overlay);
  }

  function hideLoadingState() {
    const loader = document.getElementById('header-loader');
    const overlay = document.getElementById('header-loader-overlay');
    if (loader) loader.remove();
    if (overlay) overlay.remove();
  }

  function applyRoleBasedVisibility() {
    const role = (localStorage.getItem('role') || '').trim().toLowerCase();
    
    if (!role) return;

    // Get menu items by data attribute
    const addReceptionLink = document.querySelector('.menu-link[data-page="add-reception"]');
    const addPrescriptionLink = document.querySelector('.menu-link[data-page="add-prescription"]');
    const addPatientLink = document.querySelector('.menu-link[data-page="add-patient"]');

    if (role === 'receptionalist') {
      // Hide add prescription and add receptionist for receptionalists
      hideMenuItem(addPrescriptionLink);
      hideMenuItem(addReceptionLink);
    } else if (role === 'user') {
      // Hide add patient for regular users
      hideMenuItem(addPatientLink);
    }
  }

  function hideMenuItem(linkElement) {
    if (!linkElement) return;
    
    // Hide the parent menu item
    const menuItem = linkElement.closest('.menu-item');
    if (menuItem) {
      menuItem.style.display = 'none';
      menuItem.setAttribute('aria-hidden', 'true');
    }
  }

  // Expose function to update profile letter (can be called from other scripts)
  window.updateHeaderProfile = function() {
    setProfileLetter();
  };

  // Auto-initialize when script loads or when DOM is ready
  console.log('Header: Script loaded, readyState:', document.readyState);
  if (document.readyState === 'loading') {
    console.log('Header: Waiting for DOMContentLoaded...');
    document.addEventListener('DOMContentLoaded', initializeHeader);
  } else {
    // DOM is already loaded, initialize immediately
    console.log('Header: DOM already loaded, initializing immediately');
    initializeHeader();
  }

})();
