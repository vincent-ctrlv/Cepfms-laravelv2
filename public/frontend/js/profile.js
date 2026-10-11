/**
 * CEPFMS — Admin: Profile Settings (own name and position)
 */
(() => {
  const form = document.getElementById('profile-form');
  if (!form) return;

  const nameInput = document.getElementById('profile-name');
  const titleInput = document.getElementById('profile-title');
  const nameError = document.getElementById('profile-name-error');
  const saveBtn = document.getElementById('profile-save-btn');
  const cancelBtn = document.getElementById('profile-cancel-btn');
  const dirtyEl = document.getElementById('profile-dirty');
  const roleLabel = window.PROFILE_ROLE_LABEL || 'Staff';
  let saved = { name: nameInput.value, title: titleInput.value };

  // Same rule as the sidebar partial: first letter of the first and last word of the name.
  function initials(name) {
    const parts = name.trim().split(/\s+/);
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  }

  const tidy = (v) => v.trim().replace(/\s+/g, ' ');

  // Header card (and, after saving, the sidebar) show the name and title as typed.
  function renderIdentity(prefix, name, title) {
    const set = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
    set(`${prefix}-name`, name || ' ');
    set(`${prefix}-sub`, title || roleLabel);
  }

  function refresh() {
    const name = tidy(nameInput.value);
    const title = tidy(titleInput.value);
    const dirty = name !== tidy(saved.name) || title !== tidy(saved.title);
    saveBtn.disabled = !dirty;
    cancelBtn.classList.toggle('hidden', !dirty);
    dirtyEl.classList.toggle('hidden', !dirty);
    renderIdentity('profile-preview', name, title);
    document.getElementById('profile-avatar').textContent = name ? initials(name) : 'ST';
    if (name) {
      nameError.classList.add('hidden');
      nameInput.classList.remove('border-red-400');
    }
  }

  nameInput.addEventListener('input', refresh);
  titleInput.addEventListener('input', refresh);

  cancelBtn.addEventListener('click', () => {
    nameInput.value = saved.name;
    titleInput.value = saved.title;
    refresh();
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!tidy(nameInput.value)) {
      nameError.classList.remove('hidden');
      nameInput.classList.add('border-red-400');
      nameInput.focus();
      return;
    }

    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving…';
    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/profile/update`, {
      method: 'POST',
      body: JSON.stringify({ full_name: nameInput.value, title: titleInput.value }),
    });
    saveBtn.textContent = 'Save changes';

    if (!result.success) { refresh(); return; } // apiFetch already showed the server's message

    const d = result.data;
    nameInput.value = d.full_name;
    titleInput.value = d.title;
    saved = { name: d.full_name, title: d.title };
    refresh();
    renderIdentity('sidebar-user', d.full_name, d.title);
    const sidebarInitials = document.getElementById('sidebar-user-initials');
    if (sidebarInitials) sidebarInitials.textContent = initials(d.full_name);
    CivicConnect.toast('Profile saved.', 'success');
  });

  // Don't lose edits by navigating away.
  window.addEventListener('beforeunload', (e) => {
    if (!saveBtn.disabled && saveBtn.textContent !== 'Saving…') { e.preventDefault(); e.returnValue = ''; }
  });
})();

/* Two-factor sign-in switch: turning on is one click; turning off asks for the password. */
(() => {
  const toggle = document.getElementById('tfa-switch');
  const confirmForm = document.getElementById('tfa-confirm');
  if (!toggle || toggle.disabled || !confirmForm) return;

  const badge = document.getElementById('tfa-badge');
  const warning = document.getElementById('tfa-off-warning');
  const pwInput = document.getElementById('tfa-password');
  const confirmBtn = document.getElementById('tfa-confirm-btn');
  let on = document.getElementById('tfa-card').dataset.on === '1';

  function render() {
    toggle.setAttribute('aria-checked', String(on));
    badge.textContent = on ? 'On' : 'Off';
    badge.className = `badge ${on ? 'badge-green' : 'badge-amber'}`;
    warning?.classList.toggle('hidden', on);
  }

  function closeConfirm() {
    confirmForm.classList.add('hidden');
    pwInput.value = '';
    pwInput.classList.remove('border-red-400');
  }

  async function save(enabled, password) {
    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/profile/two-factor`, {
      method: 'POST',
      body: JSON.stringify(password === undefined ? { enabled } : { enabled, password }),
    });
    if (!result.success) return false; // apiFetch already showed the server's message
    on = result.data.enabled;
    render();
    CivicConnect.toast(on
      ? 'Two-factor sign-in is on. You\'ll get a code by email each time you sign in.'
      : 'Two-factor sign-in is off. Your password alone now signs you in.', on ? 'success' : 'warning');
    return true;
  }

  toggle.addEventListener('click', async () => {
    if (on) {
      // Off needs the password; the switch stays on until it's confirmed.
      confirmForm.classList.toggle('hidden');
      if (!confirmForm.classList.contains('hidden')) pwInput.focus();
      return;
    }
    toggle.disabled = true;
    await save(true);
    toggle.disabled = false;
  });

  document.getElementById('tfa-cancel-btn').addEventListener('click', closeConfirm);

  confirmForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!pwInput.value) {
      pwInput.classList.add('border-red-400');
      pwInput.focus();
      return;
    }
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Turning off…';
    const ok = await save(false, pwInput.value);
    confirmBtn.disabled = false;
    confirmBtn.textContent = 'Turn off';
    if (ok) {
      closeConfirm();
    } else {
      pwInput.select();
    }
  });
})();
