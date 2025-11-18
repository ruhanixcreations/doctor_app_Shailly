(() => {
  'use strict';

  const scriptEl = document.currentScript;
  const headerRootAttr = scriptEl?.getAttribute('data-header-root') || './header';
  const appRootAttr = scriptEl?.getAttribute('data-app-root');
  const bodyRootAttr = document.body?.dataset?.appRoot;
  const appRoot = sanitizeRoot(bodyRootAttr || appRootAttr || '..');
  const headerRoot = trimSlash(headerRootAttr || './header');

  const NAV_ITEMS = [
    { id: 'dashboard', title: 'Dashboard', subtitle: 'Overview & quick actions', path: 'dashboard/dashboard.html', icon: 'grid' },
    { id: 'addReceptionist', title: 'Add Receptionist', subtitle: 'Create a new receptionist profile', path: 'dashboard/dashboard.php?role=receptionalist', icon: 'userPlus' },
    { id: 'patientHistory', title: 'Patient History', subtitle: 'Timeline, reports & visits', path: 'patient_history/patient_history.html', icon: 'history' },
    { id: 'addPatient', title: 'Add Patient', subtitle: 'Capture visit details & files', path: 'add_new_patient/add_new_patient.html', icon: 'camera' },
    { id: 'prescription', title: 'Add Prescription', subtitle: 'Write or upload prescriptions', path: 'add_prescription/add_prescription.html', icon: 'prescription' },
    { id: 'allMedicine', title: 'All Medicine', subtitle: 'Browse inventory & forms', path: 'all_medicine/all_medicine.html', icon: 'pill' },
    { id: 'doctors', title: 'Doctors List', subtitle: 'Manage doctor roster', path: 'doctors_list/doctors_list.html', icon: 'stethoscope' },
    { id: 'bloodTests', title: 'Blood Tests', subtitle: 'Set up diagnostics', path: 'blood_tests/blood_tests.html', icon: 'lab' },
    { id: 'receptionists', title: 'Receptionist List', subtitle: 'Team overview', path: 'receptionalist_list/receptionalist_list.html', icon: 'team' }
  ];

  const ICONS = {
    grid: `<svg viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>`,
    userPlus: `<svg viewBox="0 0 24 24"><path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="3"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg>`,
    history: `<svg viewBox="0 0 24 24"><path d="M3 3v6h6"/><path d="M3.51 9a9 9 0 1 1-.49 3"/><path d="M12 7v5l3 2"/></svg>`,
    camera: `<svg viewBox="0 0 24 24"><path d="M4 7h3l2-3h6l2 3h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2z"/><circle cx="12" cy="13" r="3"/></svg>`,
    prescription: `<svg viewBox="0 0 24 24"><path d="M6 4h9a2 2 0 0 1 2 2v12"/><path d="M6 4v16"/><path d="M6 8h9"/><path d="m9.5 13.5 6 6"/><path d="m15.5 13.5-6 6"/></svg>`,
    pill: `<svg viewBox="0 0 24 24"><path d="M9.5 9.5 3 16a4.5 4.5 0 0 0 6.36 6.36L15.86 16a4.5 4.5 0 1 0-6.36-6.36Z"/><path d="m14 14 1-1"/></svg>`,
    stethoscope: `<svg viewBox="0 0 24 24"><path d="M6 3v7a4 4 0 0 0 8 0V3"/><path d="M6 7h8"/><path d="M12 15v2a4 4 0 0 0 8 0v-2"/><circle cx="20" cy="10" r="2"/></svg>`,
    lab: `<svg viewBox="0 0 24 24"><path d="M6 3h12"/><path d="M10 3v8.5L4.5 21a1 1 0 0 0 .87 1.5h13.26a1 1 0 0 0 .87-1.5L14 11.5V3"/><path d="M6.5 17h11"/></svg>`,
    team: `<svg viewBox="0 0 24 24"><circle cx="7" cy="7" r="3"/><circle cx="17" cy="7" r="3"/><path d="M7 14a4 4 0 0 0-4 4v2"/><path d="M17 14a4 4 0 0 1 4 4v2"/><path d="M12 11a4 4 0 0 0-4 4v5"/></svg>`
  };

  initShell().catch(err => console.error('[header]', err));

  async function initShell() {
    if (document.querySelector('[data-shell-root]')) {
      document.body.classList.add('app-shell');
      hydrateHeader();
      return;
    }

    const templateHtml = await fetchTemplate('header.html');
    const mount = document.createElement('div');
    mount.innerHTML = templateHtml.trim();
    const fragment = document.createDocumentFragment();
    Array.from(mount.children).forEach(child => fragment.appendChild(child));
    document.body.prepend(fragment);
    document.body.classList.add('app-shell');
    hydrateHeader();
  }

  function hydrateHeader() {
    const drawer = document.querySelector('[data-nav-drawer]');
    const linksContainer = document.querySelector('[data-drawer-links]');
    const toggleBtn = document.querySelector('[data-nav-toggle]');
    const closeBtn = document.querySelector('[data-drawer-close]');
    const backdrop = document.querySelector('[data-drawer-backdrop]');
    const profileChip = document.querySelector('[data-profile-chip]');
    const authCtas = document.querySelector('[data-auth-ctas]');
    const signinLink = document.querySelector('[data-auth-signin]');
    const signupLink = document.querySelector('[data-auth-signup]');

    if (linksContainer) {
      linksContainer.innerHTML = '';
      NAV_ITEMS.forEach(item => {
        const link = document.createElement('a');
        link.className = 'drawer-card';
        link.href = buildAppPath(item.path);
        link.innerHTML = `
          <span class="drawer-icon">${ICONS[item.icon] || ICONS.grid}</span>
          <span class="drawer-copy">
            <strong>${item.title}</strong>
            <span>${item.subtitle}</span>
          </span>`;
        linksContainer.appendChild(link);
      });
    }

    const navHandler = (open) => {
      if (!drawer || !backdrop) return;
      const shouldOpen = typeof open === 'boolean' ? open : !drawer.classList.contains('open');
      drawer.classList.toggle('open', shouldOpen);
      drawer.setAttribute('aria-hidden', String(!shouldOpen));
      backdrop.classList.toggle('visible', shouldOpen);
      document.body.classList.toggle('drawer-open', shouldOpen);
    };

    toggleBtn?.addEventListener('click', () => navHandler(true));
    closeBtn?.addEventListener('click', () => navHandler(false));
    backdrop?.addEventListener('click', () => navHandler(false));
    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape') navHandler(false);
    });

    if (signinLink) signinLink.href = buildAppPath('signin/signin.html');
    if (signupLink) signupLink.href = buildAppPath('signup/signup.html');
    profileChip?.setAttribute('hidden', 'true');
    authCtas?.removeAttribute('hidden');

    hydrateAuthState();
  }

  async function hydrateAuthState() {
    const profileChip = document.querySelector('[data-profile-chip]');
    const profileInitial = document.querySelector('[data-profile-initial]');
    const authCtas = document.querySelector('[data-auth-ctas]');
    const userNote = document.querySelector('[data-user-note]');

    const fallback = () => {
      const storedName = safeLocalStorage('userName');
      const storedEmail = safeLocalStorage('signedInUserEmail');
      const userId = safeLocalStorage('user_id');
      if (userId) {
        applyAuthState({ loggedIn: true, userName: storedName, userEmail: storedEmail });
      } else {
        applyAuthState({ loggedIn: false });
      }
    };

    try {
      const data = await fetchSession();
      if (data) {
        applyAuthState(data);
      } else {
        fallback();
      }
    } catch (err) {
      console.warn('[header] session lookup failed', err);
      fallback();
    }

    function applyAuthState(state) {
      const isLogged = Boolean(state?.loggedIn);
      if (isLogged) {
        authCtas?.setAttribute('hidden', 'true');
        profileChip?.removeAttribute('hidden');
        const label = (state.userName || state.userEmail || 'User').trim();
        profileInitial.textContent = label.charAt(0).toUpperCase();
        profileChip?.setAttribute('title', label);
        if (userNote) {
          const firstName = label.split(' ')[0];
          userNote.textContent = `Hi, ${firstName}`;
        }
      } else {
        profileChip?.setAttribute('hidden', 'true');
        authCtas?.removeAttribute('hidden');
        if (userNote) userNote.textContent = 'You are browsing as guest';
      }
    }
  }

  function buildAppPath(target) {
    if (!target) return appRoot;
    const cleanBase = appRoot.replace(/\/+$/, '');
    const cleanTarget = target.replace(/^\/+/, '');
    const raw = cleanBase ? `${cleanBase}/${cleanTarget}` : cleanTarget;
    return encodeURI(raw);
  }

  async function fetchTemplate(fileName) {
    const res = await fetch(`${headerRoot}/${fileName}`, { cache: 'no-store' });
    if (!res.ok) throw new Error(`Failed to load header template (${res.status})`);
    return res.text();
  }

  async function fetchSession() {
    try {
      const res = await fetch(`${headerRoot}/header.php`, { credentials: 'include', cache: 'no-store' });
      if (!res.ok) return null;
      return res.json();
    } catch (err) {
      return null;
    }
  }

  function sanitizeRoot(value) {
    if (!value) return '';
    if (value === '.') return '.';
    return value.replace(/\/+$/, '');
  }

  function trimSlash(value) {
    if (!value) return '';
    return value.replace(/\/+$/, '');
  }

  function safeLocalStorage(key) {
    try {
      return window.localStorage?.getItem(key) || '';
    } catch (_) {
      return '';
    }
  }
})();
