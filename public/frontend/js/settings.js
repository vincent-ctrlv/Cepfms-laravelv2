/**
 * CEPFMS — Admin: System Settings (Branding)
 */
(() => {
  const form = document.getElementById('branding-form');
  if (!form) return;

  const textInputs = Array.from(form.querySelectorAll('input[data-default]'));
  const logoInput = document.getElementById('logo-input');
  const logoPreview = document.getElementById('logo-preview');
  const logoStatus = document.getElementById('logo-status');
  const logoResetBtn = document.getElementById('logo-reset-btn');
  const removeLogo = document.getElementById('remove-logo');
  const saveBtn = document.getElementById('branding-save-btn');
  const cancelBtn = document.getElementById('branding-cancel-btn');
  const dirtyEl = document.getElementById('branding-dirty');

  const savedText = Object.fromEntries(textInputs.map(i => [i.name, i.value]));
  const savedLogoSrc = logoPreview.src;
  const hadCustomLogo = !logoResetBtn.classList.contains('hidden');
  let previewUrl = null;

  const tidy = (v) => v.trim().replace(/\s+/g, ' ');

  function isDirty() {
    return textInputs.some(i => tidy(i.value) !== tidy(savedText[i.name]))
      || logoInput.files.length > 0
      || removeLogo.value === '1';
  }

  function refresh() {
    const dirty = isDirty();
    saveBtn.disabled = !dirty;
    cancelBtn.classList.toggle('hidden', !dirty);
    dirtyEl.classList.toggle('hidden', !dirty);
  }

  function setLogoStatus(text) {
    logoStatus.textContent = text;
    logoStatus.classList.toggle('hidden', !text);
  }

  textInputs.forEach(i => i.addEventListener('input', () => {
    i.classList.remove('border-red-400');
    refresh();
  }));

  // Show the chosen image right away; it's only saved with "Save changes".
  logoInput.addEventListener('change', () => {
    const file = logoInput.files[0];
    if (!file) return;
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
      CivicConnect.toast('Please choose a PNG, JPG, or WebP image.', 'warning');
      logoInput.value = '';
      return;
    }
    if (file.size > 1024 * 1024) {
      CivicConnect.toast('The logo must be 1 MB or smaller.', 'warning');
      logoInput.value = '';
      return;
    }
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    previewUrl = URL.createObjectURL(file);
    logoPreview.src = previewUrl;
    removeLogo.value = '0';
    logoResetBtn.classList.remove('hidden');
    setLogoStatus('New logo selected — save to apply.');
    refresh();
  });

  logoResetBtn.addEventListener('click', () => {
    logoInput.value = '';
    logoPreview.src = logoPreview.dataset.defaultSrc;
    // Only a saved custom logo needs removing; a just-picked file is simply dropped.
    removeLogo.value = hadCustomLogo ? '1' : '0';
    logoResetBtn.classList.add('hidden');
    setLogoStatus(hadCustomLogo ? 'The city seal will be used — save to apply.' : '');
    refresh();
  });

  cancelBtn.addEventListener('click', () => {
    textInputs.forEach(i => { i.value = savedText[i.name]; i.classList.remove('border-red-400'); });
    logoInput.value = '';
    removeLogo.value = '0';
    logoPreview.src = savedLogoSrc;
    logoResetBtn.classList.toggle('hidden', !hadCustomLogo);
    setLogoStatus('');
    refresh();
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const empty = textInputs.find(i => !tidy(i.value));
    if (empty) {
      empty.classList.add('border-red-400');
      empty.focus();
      CivicConnect.toast(`${form.querySelector(`label[for="${empty.id}"]`).textContent} is required.`, 'warning');
      return;
    }

    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving…';
    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/settings/branding`, {
      method: 'POST',
      body: new FormData(form),
    });

    if (!result.success) {
      saveBtn.textContent = 'Save changes';
      refresh();
      return; // apiFetch already showed the server's message
    }
    CivicConnect.toast('Branding saved.', 'success');
    // Reload so the sidebar, title and logo everywhere on the page pick up the change.
    saveBtn.textContent = 'Saved';
    setTimeout(() => window.location.reload(), 900);
  });

  window.addEventListener('beforeunload', (e) => {
    if (isDirty() && saveBtn.textContent === 'Save changes') { e.preventDefault(); e.returnValue = ''; }
  });
})();
