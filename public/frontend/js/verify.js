/**
 * CEPFMS — Citizen identity verification form: photo previews, basic checks,
 * and the upload. The server checks everything again.
 */
(() => {
  const form = document.getElementById('verify-form');
  if (!form) return;

  const MAX_BYTES = 5 * 1024 * 1024;
  const TYPES = ['image/jpeg', 'image/png', 'image/webp'];
  const errorEl = document.getElementById('verify-error');
  const btn = document.getElementById('verify-btn');

  function showError(message) {
    errorEl.textContent = message || '';
    errorEl.classList.toggle('hidden', !message);
  }

  // Preview each chosen photo inside its drop area.
  form.querySelectorAll('[data-photo]').forEach((box) => {
    const input = box.querySelector('input[type="file"]');
    input.addEventListener('change', () => {
      box.querySelector('img')?.remove();
      box.classList.remove('has-image');
      const file = input.files[0];
      if (!file) return;
      if (!TYPES.includes(file.type)) {
        input.value = '';
        showError('Please choose a JPG, PNG, or WEBP photo.');
        return;
      }
      if (file.size > MAX_BYTES) {
        input.value = '';
        showError('Each photo must be smaller than 5MB.');
        return;
      }
      showError('');
      const img = document.createElement('img');
      img.alt = '';
      img.src = URL.createObjectURL(file);
      img.onload = () => URL.revokeObjectURL(img.src);
      box.prepend(img);
      box.classList.add('has-image');
    });
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const val = (name) => (form.elements[name]?.value || '').trim();

    let problem = '';
    if (!val('full_name')) problem = 'Enter your full name exactly as it appears on your ID.';
    else if (!val('id_type')) problem = 'Choose the type of ID you are using.';
    else if (val('id_number').replace(/[^A-Za-z0-9]/g, '').length < 4) problem = 'Enter the ID number shown on your ID.';
    else if (!val('barangay')) problem = 'Choose your barangay in San Jose del Monte.';
    else if (!form.elements.id_photo.files.length) problem = 'Add a photo of the front of your ID.';
    else if (!form.elements.selfie_photo.files.length) problem = 'Add a selfie of you holding your ID.';
    else if (!form.elements.consent.checked) problem = 'Please confirm the ID is yours and agree to it being processed.';
    if (problem) {
      showError(problem);
      return;
    }
    showError('');

    const label = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Sending…';

    const result = await CivicConnect.apiFetch(`${window.APP_URL || ''}/api/citizen/verification`, {
      method: 'POST',
      body: new FormData(form),
    });

    if (result.success) {
      window.location.reload();
      return;
    }
    btn.disabled = false;
    btn.textContent = label;
    showError(result.message || 'Something went wrong. Please try again.');
  });
})();
