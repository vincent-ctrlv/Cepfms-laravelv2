/**
 * CEPFMS — Submission tracker: reference input UX (auto-uppercase, clear
 * button), copy-to-clipboard for a found submission, and remembering recent
 * reference-number lookups on this device (localStorage) so repeat visits
 * are one tap away.
 */
(() => {
  // Reference input: auto-uppercase as typed + clear button.
  const input = document.getElementById('ref-input');
  const clearBtn = document.getElementById('ref-clear-btn');
  if (input && clearBtn) {
    const syncClearBtn = () => clearBtn.classList.toggle('hidden', input.value.length === 0);
    input.addEventListener('input', () => {
      const pos = input.selectionStart;
      input.value = input.value.toUpperCase();
      input.setSelectionRange(pos, pos);
      syncClearBtn();
    });
    clearBtn.addEventListener('click', () => {
      input.value = '';
      syncClearBtn();
      input.focus();
    });
    syncClearBtn();
  }

  // Copy reference number to clipboard, when a submission was found.
  const copyBtn = document.getElementById('copy-ref-btn');
  if (copyBtn) {
    copyBtn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(copyBtn.dataset.ref);
        CivicConnect.toast('Reference number copied.', 'success', 2500);
      } catch (e) {
        CivicConnect.toast('Could not copy automatically — please copy it manually.', 'warning');
      }
    });
  }

  const KEY = 'civicconnect_recent_refs_v1';
  const MAX_RECENTS = 5;
  const wrap = document.getElementById('recent-refs-wrap');
  const list = document.getElementById('recent-refs-list');
  if (!wrap || !list) return;

  function getRecents() {
    try {
      const arr = JSON.parse(localStorage.getItem(KEY) || '[]');
      return Array.isArray(arr) ? arr : [];
    } catch (e) {
      return [];
    }
  }

  function saveRecents(arr) {
    try { localStorage.setItem(KEY, JSON.stringify(arr.slice(0, MAX_RECENTS))); } catch (e) { /* ignore */ }
  }

  // If this page load successfully found a submission, remember its reference.
  if (window.CURRENT_REF) {
    const updated = [window.CURRENT_REF, ...getRecents().filter(r => r !== window.CURRENT_REF)];
    saveRecents(updated);
  }

  const recents = getRecents();
  if (!recents.length) return;

  wrap.classList.remove('hidden');
  list.innerHTML = recents.map(ref => `
    <button type="button" data-ref="${CivicConnect.escapeHtml(ref)}"
      class="recent-ref-btn tr-chip">
      ${CivicConnect.escapeHtml(ref)}
    </button>
  `).join('');

  list.querySelectorAll('.recent-ref-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      window.location.href = `${window.APP_URL}/citizen/track?ref=${encodeURIComponent(btn.dataset.ref)}`;
    });
  });
})();
