/**
 * CEPFMS — Admin: citizens' ID verification queue (approve / reject).
 */
(() => {
  const tbody = document.getElementById('verify-tbody');
  if (!tbody) return;

  const esc = (s) => CivicConnect.escapeHtml(String(s ?? ''));
  const emptyEl = document.getElementById('verify-empty');
  const paginationEl = document.getElementById('verify-pagination');
  const statusEl = document.getElementById('v-status');
  let currentPage = 1;
  statusEl.addEventListener('change', () => { currentPage = 1; loadTable(); });

  function statusClass(status) {
    return { Pending: 'badge-amber', Approved: 'badge-green', Rejected: 'badge-red' }[status] || 'badge-slate';
  }

  async function loadTable() {
    CivicConnect.showTableSkeleton(tbody);
    emptyEl.classList.add('hidden');

    const params = new URLSearchParams({ status: statusEl.value, page: currentPage });
    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/verifications/list?${params}`);
    tbody.innerHTML = '';

    if (!result.success || !result.data.rows.length) {
      emptyEl.classList.remove('hidden');
      paginationEl.innerHTML = '';
      return;
    }

    CivicConnect.renderPagination(paginationEl, {
      page: result.data.page, perPage: result.data.per_page, total: result.data.total,
    }, (newPage) => { currentPage = newPage; loadTable(); });

    result.data.rows.forEach((row) => {
      const tr = document.createElement('tr');
      tr.className = 'hover:bg-pagebg/60 transition';
      tr.innerHTML = `
        <td class="px-4 py-3 font-medium text-navy">${esc(row.full_name)}
          ${row.duplicate_accounts > 0 ? '<span class="badge badge-red ml-1" title="The same ID number is used on another account">ID reused</span>' : ''}</td>
        <td class="px-4 py-3 text-xs text-slate">${esc(row.citizen_email)}</td>
        <td class="px-4 py-3 text-xs">${esc(row.id_type)}<span class="block font-mono text-slate">•••• ${esc(row.id_number_last4)}</span></td>
        <td class="px-4 py-3 text-xs">${esc(row.barangay)}</td>
        <td class="px-4 py-3">${CivicConnect.dateCell(row.created_at)}</td>
        <td class="px-4 py-3"><span class="badge ${statusClass(row.status)}">${esc(row.status)}</span></td>
        <td class="px-4 py-3 text-right whitespace-nowrap">
          <button type="button" data-id="${Number(row.id)}" class="v-view-btn text-xs font-semibold text-teal border border-teal/30 rounded-md px-2.5 py-1 hover:bg-teal/5">${row.status === 'Pending' ? 'Review' : 'View'}</button>
        </td>`;
      tbody.appendChild(tr);
    });

    CivicConnect.enableRowClick(tbody, '.v-view-btn');
    tbody.querySelectorAll('.v-view-btn').forEach((btn) => {
      btn.addEventListener('click', () => openModal(result.data.rows.find((r) => String(r.id) === btn.dataset.id)));
    });
  }

  function openModal(row) {
    const body = document.getElementById('verify-modal-body');
    const isPending = row.status === 'Pending';
    const photo = (url, label) => `
      <figure class="min-w-0">
        <a href="${esc(url)}" target="_blank" rel="noopener" title="Open full size">
          <img src="${esc(url)}" alt="${esc(label)}" class="w-full h-64 object-contain bg-pagebg rounded border border-borderc">
        </a>
        <figcaption class="text-xs text-slate mt-1">${esc(label)} · click to open full size</figcaption>
      </figure>`;

    body.innerHTML = `
      <div class="p-6">
        <div class="flex items-start justify-between gap-4 mb-4">
          <div>
            <h2 class="text-lg font-semibold text-navy">${esc(row.full_name)}</h2>
            <p class="text-xs text-slate">${esc(row.citizen_email)} · sent ${esc(new Date(row.created_at.replace(' ', 'T')).toLocaleString())}</p>
          </div>
          <span class="badge ${statusClass(row.status)}">${esc(row.status)}</span>
        </div>
        ${row.duplicate_accounts > 0 ? `
          <div class="rounded-lg border border-red-200 bg-red-50 px-3.5 py-3 text-xs text-red-900 mb-4">
            <strong>ID number already used:</strong> ${Number(row.duplicate_accounts)} other account(s) sent the same ID number. Check carefully before approving.
          </div>` : ''}
        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm mb-4">
          <div><dt class="text-xs text-slate">ID type</dt><dd class="font-medium text-navy">${esc(row.id_type)}</dd></div>
          <div><dt class="text-xs text-slate">ID number</dt><dd class="font-mono text-navy">•••• ${esc(row.id_number_last4)}</dd></div>
          <div><dt class="text-xs text-slate">Barangay</dt><dd class="font-medium text-navy">${esc(row.barangay)}</dd></div>
          <div><dt class="text-xs text-slate">Name on Google</dt><dd class="font-medium text-navy">${esc(row.account_name)}</dd></div>
        </dl>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
          ${photo(row.id_photo_url, 'Front of ID')}
          ${photo(row.selfie_photo_url, 'Selfie holding the ID')}
        </div>
        ${row.status === 'Rejected' ? `<p class="text-xs text-red-800 mb-4"><strong>Rejected:</strong> ${esc(row.reject_reason)}${row.reviewer_name ? ` · by ${esc(row.reviewer_name)}` : ''}</p>` : ''}
        ${row.status === 'Approved' ? `<p class="text-xs text-emerald mb-4"><strong>Approved</strong>${row.reviewer_name ? ` by ${esc(row.reviewer_name)}` : ''}</p>` : ''}
        <div class="flex flex-wrap items-center justify-end gap-2">
          <button type="button" data-close-modal="verify-modal" class="text-sm text-navy border border-borderc rounded-lg px-4 py-2 hover:bg-pagebg">Close</button>
          ${isPending ? `
            <button type="button" id="v-reject-btn" class="text-sm font-semibold text-red-600 border border-red-200 rounded-lg px-4 py-2 hover:bg-red-50 transition">Reject</button>
            <button type="button" id="v-approve-btn" class="bg-teal text-white text-sm font-semibold px-5 py-2 rounded-lg hover:bg-teal/90 transition">Approve</button>` : ''}
        </div>
      </div>`;

    CivicConnect.openModal('verify-modal');
    body.querySelector('[data-close-modal]').addEventListener('click', () => CivicConnect.closeModal('verify-modal'));
    document.getElementById('v-approve-btn')?.addEventListener('click', () => act(row.id, 'approve'));
    document.getElementById('v-reject-btn')?.addEventListener('click', () => act(row.id, 'reject'));
  }

  /** Asks why the ID is rejected. Resolves { reason, note }, or null if cancelled. */
  function rejectDialog() {
    const reasons = Object.entries(window.VERIFY_REJECT_REASONS || {});
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.className = 'fixed inset-0 z-[60] modal-backdrop flex items-center justify-center p-4';
      overlay.innerHTML = `
        <form class="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6" role="dialog" aria-modal="true" aria-labelledby="v-reject-title" novalidate>
          <h2 id="v-reject-title" class="text-base font-semibold text-navy">Reject ID</h2>
          <p class="text-sm text-slate mt-1">The reason and note are emailed to the citizen so they can send a better photo.</p>
          <fieldset class="mt-4 space-y-2">
            <legend class="sr-only">Reason</legend>
            ${reasons.map(([key, hint]) => `
              <label class="flex items-start gap-3 rounded-lg border border-borderc px-3 py-2.5 cursor-pointer hover:bg-pagebg">
                <input type="radio" name="v-reason" value="${esc(key)}" class="mt-1">
                <span class="min-w-0"><span class="block text-sm font-semibold text-navy">${esc(key)}</span><span class="block text-xs text-slate">${esc(hint)}</span></span>
              </label>`).join('')}
          </fieldset>
          <label for="v-note" class="block text-sm font-medium text-navy mt-4">Note <span class="text-slate font-normal">(required for "Other")</span></label>
          <textarea id="v-note" rows="2" maxlength="200" class="mt-1 w-full border border-borderc rounded px-3 py-2 text-sm"></textarea>
          <div class="flex justify-end gap-2 mt-4">
            <button type="button" data-cancel class="text-sm font-semibold py-2.5 px-5 rounded border border-borderc text-navy hover:bg-pagebg transition">Cancel</button>
            <button type="submit" class="text-sm font-semibold py-2.5 px-5 rounded text-white bg-dangerred hover:opacity-90 transition disabled:opacity-50" disabled>Reject</button>
          </div>
        </form>`;
      const form = overlay.querySelector('form');
      const submitBtn = form.querySelector('[type="submit"]');
      const note = form.querySelector('#v-note');
      const update = () => {
        const reason = form.querySelector('[name="v-reason"]:checked')?.value;
        submitBtn.disabled = !reason || (reason === 'Other' && !note.value.trim());
      };
      const close = (value) => { overlay.remove(); resolve(value); };
      form.addEventListener('change', update);
      note.addEventListener('input', update);
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        const reason = form.querySelector('[name="v-reason"]:checked')?.value;
        if (reason) close({ reason, note: note.value.trim() });
      });
      form.querySelector('[data-cancel]').addEventListener('click', () => close(null));
      overlay.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(null); });
      document.body.appendChild(overlay);
      form.querySelector('[name="v-reason"]')?.focus();
    });
  }

  async function act(id, action) {
    const payload = { id, action };
    if (action === 'reject') {
      const choice = await rejectDialog();
      if (!choice) return;
      Object.assign(payload, choice);
    }
    const r = await CivicConnect.apiFetch(`${window.APP_URL}/api/verifications/action`, {
      method: 'POST', body: JSON.stringify(payload),
    });
    if (r.success) {
      CivicConnect.toast(r.data.status === 'Approved'
        ? 'Approved — the citizen can now submit feedback and has been emailed.'
        : 'Rejected — the citizen has been emailed the reason.', 'success');
      CivicConnect.closeModal('verify-modal');
      loadTable();
    }
  }

  loadTable();
})();
