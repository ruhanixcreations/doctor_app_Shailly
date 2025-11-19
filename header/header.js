// Header Component JavaScript
// --- Initialize Header ---
function initHeader() {
  const hamburgerBtn = document.getElementById('hamburgerBtn');
  const menuCloseBtn = document.getElementById('menuCloseBtn');
  const hamburgerOverlay = document.getElementById('hamburgerOverlay');
  const hamburgerMenu = document.getElementById('hamburgerMenu');

  if (!hamburgerBtn || !hamburgerMenu) return;

  // Hamburger menu toggle
  hamburgerBtn.addEventListener('click', () => {
    hamburgerMenu.classList.add('active');
    hamburgerOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  });

  // Close menu button
  if (menuCloseBtn) {
    menuCloseBtn.addEventListener('click', closeMenu);
  }

  // Close when clicking overlay
  if (hamburgerOverlay) {
    hamburgerOverlay.addEventListener('click', closeMenu);
  }

  // Close menu function
  function closeMenu() {
    hamburgerMenu.classList.remove('active');
    hamburgerOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  // Close on ESC key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && hamburgerMenu.classList.contains('active')) {
      closeMenu();
    }
  });

  // Apply role-based visibility
  applyRoleBasedVisibility();
}

// --- Load Header HTML ---
document.addEventListener('DOMContentLoaded', () => {
  const headerPlaceholder = document.getElementById('header-placeholder');

  if (headerPlaceholder) {
    fetch('../header/header.html')
      .then(res => res.text())
      .then(html => {
        headerPlaceholder.innerHTML = html;

        // Add body padding class
        document.body.classList.add('has-header');

        // Initialize header interactions
        initHeader();

        // Setup user profile
        setTimeout(() => setupUserProfile(), 100);
      })
      .catch(err => {
        console.error('Failed to load header:', err);
        console.error('Make sure header.html exists at: ../header/header.html');
      });
  } else {
    initHeader();
    setupUserProfile();
  }
});

// --- Setup User Profile ---
function setupUserProfile() {
  const profileIcon = document.getElementById('profileIcon');
  const profileLetter = document.getElementById('profileLetter');
  
  if (!profileIcon || !profileLetter) return;

  // Get user info from localStorage
  let userName = localStorage.getItem('userName') || 
                 localStorage.getItem('name') || 
                 localStorage.getItem('username') || 
                 localStorage.getItem('user');
  
  const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';

  // If not logged in, check session
  if (!isLoggedIn) {
    fetch('../check_session.php', { credentials: 'include' })
      .then(res => res.json())
      .then(data => {
        if (data.success && data.user_id) {
          localStorage.setItem('isLoggedIn', 'true');
          localStorage.setItem('user_id', data.user_id);
          if (data.email) {
            const nameFromEmail = data.email.split('@')[0];
            localStorage.setItem('userName', nameFromEmail);
            userName = nameFromEmail;
          }
          if (data.name) {
            localStorage.setItem('name', data.name);
            userName = data.name;
          }
          if (data.role) {
            localStorage.setItem('role', data.role);
          }
          // Update profile letter after setting userName
          updateProfileLetter();
          applyRoleBasedVisibility();
        }
      })
      .catch(err => console.error('Session check error:', err));
  }

  // Set profile letter
  if (userName && userName.trim()) {
    const firstLetter = userName.trim().charAt(0).toUpperCase();
    profileLetter.textContent = firstLetter;
    profileIcon.title = userName;
  } else {
    profileLetter.textContent = 'U';
  }

  // Profile icon click - show logout menu
  profileIcon.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleLogoutMenu();
  });

  // Hide logout menu when clicking outside
  document.addEventListener('click', () => {
    hideLogoutMenu();
  });
}

// --- Update Profile Letter ---
function updateProfileLetter() {
  const profileLetter = document.getElementById('profileLetter');
  if (!profileLetter) return;

  const userName = localStorage.getItem('userName') || 
                   localStorage.getItem('name') || 
                   localStorage.getItem('username') || 
                   localStorage.getItem('user');

  if (userName && userName.trim()) {
    const firstLetter = userName.trim().charAt(0).toUpperCase();
    profileLetter.textContent = firstLetter;
  } else {
    profileLetter.textContent = 'U';
  }
}

// --- Toggle Logout Menu ---
function toggleLogoutMenu() {
  let logoutMenu = document.querySelector('.logout-menu');
  
  if (!logoutMenu) {
    logoutMenu = document.createElement('div');
    logoutMenu.className = 'logout-menu';
    logoutMenu.textContent = 'Logout';
    logoutMenu.addEventListener('click', (e) => {
      e.stopPropagation();
      handleLogout();
    });
    document.body.appendChild(logoutMenu);
  }
  
  logoutMenu.classList.toggle('show');
}

// --- Hide Logout Menu ---
function hideLogoutMenu() {
  const logoutMenu = document.querySelector('.logout-menu');
  if (logoutMenu) {
    logoutMenu.classList.remove('show');
  }
}

// --- Handle Logout ---
function handleLogout() {
  if (confirm('Are you sure you want to logout?')) {
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
      }
    })
    .catch(error => {
      console.error('Logout error:', error);
      alert('Logout failed. Please try again.');
    });
  }
}

// --- Role-Based Visibility ---
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

// --- Hide Menu Item ---
function hideMenuItem(linkElement) {
  if (!linkElement) return;
  
  const menuItem = linkElement.closest('.menu-item');
  if (menuItem) {
    menuItem.style.display = 'none';
    menuItem.setAttribute('aria-hidden', 'true');
  }
}

// --- Expose Global Function ---
window.updateHeaderProfile = function() {
  updateProfileLetter();
};
