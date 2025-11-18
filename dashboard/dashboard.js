// dashboard.js (full)
// Handles dashboard card navigation + role-based visibility
// - Follows server redirect when adding receptionist (same-origin fetch + redirect follow).
// - Shows loading overlay during navigation.
// - Hides dashboard cards based on role stored in localStorage.role
//   * receptionalist -> hide: card-new-prescription, card-add-reception
//   * user         -> hide: card-add-patient

const dashboardState = {
  cards: [],
  cardsEmpty: null,
  statRole: null,
  statClient: null,
  statShortcut: null
};

function syncCardDisplay(card) {
  if (!card) return;
  const hiddenByRole = card.dataset.roleHidden === 'true';
  const hiddenByFilter = card.dataset.filterHidden === 'true';
  const shouldHide = hiddenByRole || hiddenByFilter;
  card.style.display = shouldHide ? 'none' : '';
  card.setAttribute('aria-hidden', shouldHide ? 'true' : 'false');
}

function resetRoleVisibility() {
  dashboardState.cards.forEach(card => {
    delete card.dataset.roleHidden;
    syncCardDisplay(card);
  });
}

function updateStats() {
  if (dashboardState.statRole) {
    const roleRaw = (localStorage.getItem('role') || '').trim();
    dashboardState.statRole.textContent = roleRaw
      ? roleRaw.charAt(0).toUpperCase() + roleRaw.slice(1)
      : '—';
  }
  if (dashboardState.statClient) {
    const clientId = (localStorage.getItem('client_id') || '').trim();
    dashboardState.statClient.textContent = clientId || '—';
  }
  updateShortcutStat();
}

function getVisibleShortcutCount() {
  return dashboardState.cards.filter(card => card.style.display !== 'none').length;
}

function updateShortcutStat(count) {
  if (!dashboardState.statShortcut) return;
  const value = typeof count === 'number' ? count : getVisibleShortcutCount();
  dashboardState.statShortcut.textContent = value.toString().padStart(2, '0');
}

function initCardFilter(inputEl, emptyStateEl) {
  if (!inputEl) {
    updateShortcutStat();
    return;
  }
  const handleFilter = () => {
    const query = (inputEl.value || '').trim().toLowerCase();
    let visibleCount = 0;
    dashboardState.cards.forEach(card => {
      const filterText = (card.dataset.filterValue || card.textContent || '').toLowerCase();
      if (query && !filterText.includes(query)) {
        card.dataset.filterHidden = 'true';
      } else {
        delete card.dataset.filterHidden;
      }
      syncCardDisplay(card);
      if (card.style.display !== 'none') visibleCount++;
    });
    if (emptyStateEl) emptyStateEl.classList.toggle('visible', visibleCount === 0);
    updateShortcutStat(visibleCount);
  };
  inputEl.addEventListener('input', handleFilter);
  handleFilter();
}

/* ------------------ Loading overlay helpers ------------------ */
function createLoadingOverlay() {
  const overlay = document.createElement('div');
  overlay.id = 'dash-loading-overlay';
  overlay.style.position = 'fixed';
  overlay.style.inset = '0';
  overlay.style.background = 'rgba(6,12,24,0.26)';
  overlay.style.display = 'flex';
  overlay.style.justifyContent = 'center';
  overlay.style.alignItems = 'center';
  overlay.style.zIndex = '9999';
  overlay.style.backdropFilter = 'blur(2px)';

  const box = document.createElement('div');
  box.style.padding = '16px 18px';
  box.style.borderRadius = '10px';
  box.style.background = '#fff';
  box.style.boxShadow = '0 10px 28px rgba(11,18,35,0.12)';
  box.style.display = 'flex';
  box.style.alignItems = 'center';
  box.style.gap = '12px';
  box.style.fontFamily = 'Inter, system-ui, Arial, sans-serif';
  box.style.fontSize = '15px';
  box.style.color = '#0b2340';

  const spinner = document.createElement('div');
  spinner.setAttribute('aria-hidden', 'true');
  spinner.style.width = '24px';
  spinner.style.height = '24px';
  spinner.style.border = '3px solid rgba(15,30,80,0.12)';
  spinner.style.borderTopColor = '#2c63d6';
  spinner.style.borderRadius = '50%';
  spinner.style.animation = 'dash-spin 1s linear infinite';

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
  if (msg) overlay.querySelector('div div') && (overlay.querySelector('div div').textContent = msg); // best-effort
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
  try { window.location.href = url; }
  catch(e){ try{ window.location.assign(url) } catch(e2) { window.location = url; } }
}

/* Follow redirect then navigate (used for dashboard.php -> signup redirect) */
async function followRedirectAndNavigate(href) {
  showLoading('Opening signup page…');
  try {
    const resp = await fetch(href, { method: 'GET', credentials: 'include', redirect: 'follow', cache: 'no-store' });
    // resp.url is final url after redirects (same-origin)
    if (resp && resp.url) { hideLoading(); safeNavigate(resp.url); return; }
    hideLoading(); safeNavigate(href);
  } catch (err) {
    console.warn('dashboard.js: fetch failed, falling back to direct navigation', err);
    hideLoading(); safeNavigate(href);
  }
}

/* ------------------ Role-based visibility ------------------ */
function hideCard(cardEl, reasonText) {
  if (!cardEl) return;
  cardEl.dataset.roleHidden = 'true';
  syncCardDisplay(cardEl);
  // optional small notice appended to the header (only once)
  if (reasonText) {
    let info = document.getElementById('dashboard-role-info');
    if (!info) {
      info = document.createElement('div');
      info.id = 'dashboard-role-info';
      info.className = 'role-alert';
      const container = document.querySelector('.shortcut-section') || document.querySelector('.page-shell') || document.body;
      container.insertBefore(info, container.firstChild || null);
    }
    // set text (if multiple calls, keep the first message)
    if (!info.textContent) info.textContent = reasonText;
  }
}

/* Reads role from localStorage and hides cards accordingly */
function applyRoleVisibility() {
  resetRoleVisibility();
  const role = (localStorage.getItem('role') || '').trim().toLowerCase();
  // IDs used in your dashboard:
  const elAddReception = document.getElementById('card-add-reception');         // Add Receptionalist
  const elNewPrescription = document.getElementById('card-new-prescription');  // Add New Prescription
  const elAddPatient = document.getElementById('card-add-patient');            // Add New Patient

  if (!role) {
    updateStats();
    return;
  }

  if (role === 'receptionalist') {
    // hide add new prescription + add receptionist
    hideCard(elNewPrescription, 'Some options are hidden for receptionist accounts.');
    hideCard(elAddReception, 'Receptionist accounts cannot create receptionist or prescriptions.');
  } else if (role === 'user') {
    // hide add new patient for user role
    hideCard(elAddPatient, 'Add new patient is available to staff only.');
  } else {
    // other roles — no changes (or add additional rules here)
  }

  updateStats();
}

/* ------------------ Main DOM logic ------------------ */
document.addEventListener('DOMContentLoaded', () => {
  const cardsContainer = document.getElementById('dashboardCards');
  dashboardState.cards = Array.from(cardsContainer?.querySelectorAll('.shortcut-card') || []);
  dashboardState.cards.forEach(card => {
    card.dataset.filterValue = (card.innerText || '').toLowerCase();
  });
  dashboardState.cardsEmpty = document.getElementById('cardsEmpty');
  dashboardState.statRole = document.querySelector('[data-stat-role]');
  dashboardState.statClient = document.querySelector('[data-stat-client]');
  dashboardState.statShortcut = document.querySelector('[data-stat-shortcuts]');

  const filterInput = document.getElementById('cardFilter');
  const heroAddPatientBtn = document.getElementById('heroAddPatient');
  const heroOpenMenuBtn = document.getElementById('heroOpenMenu');

  const addReceptionEl = document.getElementById('card-add-reception');
  const viewPatientEl = document.getElementById('card-view-patient');
  const newPrescriptionEl = document.getElementById('card-new-prescription');
  const savedPrescriptionsEl = document.getElementById('card-saved-prescriptions');
  const addPatientEl = document.getElementById('card-add-patient');

  // new cards
  const allMedicineEl = document.getElementById('card-all-medicine');
  const doctorsListEl = document.getElementById('card-doctors-list');
  const bloodTestsEl = document.getElementById('card-blood-tests');
  const receptionListEl = document.getElementById('card-receptionalist-list');

  // make sure elements are focusable (if not anchors)
  [addReceptionEl, viewPatientEl, newPrescriptionEl, savedPrescriptionsEl, addPatientEl,
   allMedicineEl, doctorsListEl, bloodTestsEl, receptionListEl].forEach(el=>{
    if (!el) return;
    if (el.tagName.toLowerCase() !== 'a') {
      el.setAttribute('tabindex', '0');
      el.style.cursor = 'pointer';
    }
  });

  // hero quick actions
  if (heroAddPatientBtn && addPatientEl) {
    heroAddPatientBtn.addEventListener('click', (ev) => {
      ev.preventDefault();
      addPatientEl.click();
    });
  }
  if (heroOpenMenuBtn) {
    heroOpenMenuBtn.addEventListener('click', (ev) => {
      ev.preventDefault();
      const toggle = document.querySelector('[data-nav-toggle]');
      if (toggle) toggle.dispatchEvent(new Event('click', { bubbles: true }));
    });
  }

  // Add Receptionalist: special flow (server sets session/redirect)
  if (addReceptionEl) {
    addReceptionEl.addEventListener('click', (ev) => {
      ev.preventDefault();
      const href = addReceptionEl.getAttribute('href') || 'dashboard.php?role=receptionalist';
      addReceptionEl.style.opacity = '0.8';
      followRedirectAndNavigate(href);
    });
    addReceptionEl.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); addReceptionEl.click(); }
    });
  }

  function attachSimpleNav(el, loadingText) {
    if (!el) return;
    el.addEventListener('click', (ev) => {
      ev.preventDefault();
      const href = el.getAttribute('href');
      if (!href) return;
      showLoading(loadingText || 'Opening page…');
      setTimeout(()=>{ hideLoading(); safeNavigate(href); }, 180);
    });
    el.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); el.click(); }
    });
  }

  attachSimpleNav(viewPatientEl, 'Opening patient history…');
  attachSimpleNav(newPrescriptionEl, 'Opening Add New Prescription…');
  attachSimpleNav(savedPrescriptionsEl, 'Opening saved prescriptions…');
  attachSimpleNav(addPatientEl, 'Opening add new patient…');

  // attach nav for new cards
  attachSimpleNav(allMedicineEl, 'Opening medicine list…');
  attachSimpleNav(doctorsListEl, 'Opening doctors list…');
  attachSimpleNav(bloodTestsEl, 'Opening blood tests…');
  attachSimpleNav(receptionListEl, 'Opening receptionist list…');

  // Apply role-based hiding (uses localStorage.role set by signin flow)
  applyRoleVisibility();
  initCardFilter(filterInput, dashboardState.cardsEmpty);

  // Optional: watch for role changes in another tab/window and re-apply (best-effort)
  window.addEventListener('storage', (e) => {
    if (e.key === 'role') {
      // micro-delay so other tab finishes writing
      setTimeout(() => {
        applyRoleVisibility();
        if (filterInput) filterInput.dispatchEvent(new Event('input'));
      }, 100);
    }
  });
});
