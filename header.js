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
    <h1 class="header-title"></h1>
  </div>

  <div class="header-right" id="navButtons">
    <!-- Will be populated by JavaScript -->
  </div>
</header>

<div class="hamburger-overlay" id="hamburgerOverlay"></div>

<nav class="hamburger-menu" id="hamburgerMenu">
  <div class="menu-header">
    <a href="../profile.html" class="menu-profile-link">
      <i class="fa-solid fa-user-circle"></i>
      <span>Profile</span>
    </a>
    <button class="menu-close-btn" id="menuCloseBtn" aria-label="Close menu">
      <i class="fa-solid fa-times"></i>
    </button>
  </div>

  <ul class="menu-list">
    <li class="menu-item">
      <a href="../dashboard/dashboard.html" class="menu-link" data-page="dashboard">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
      </a>
    </li>
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

// --- Setup User Header (matching reference implementation) ---
function setupUserHeader() {
  console.log('setupUserHeader() called');
  const navButtons = document.getElementById("navButtons");
  if (!navButtons) {
    console.error('❌ navButtons element not found!');
    return;
  }

  const userName = localStorage.getItem("userName");
  const isLoggedIn = localStorage.getItem("isLoggedIn") === "true";

  console.log('userName:', userName);
  console.log('isLoggedIn:', isLoggedIn);

  // 🟢 If localStorage isn't sure, verify PHP session live
  if (!isLoggedIn) {
    console.log('Checking session...');
    fetch("../check_session.php", { credentials: "include" })
      .then(res => res.json())
      .then(data => {
        console.log('Session data:', data);
        if (data.success && data.user_id) {
          localStorage.setItem("isLoggedIn", "true");
          // Only set userName if it doesn't exist
          if (!localStorage.getItem("userName")) {
            if (data.name) {
              localStorage.setItem("userName", data.name);
            } else if (data.email) {
              localStorage.setItem("userName", data.email.split("@")[0]);
            }
          }
          setupUserHeader(); // Rebuild header correctly
        } else {
          // No session found, show auth buttons
          console.log('No active session, showing auth buttons');
          showAuthButtons();
        }
      })
      .catch((err) => {
        console.error('Session check error:', err);
        // On error, show auth buttons
        showAuthButtons();
      });
    return; // Exit and wait for callback
  }

  if (userName && isLoggedIn) {
    const firstLetter = userName.charAt(0).toUpperCase();

    navButtons.innerHTML = `
      <div class="profile-container">
        <div class="profile-icon" title="${userName}">${firstLetter}</div>
        <div class="profile-dropdown-menu" id="profileDropdownMenu">
          <div class="profile-menu-item" id="profileMenuItem">
            <i class="fas fa-user"></i> Profile
          </div>
          <div class="profile-menu-item" id="logoutMenuItem">
            <i class="fas fa-sign-out-alt"></i> Logout
          </div>
        </div>
      </div>
    `;

    console.log('Profile icon created with letter:', firstLetter);

    const profileIcon = document.querySelector(".profile-icon");
    const profileDropdownMenu = document.getElementById("profileDropdownMenu");
    const profileMenuItem = document.getElementById("profileMenuItem");
    const logoutMenuItem = document.getElementById("logoutMenuItem");

    // Toggle profile dropdown menu on click
    if (profileIcon) {
      profileIcon.addEventListener("click", (e) => {
        e.stopPropagation();
        profileDropdownMenu.classList.toggle("show");
        console.log('Profile dropdown menu toggled');
      });
    }

    // Hide menu if clicked outside
    document.addEventListener("click", () => {
      if (profileDropdownMenu) {
        profileDropdownMenu.classList.remove("show");
      }
    });

    // ✅ Profile action
    if (profileMenuItem) {
      profileMenuItem.addEventListener("click", () => {
        // Profile files are in root workspace directory, same as header files
        window.location.href = '../profile.html';
      });
    }

    // ✅ Logout action
    if (logoutMenuItem) {
      logoutMenuItem.addEventListener("click", () => {
        handleLogout();
      });
    }

  } else {
    // Show Sign In / Sign Up buttons
    showAuthButtons();
  }

  console.log('✅ User header setup complete');
}

// --- Show Auth Buttons (for logged out users) ---
function showAuthButtons() {
  const navButtons = document.getElementById("navButtons");
  if (!navButtons) return;

  navButtons.innerHTML = `
    <div class="auth-buttons">
      <button class="signin-btn" onclick="window.location.href='../signin/signin.html'">Sign In</button>
      <button class="signup-btn" onclick="window.location.href='../signup/signup.html'">Sign Up</button>
    </div>
  `;
  console.log('✅ Auth buttons displayed');
}

// --- Logout Handler ---
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
        // ✅ Clear local session data
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('user_id');
        localStorage.removeItem('client_id');
        localStorage.removeItem('userName');
        localStorage.removeItem('name');
        localStorage.removeItem('username');
        localStorage.removeItem('user');
        localStorage.removeItem('role');

        console.log('Logout successful, showing auth buttons...');
        // Show auth buttons immediately
        showAuthButtons();
        
        // Optional: redirect after a short delay
        setTimeout(() => {
          window.location.href = '../signin/signin.html';
        }, 500);
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
  
  const headerPlaceholder = document.getElementById('header-placeholder');
  console.log('header-placeholder element:', headerPlaceholder);

  if (headerPlaceholder) {
    console.log('Inserting header HTML...');
    headerPlaceholder.innerHTML = headerHTML;
    console.log('✅ Header HTML inserted');

    // Add body padding class
    document.body.classList.add('has-header');
    console.log('✅ Body class added');

    // Wait for DOM to be ready before initializing
    setTimeout(() => {
      console.log('Calling initHeader()...');
      initHeader();
      
      console.log('Calling setupUserHeader()...');
      setupUserHeader();
    }, 200);
  } else {
    console.error('❌❌❌ header-placeholder element NOT FOUND! ❌❌❌');
  }
});

// --- Expose Global Function ---
window.updateHeaderProfile = function() {
  setupUserHeader();
};

// --- Periodic Session Validation ---
let sessionCheckInterval = null;
let isLoggingOut = false; // Prevent multiple simultaneous logouts

function startSessionMonitoring() {
  // Only monitor if user is logged in
  const isLoggedIn = localStorage.getItem("isLoggedIn") === "true";
  if (!isLoggedIn) return;
  
  // Clear any existing interval
  if (sessionCheckInterval) {
    clearInterval(sessionCheckInterval);
  }

  // Silent monitoring - check session every 3 seconds
  sessionCheckInterval = setInterval(() => {
    // Skip if already logging out
    if (isLoggingOut) return;
    
    fetch("../check_session.php", { credentials: "include" })
      .then(res => res.json())
      .then(data => {
        if (!data.success) {
          // Only log when there's an issue
          console.warn('❌ Session invalid:', data.message);
          forceLogout(data.message || 'Your session has expired');
        }
        // Silent when valid - no console logs
      })
      .catch(err => {
        console.error('⚠️ Session check error:', err);
      });
  }, 3000);
}

function forceLogout(message) {
  // Prevent multiple simultaneous logouts
  if (isLoggingOut) {
    console.log('Already logging out, skipping...');
    return;
  }
  
  isLoggingOut = true;
  console.log('🚨 Force logout triggered:', message);
  
  // Clear the interval immediately
  if (sessionCheckInterval) {
    clearInterval(sessionCheckInterval);
    sessionCheckInterval = null;
  }
  
  // Clear local storage
  localStorage.clear(); // Clear everything for security
  
  // Show alert
  alert(message);
  
  // Redirect to signin
  window.location.href = '../signin/signin.html';
}

// Start monitoring when page loads
function initSessionMonitoring() {
  const isLoggedIn = localStorage.getItem("isLoggedIn") === "true";
  if (isLoggedIn) {
    startSessionMonitoring();
  }
}

// Try multiple ways to ensure monitoring starts
if (document.readyState === 'loading') {
  // DOM is still loading
  document.addEventListener('DOMContentLoaded', () => {
    setTimeout(initSessionMonitoring, 1000);
  });
} else {
  // DOM already loaded
  setTimeout(initSessionMonitoring, 1000);
}

// Also try on window load as backup
window.addEventListener('load', () => {
  // Only start if not already started
  if (!sessionCheckInterval) {
    setTimeout(initSessionMonitoring, 500);
  }
});


console.log('=== HEADER.JS FINISHED LOADING ===');
