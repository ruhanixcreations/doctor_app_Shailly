// Header Component JavaScript
// --- Header HTML Template ---
const headerHTML = `
<!-- Header Component -->
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

<!-- Hamburger Menu Overlay -->
<div class="hamburger-overlay" id="hamburgerOverlay"></div>

<!-- Hamburger Menu Sidebar -->
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

// --- Initialize Header ---
function initHeader() {
  const hamburgerBtn = document.getElementById('hamburgerBtn');
  const menuCloseBtn = document.getElementById('menuCloseBtn');
  const hamburgerOverlay = document.getElementById('hamburgerOverlay');
  const hamburgerMenu = document.getElementById('hamburgerMenu');

  if (!hamburgerBtn || !hamburgerMenu) {
    console.error('Header elements not found!');
    return;
  }

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
    // Insert header HTML
    headerPlaceholder.innerHTML = headerHTML;

    // Add body padding class
    document.body.classList.add('has-header');

    // Initialize header interactions
    initHeader();

    // Setup user profile
    setTimeout(() => setupUserProfile(), 100);
  } else {
    console.error('header-placeholder not found!');
  }
});

// --- Setup User Profile ---
function setupUserProfile() {
  const profileIcon = document.getElementById('profileIcon');
  const profileLetter = document.getElementById('profileLetter');
  
  if (!profileIcon || !profileLetter) {
    console.error('Profile icon or letter element not found!');
    return;
  }

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

// Debug info
console.log('Header JS loaded successfully');
