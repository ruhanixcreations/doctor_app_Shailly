// dashboard.js
// Handles dashboard card navigation + role-based visibility

/* ------------------ Loading overlay helpers ------------------ */
function createLoadingOverlay() {
  const overlay = document.createElement('div');
  overlay.id = 'dash-loading-overlay';
  overlay.style.position = 'fixed';
  overlay.style.inset = '0';
  overlay.style.background = 'rgba(0,0,0,0.3)';
  overlay.style.display = 'flex';
  overlay.style.justifyContent = 'center';
  overlay.style.alignItems = 'center';
  overlay.style.zIndex = '9999';
  overlay.style.backdropFilter = 'blur(4px)';

  const box = document.createElement('div');
  box.style.padding = '24px 32px';
  box.style.borderRadius = '12px';
  box.style.background = '#fff';
  box.style.boxShadow = '0 8px 32px rgba(0,0,0,0.2)';
  box.style.display = 'flex';
  box.style.alignItems = 'center';
  box.style.gap = '16px';
  box.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  box.style.fontSize = '16px';
  box.style.color = '#2d3748';

  const spinner = document.createElement('div');
  spinner.setAttribute('aria-hidden', 'true');
  spinner.style.width = '24px';
  spinner.style.height = '24px';
  spinner.style.border = '3px solid rgba(0,169,165,0.2)';
  spinner.style.borderTopColor = '#00A9A5';
  spinner.style.borderRadius = '50%';
  spinner.style.animation = 'dash-spin 0.8s linear infinite';

  if (!document.getElementById('dash-loading-style')) {
    const s = document.createElement('style');
    s.id = 'dash-loading-style';
    s.textContent = `@keyframes dash-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`;
    document.head.appendChild(s);
  }

  const label = document.createElement('div');
  label.textContent = 'Opening page…';

  box.appendChild(spinner);
  box.appendChild(label);
  overlay.appendChild(box);
  return overlay;
}

function showLoading(msg) {
  if (document.getElementById('dash-loading-overlay')) return;
  const overlay = createLoadingOverlay();
  if (msg) {
    const labelEl = overlay.querySelector('div div:last-child');
    if (labelEl) labelEl.textContent = msg;
  }
  document.body.appendChild(overlay);
  document.documentElement.style.overflow = 'hidden';
  document.body.style.overflow = 'hidden';
}

function hideLoading() {
  const el = document.getElementById('dash-loading-overlay');
  if (el) el.remove();
  document.documentElement.style.overflow = '';
  document.body.style.overflow = '';
}

function safeNavigate(url) {
  try { 
    window.location.href = url; 
  } catch(e) { 
    try { 
      window.location.assign(url); 
    } catch(e2) { 
      window.location = url; 
    } 
  }
}

/* Follow redirect then navigate (used for dashboard.php -> signup redirect) */
async function followRedirectAndNavigate(href) {
  showLoading('Opening page…');
  try {
    const resp = await fetch(href, { 
      method: 'GET', 
      credentials: 'include', 
      redirect: 'follow', 
      cache: 'no-store' 
    });
    // resp.url is final url after redirects (same-origin)
    if (resp && resp.url) { 
      hideLoading(); 
      safeNavigate(resp.url); 
      return; 
    }
    hideLoading(); 
    safeNavigate(href);
  } catch (err) {
    console.warn('dashboard.js: fetch failed, falling back to direct navigation', err);
    hideLoading(); 
    safeNavigate(href);
  }
}

/* ------------------ Role-based visibility ------------------ */
function hideCard(cardEl, reasonText) {
  if (!cardEl) return;
  // add aria-hidden for accessibility and visually hide
  cardEl.setAttribute('aria-hidden', 'true');
  cardEl.style.display = 'none';
}

/* Reads role from localStorage and hides cards accordingly */
function applyRoleVisibility() {
  const role = (localStorage.getItem('role') || '').trim().toLowerCase();
  // IDs used in your dashboard:
  const elAddReception = document.getElementById('card-add-reception');
  const elNewPrescription = document.getElementById('card-new-prescription');
  const elAddPatient = document.getElementById('card-add-patient');

  if (!role) {
    // no role present — show everything
    return;
  }

  if (role === 'receptionalist') {
    // hide add new prescription + add receptionist
    hideCard(elNewPrescription);
    hideCard(elAddReception);
  } else if (role === 'user') {
    // hide add new patient for user role
    hideCard(elAddPatient);
  }
}

/* ------------------ Main DOM logic ------------------ */
document.addEventListener('DOMContentLoaded', () => {
  // card elements
  const addReceptionEl = document.getElementById('card-add-reception');
  const viewPatientEl = document.getElementById('card-view-patient');
  const newPrescriptionEl = document.getElementById('card-new-prescription');
  const addPatientEl = document.getElementById('card-add-patient');

  // new cards
  const allMedicineEl = document.getElementById('card-all-medicine');
  const doctorsListEl = document.getElementById('card-doctors-list');
  const bloodTestsEl = document.getElementById('card-blood-tests');
  const receptionListEl = document.getElementById('card-receptionalist-list');

  // make sure elements are focusable (if not anchors)
  [addReceptionEl, viewPatientEl, newPrescriptionEl, addPatientEl,
   allMedicineEl, doctorsListEl, bloodTestsEl, receptionListEl].forEach(el => {
    if (!el) return;
    if (el.tagName.toLowerCase() !== 'a') {
      el.setAttribute('tabindex', '0');
      el.style.cursor = 'pointer';
    }
  });

  // Add Receptionalist: special flow (server sets session/redirect)
  if (addReceptionEl) {
    addReceptionEl.addEventListener('click', (ev) => {
      ev.preventDefault();
      const href = addReceptionEl.getAttribute('href') || 'dashboard.php?role=receptionalist';
      addReceptionEl.style.opacity = '0.8';
      followRedirectAndNavigate(href);
    });
    addReceptionEl.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' || ev.key === ' ') { 
        ev.preventDefault(); 
        addReceptionEl.click(); 
      }
    });
  }

  function attachSimpleNav(el, loadingText) {
    if (!el) return;
    el.addEventListener('click', (ev) => {
      ev.preventDefault();
      const href = el.getAttribute('href');
      if (!href) return;
      showLoading(loadingText || 'Opening page…');
      setTimeout(() => { 
        hideLoading(); 
        safeNavigate(href); 
      }, 180);
    });
    el.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' || ev.key === ' ') { 
        ev.preventDefault(); 
        el.click(); 
      }
    });
  }

  attachSimpleNav(viewPatientEl, 'Opening patient history…');
  attachSimpleNav(newPrescriptionEl, 'Opening prescription form…');
  attachSimpleNav(addPatientEl, 'Opening new patient form…');

  // attach nav for new cards
  attachSimpleNav(allMedicineEl, 'Opening medicine list…');
  attachSimpleNav(doctorsListEl, 'Opening doctors list…');
  attachSimpleNav(bloodTestsEl, 'Opening blood tests…');
  attachSimpleNav(receptionListEl, 'Opening receptionist list…');

  // Apply role-based hiding (uses localStorage.role set by signin flow)
  applyRoleVisibility();

  // Optional: watch for role changes in another tab/window and re-apply
  window.addEventListener('storage', (e) => {
    if (e.key === 'role') {
      setTimeout(() => applyRoleVisibility(), 100);
    }
  });
});
