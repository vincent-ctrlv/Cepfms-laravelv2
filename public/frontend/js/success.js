/**
 * CEPFMS — Submission success page: copy reference number to clipboard.
 */
(() => {
  const btn = document.getElementById('copy-ref-btn');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    const ref = btn.dataset.ref;
    try {
      await navigator.clipboard.writeText(ref);
      CivicConnect.toast('Reference number copied.', 'success', 2500);
    } catch (e) {
      CivicConnect.toast('Could not copy automatically — please copy it manually.', 'warning');
    }
  });
})();
