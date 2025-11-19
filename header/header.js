// Header Component JavaScript
console.log('=== HEADER.JS STARTED LOADING ===');

// --- Header HTML Template ---
const headerHTML = `
<header class="app-header">
  <div class="header-left">
    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Open menu">
      <span class="hamburger-line"></span>
      <span class="hamburger-line"></span>
      <span class="hamburger-line"></span>
    </button>
  </div>

  <div class="header-center">
    <h1 class="header-title">Dashboard</h1>
  </div>

  <div class="header-right">
    <div class="profile-icon" id="profileIcon" title="User Profile">
      <span class="profile-letter" id="profileLetter">U</span>
    </div>
  </div>
</header>

<div class="hamburger-overlay" id="hamburgerOverlay"></div>

<nav class="hamburger-menu" id="hamburgerMenu">
  <div class="menu-header">
    <h2 class="menu-title">Navigation</h2>
    <button class="menu-close-btn" id="menuCloseBtn" aria-label="Close menu">
      <i class="fa-solid fa-times"></i>
    </button>
  </div>

  <ul class="menu-list">
    <li class="menu-item">
      <a href="../dashboard/dashboard.php?role=receptionalist" class="menu-link" data-page="add-reception">
        <i class="fa-solid fa-user-plus"></i>
        <span>Add Receptionalist</span>
      </a>
    </li>
    <li class="menu-item">
      <a href="../patient_history/patient_history.html" class="menu-link" data-page="patient-history">
        <i class="fa-solid fa-users"></i>
        <span>Patient History</span>
      </a>
    </li>
    <li class="menu-item">
      <a href="../add_new_patient/add_new_patient.html" class="menu-link" data-page="add-patient">
        <i class="fa-solid fa-user-plus"></i>
        <span>Add New Patient</span>
      </a>
    </li>
    <li class="menu-item">
      <a href="../add_prescription/add_prescription.html" class="menu-link" data-page="add-prescription">
        <i class="fa-solid fa-prescription-bottle-medical"></i>
        <span>Add New Prescription</span>
      </a>
    </li>
    <li class="menu-item">
      <a href="../all_medicine/all_medicine.html" class="menu-link" data-page="all-medicine">
        <i class="fa-solid fa-pills"></i>
        <span>All Medicine</span>
      </a>
    </li>
    <li class="menu-item">
      <a href="../doctors_list/doctors_list.html" class="menu-link" data-page="doctors-list">
        <i class="fa-solid fa-user-doctor"></i>
        <span>Doctors List</span>
      </a>
    </li>
    <li class="menu-item">
      <a href="../blood_tests/blood_tests.html" class="menu-link" data-page="blood-tests">
        <i class="fa-solid fa-vials"></i>
        <span>Blood Test List</span>
      </a>
    </li>
    <li class="menu-item">
      <a href="../receptionalist_list/receptionalist_list.html" class="menu-link" data-page="receptionalist-list">
        <i class="fa-solid fa-user-tie"></i>
        <span>Receptionalist List</span>
      </a>
    </li>
  </ul>
</nav>
`;

console.log('Header HTML template defined');

// --- Initialize Header ---
function initHeader() {
  console.log('initHeader() called');
  const hamburgerBtn = document.getElementById('hamburgerBtn');
  const menuCloseBtn = document.getElementById('menuCloseBtn');
  const hamburgerOverlay = document.getElementById('hamburgerOverlay');
  const hamburgerMenu = document.getElementById('hamburgerMenu');

  console.log('Header elements:', {
    hamburgerBtn: !!hamburgerBtn,
    menuCloseBtn: !!menuCloseBtn,
    hamburgerOverlay: !!hamburgerOverlay,
    hamburgerMenu: !!hamburgerMenu
  });

  if (!hamburgerBtn || !hamburgerMenu) {
    console.error('❌ Critical header elements not found!');
    return;
  }

  // Hamburger menu toggle
  hamburgerBtn.addEventListener('click', () => {
    console.log('Hamburger clicked');
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
    console.log('Closing menu');
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
  
  console.log('✅ Header initialized successfully');
}

// --- Setup User Profile ---
function setupUserProfile() {
  console.log('setupUserProfile() called');
  const headerRight = document.querySelector('.header-right');
  
  if (!headerRight) {
    console.error('❌ Header right section not found!');
    return;
  }

  // Get user info from localStorage
  let userName = localStorage.getItem('userName') || 
                 localStorage.getItem('name') || 
                 localStorage.getItem('username') || 
                 localStorage.getItem('user');
  
  console.log('User name from localStorage:', userName);

  const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
  console.log('Is logged in:', isLoggedIn);

  // If not logged in, check session
  if (!isLoggedIn || !userName) {
    console.log('Checking session...');
    fetch('../check_session.php', { credentials: 'include' })
      .then(res => res.json())
      .then(data => {
        console.log('Session data:', data);
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
          // User is logged in, show profile icon
          showProfileIcon(userName);
          applyRoleBasedVisibility();
        } else {
          // User is not logged in, show sign in/sign up buttons
          showAuthButtons();
        }
      })
      .catch(err => {
        console.error('Session check error:', err);
        // On error, show auth buttons
        showAuthButtons();
      });
  } else {
    // User is logged in, show profile icon
    showProfileIcon(userName);
  }
  
  console.log('✅ Profile setup complete');
}

// --- Show Profile Icon (for logged in users) ---
function showProfileIcon(userName) {
  const headerRight = document.querySelector('.header-right');
  if (!headerRight) return;

  const firstLetter = userName && userName.trim() ? userName.trim().charAt(0).toUpperCase() : 'U';
  
  headerRight.innerHTML = `
    <div class="profile-icon" id="profileIcon" title="${userName || 'User'}">
      <span class="profile-letter" id="profileLetter">${firstLetter}</span>
    </div>
  `;

  console.log('Profile letter set to:', firstLetter);

  // Profile icon click - show logout menu
  const profileIcon = document.getElementById('profileIcon');
  if (profileIcon) {
    profileIcon.addEventListener('click', (e) => {
      console.log('Profile icon clicked');
      e.stopPropagation();
      toggleLogoutMenu();
    });
  }

  // Hide logout menu when clicking outside
  document.addEventListener('click', () => {
    hideLogoutMenu();
  });
}

// --- Show Auth Buttons (for logged out users) ---
function showAuthButtons() {
  const headerRight = document.querySelector('.header-right');
  if (!headerRight) return;

  headerRight.innerHTML = `
    <div class="auth-buttons">
      <button class="signin-btn" onclick="window.location.href='../signin/signin.html'">Sign In</button>
      <button class="signup-btn" onclick="window.location.href='../signup/signup.html'">Sign Up</button>
    </div>
  `;

  console.log('Auth buttons displayed');
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
    console.log('Profile letter updated to:', firstLetter);
  } else {
    profileLetter.textContent = 'U';
  }
}

// --- Toggle Logout Menu ---
function toggleLogoutMenu() {
  console.log('toggleLogoutMenu() called');
  let logoutMenu = document.querySelector('.logout-menu');
  
  if (!logoutMenu) {
    console.log('Creating logout menu');
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
  console.log('Logout menu visible:', logoutMenu.classList.contains('show'));
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
  console.log('handleLogout() called');
  if (confirm('Are you sure you want to logout?')) {
    console.log('Logging out...');
    fetch('../logout.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
      console.log('Logout response:', data);
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
        
        console.log('Redirecting to signin...');
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
  console.log('Applying role-based visibility. Role:', role);
  
  if (!role) return;

  const addReceptionLink = document.querySelector('.menu-link[data-page="add-reception"]');
  const addPrescriptionLink = document.querySelector('.menu-link[data-page="add-prescription"]');
  const addPatientLink = document.querySelector('.menu-link[data-page="add-patient"]');

  if (role === 'receptionalist') {
    console.log('Hiding items for receptionalist');
    hideMenuItem(addPrescriptionLink);
    hideMenuItem(addReceptionLink);
  } else if (role === 'user') {
    console.log('Hiding items for user');
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

// --- Load Header HTML ---
console.log('Setting up DOMContentLoaded listener...');
document.addEventListener('DOMContentLoaded', () => {
  console.log('=== DOMContentLoaded fired ===');
  console.log('Document ready state:', document.readyState);
  
  const headerPlaceholder = document.getElementById('header-placeholder');
  console.log('header-placeholder element:', headerPlaceholder);

  if (headerPlaceholder) {
    console.log('Inserting header HTML...');
    // Insert header HTML
    headerPlaceholder.innerHTML = headerHTML;
    console.log('✅ Header HTML inserted');

    // Add body padding class
    document.body.classList.add('has-header');
    console.log('✅ Body class added');

    // Wait for DOM to be ready before initializing
    setTimeout(() => {
      console.log('Calling initHeader()...');
      initHeader();
      
      console.log('Calling setupUserProfile()...');
      setupUserProfile();
    }, 200);
  } else {
    console.error('❌❌❌ header-placeholder element NOT FOUND! ❌❌❌');
    console.log('Available elements with id:', Array.from(document.querySelectorAll('[id]')).map(el => el.id));
  }
});

// --- Expose Global Function ---
window.updateHeaderProfile = function() {
  updateProfileLetter();
};

console.log('=== HEADER.JS FINISHED LOADING ===');
