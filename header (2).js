// --- Redirect Functions ---
function redirectToSignin() {
  window.location.href = "/environment_project/signin/signin.html";
}

function redirectToSignup() {
  window.location.href = "/environment_project/signup/signup.html";
}

// --- Initialize Header ---
function initHeader() {
  const menuToggle = document.getElementById("menuToggle");
  const navButtons = document.getElementById("navButtons");

  if (!menuToggle || !navButtons) return;

  menuToggle.addEventListener("click", () => {
    navButtons.classList.toggle("show");
  });
}

// --- Load Header ---
document.addEventListener("DOMContentLoaded", () => {
  const headerPlaceholder = document.getElementById("header-placeholder");

  if (headerPlaceholder) {
    fetch("/environment_project/common/header/header.html")
      .then(res => res.text())
      .then(html => {
        headerPlaceholder.innerHTML = html;

        // Load CSS if not already loaded
        if (!document.querySelector('link[href="/environment_project/common/header/header.css"]')) {
          const link = document.createElement("link");
          link.rel = "stylesheet";
          link.href = "/environment_project/common/header/header.css";
          document.head.appendChild(link);
        }

        // Initialize header
        initHeader();

        // ✅ Wait briefly to let footer sync localStorage
        setTimeout(() => setupUserHeader(), 300);
      })
      .catch(err => console.error("Failed to load header:", err));
  } else {
    initHeader();
    setupUserHeader();
  }
});

// --- Handle Login/Logout Display ---
function setupUserHeader() {
  const navButtons = document.getElementById("navButtons");
  if (!navButtons) return;

  const userName = localStorage.getItem("userName");
  const isLoggedIn = localStorage.getItem("isLoggedIn") === "true";

  // 🟢 If localStorage isn’t sure, verify PHP session live
  if (!isLoggedIn) {
    fetch("/environment_project/check_session.php", { credentials: "include" })
      .then(res => res.json())
      .then(data => {
        if (data.success && data.user_id) {
          localStorage.setItem("isLoggedIn", "true");
          if (data.email) localStorage.setItem("userName", data.email.split("@")[0]);
          setupUserHeader(); // Rebuild header correctly
        }
      })
      .catch(() => {});
  }

  if (userName && isLoggedIn) {
    const firstLetter = userName.charAt(0).toUpperCase();

    navButtons.innerHTML = `
      <div class="profile-container">
        <div class="profile-icon" title="${userName}">${firstLetter}</div>
        <div class="logout-menu" id="logoutMenu">Logout</div>
      </div>
    `;

    const profileIcon = document.querySelector(".profile-icon");
    const logoutMenu = document.getElementById("logoutMenu");

    // Toggle logout menu on click
    profileIcon.addEventListener("click", (e) => {
      e.stopPropagation();
      logoutMenu.classList.toggle("show");
    });

    // Hide menu if clicked outside
    document.addEventListener("click", () => {
      logoutMenu.classList.remove("show");
    });

    // ✅ Logout via handleAuthClick()
    logoutMenu.addEventListener("click", () => {
      handleAuthClick();
    });

  } else {
    // Show Sign In / Sign Up buttons
    navButtons.innerHTML = `
      <button class="signin-btn" onclick="redirectToSignin()">Sign In</button>
      <button class="signup-btn" onclick="redirectToSignup()">Sign Up</button>
    `;
  }
}

// --- Logout Handler ---
function handleAuthClick() {
  const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';

  if (isLoggedIn) {
    fetch('/environment_project/logout.php', {
      method: 'POST',
      credentials: 'include', // Include session cookie
      headers: { 'Content-Type': 'application/json' }
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // ✅ Clear local session data
          localStorage.removeItem('isLoggedIn');
          localStorage.removeItem('user_id');
          localStorage.removeItem('client_id');
          localStorage.removeItem('userName');

          // Redirect to dashboard after logout
          window.location.href = '/environment_project/dashboard/dashboard.html';
        } else {
          alert('Logout failed: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Logout error:', error);
        alert('Logout failed. Please try again.');
      });
  } else {
    // Not logged in → go to Sign In
    window.location.href = '/environment_project/dashboard/dashboard.html';
  }
}

// ✅ Listen for footer session sync event (if footer fires one)
window.addEventListener("sessionUpdated", () => {
  setupUserHeader();
});
