// Header Component JavaScript
(function() {
  'use strict';

  // Wait for DOM to be ready
  document.addEventListener('DOMContentLoaded', initializeHeader);

  function initializeHeader() {
    // Get elements
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const menuCloseBtn = document.getElementById('menuCloseBtn');
    const hamburgerOverlay = document.getElementById('hamburgerOverlay');
    const hamburgerMenu = document.getElementById('hamburgerMenu');
    const profileLetter = document.getElementById('profileLetter');
    const menuLinks = document.querySelectorAll('.menu-link');

    // Add body class for padding
    document.body.classList.add('has-header');

    // Set profile letter from localStorage
    setProfileLetter();

    // Hamburger menu toggle
    if (hamburgerBtn) {
      hamburgerBtn.addEventListener('click', openMenu);
    }

    if (menuCloseBtn) {
      menuCloseBtn.addEventListener('click', closeMenu);
    }

    if (hamburgerOverlay) {
      hamburgerOverlay.addEventListener('click', closeMenu);
    }

    // Handle menu link clicks
    menuLinks.forEach(link => {
      link.addEventListener('click', handleMenuLinkClick);
    });

    // Close menu on ESC key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && hamburgerMenu.classList.contains('active')) {
        closeMenu();
      }
    });

    // Apply role-based visibility to menu items
    applyRoleBasedVisibility();
  }

  function openMenu() {
    const hamburgerOverlay = document.getElementById('hamburgerOverlay');
    const hamburgerMenu = document.getElementById('hamburgerMenu');
    
    if (hamburgerMenu && hamburgerOverlay) {
      hamburgerMenu.classList.add('active');
      hamburgerOverlay.classList.add('active');
      document.body.style.overflow = 'hidden';
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

  function setProfileLetter() {
    const profileLetter = document.getElementById('profileLetter');
    if (!profileLetter) return;

    // Try to get name from localStorage
    const userName = localStorage.getItem('name') || localStorage.getItem('username') || localStorage.getItem('user');
    
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
      // Show loading state (optional)
      showLoadingState();
      
      // Close menu before navigation
      closeMenu();
      
      // Small delay for smooth closing animation
      setTimeout(() => {
        window.location.href = href;
      }, 200);
      
      e.preventDefault();
    }
  }

  function showLoadingState() {
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
      width: 50px;
      height: 50px;
      border: 4px solid rgba(125, 179, 232, 0.2);
      border-top-color: #7db3e8;
      border-radius: 50%;
      animation: header-spin 0.8s linear infinite;
      z-index: 9999;
    `;

    // Add animation if not exists
    if (!document.getElementById('header-loader-style')) {
      const style = document.createElement('style');
      style.id = 'header-loader-style';
      style.textContent = `
        @keyframes header-spin {
          from { transform: translate(-50%, -50%) rotate(0deg); }
          to { transform: translate(-50%, -50%) rotate(360deg); }
        }
      `;
      document.head.appendChild(style);
    }

    document.body.appendChild(loader);

    // Remove loader after 3 seconds (fallback)
    setTimeout(() => {
      const loaderEl = document.getElementById('header-loader');
      if (loaderEl) loaderEl.remove();
    }, 3000);
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

})();