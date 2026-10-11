/**
 * CEPFMS — Admin: Moderation & Validation queue
 */
(() => {
  const tbody = document.getElementById('moderation-tbody');
  if (!tbody) return;

  const emptyEl = document.getElementById('moderation-empty');
  const paginationEl = document.getElementById('moderation-pagination');
  let currentPage = 1;
  const filters = ['m-type', 'm-status'].map(id => document.getElementById(id));
  filters.forEach(el => el?.addEventListener('change', () => { currentPage = 1; loadTable(); }));

  function statusClass(status) {
    const map = { Pending: 'badge-slate', Approved: 'badge-green', Rejected: 'badge-red', Flagged: 'badge-amber' };
    return map[status] || 'badge-slate';
  }

  async function loadTable() {
    CivicConnect.showTableSkeleton(tbody);
    emptyEl.classList.add('hidden');

    const params = new URLSearchParams({
      type: document.getElementById('m-type')?.value || '',
      status: document.getElementById('m-status')?.value || '',
      page: currentPage,
    });

    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/moderation/list?${params}`);
    tbody.innerHTML = '';

    if (!result.success || !result.data.rows.length) {
      emptyEl.classList.remove('hidden');
      if (paginationEl) paginationEl.innerHTML = '';
      return;
    }

    CivicConnect.renderPagination(paginationEl, {
      page: result.data.page, perPage: result.data.per_page, total: result.data.total,
    }, (newPage) => { currentPage = newPage; loadTable(); });

    result.data.rows.forEach(row => {
      const tr = document.createElement('tr');
      tr.className = 'hover:bg-pagebg/60 transition';
      // Flagged = escalated before Escalate was removed; still awaiting a decision.
      const isPending = row.status === 'Pending' || row.status === 'Flagged';

      tr.innerHTML = `
        <td class="px-4 py-3 font-mono text-xs text-slate">${row.reference_number ? CivicConnect.escapeHtml(row.reference_number) : '#' + row.id}</td>
        <td class="px-4 py-3 text-xs font-medium">${CivicConnect.escapeHtml(row.content_type)}</td>
        <td class="px-4 py-3 max-w-[200px]">${CivicConnect.citizenCell(row.citizen_name)}</td>
        <td class="px-4 py-3 max-w-[260px] truncate text-xs text-slate-700" title="${CivicConnect.escapeHtml(row.content_snapshot)}">${CivicConnect.escapeHtml(row.content_snapshot)}</td>
        <td class="px-4 py-3">${CivicConnect.dateCell(row.created_at)}</td>
        <td class="px-4 py-3">
          <span class="badge ${statusClass(row.status)}">${row.status}</span>
          ${row.status === 'Rejected' && row.reject_reason ? `<p class="text-[11px] text-slate mt-1 whitespace-nowrap" title="${CivicConnect.escapeHtml(row.reject_note || '')}">${CivicConnect.escapeHtml(row.reject_reason)}${row.reject_note ? ' · note' : ''}</p>` : ''}
        </td>
        <td class="px-4 py-3 text-right whitespace-nowrap">
          <button data-id="${row.id}" data-subid="${row.submission_id || ''}" class="mod-view-btn text-xs text-slate-500 hover:text-teal inline-flex items-center justify-center p-1.5 rounded hover:bg-slate-100 transition mr-2" title="View Full Submission">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
          ${isPending ? `
            <button data-id="${row.id}" data-action="approve" class="mod-btn text-xs font-semibold text-white bg-teal rounded px-2.5 py-1.5 hover:bg-teal/90 transition mr-2">Approve</button>
            <button data-id="${row.id}" data-action="reject" class="mod-btn text-xs text-red-600 hover:underline">Reject</button>
          ` : '<span class="text-xs text-slate">Reviewed</span>'}
        </td>
      `;
      tbody.appendChild(tr);
    });

    // Eye button click handlers (also fired by clicking anywhere on the row)
    CivicConnect.enableRowClick(tbody, '.mod-view-btn');
    tbody.querySelectorAll('.mod-view-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const modId = btn.dataset.id;
        const subId = btn.dataset.subid;
        const rowData = result.data.rows.find(r => String(r.id) === String(modId));
        openModerationModal(rowData, subId);
      });
    });

    // Action button handlers in table
    tbody.querySelectorAll('.mod-btn').forEach(btn => {
      btn.addEventListener('click', () => handleModerationAction(btn.dataset.id, btn.dataset.action));
    });
  }

  /**
   * Asks why the submission is being rejected. Resolves { reason, note },
   * or null if the admin cancels.
   */
  function rejectDialog() {
    const esc = CivicConnect.escapeHtml;
    const reasons = Object.entries(window.REJECT_REASONS || {});
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.className = 'fixed inset-0 z-[60] modal-backdrop flex items-center justify-center p-4';
      overlay.innerHTML = `
        <form class="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6" role="dialog" aria-modal="true" aria-labelledby="reject-title" novalidate>
          <h2 id="reject-title" class="text-base font-semibold text-navy">Reject submission</h2>
          <p class="text-sm text-slate mt-1">Choose why. The reason and note are shown to the citizen on the Track page.</p>
          <fieldset class="mt-4 space-y-2">
            <legend class="sr-only">Reason</legend>
            ${reasons.map(([key, hint]) => `
              <label class="flex items-start gap-3 rounded-lg border border-borderc px-3 py-2.5 cursor-pointer hover:bg-pagebg has-[:checked]:border-dangerred has-[:checked]:bg-red-50">
                <input type="radio" name="reject-reason" value="${esc(key)}" class="mt-1 accent-[#dc2626]">
                <span class="min-w-0">
                  <span class="block text-sm font-semibold text-navy">${esc(key)}</span>
                  <span class="block text-xs text-slate">${esc(hint)}</span>
                </span>
              </label>`).join('')}
          </fieldset>
          <label for="reject-note" class="block text-sm font-medium text-navy mt-4">Note <span class="text-slate font-normal">(optional)</span></label>
          <textarea id="reject-note" rows="2" maxlength="255" class="mt-1 w-full border border-borderc rounded px-3 py-2 text-sm" placeholder="e.g. The attached photos do not show the reported problem."></textarea>
          <p class="text-[11px] text-slate text-right"><span data-count>0</span>/255</p>
          <div class="flex justify-end gap-2 mt-4">
            <button type="button" data-cancel class="text-sm font-semibold py-2.5 px-5 rounded border border-borderc text-navy hover:bg-pagebg transition">Cancel</button>
            <button type="submit" class="text-sm font-semibold py-2.5 px-5 rounded text-white bg-dangerred hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed" disabled>Reject</button>
          </div>
        </form>`;

      const form = overlay.querySelector('form');
      const submitBtn = form.querySelector('[type="submit"]');
      const note = form.querySelector('#reject-note');
      const prevOverflow = document.body.style.overflow;
      function close(result) {
        document.removeEventListener('keydown', onKey, true);
        overlay.remove();
        document.body.style.overflow = prevOverflow;
        resolve(result);
      }
      function onKey(e) {
        if (e.key === 'Escape') { e.stopPropagation(); close(null); }
      }

      form.addEventListener('change', () => { submitBtn.disabled = !form.querySelector('[name="reject-reason"]:checked'); });
      note.addEventListener('input', () => { form.querySelector('[data-count]').textContent = note.value.length; });
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        const reason = form.querySelector('[name="reject-reason"]:checked')?.value;
        if (reason) close({ reason, note: note.value.trim() });
      });
      form.querySelector('[data-cancel]').addEventListener('click', () => close(null));
      overlay.addEventListener('click', (e) => { if (e.target === overlay) close(null); });
      document.addEventListener('keydown', onKey, true);
      document.body.appendChild(overlay);
      document.body.style.overflow = 'hidden';
      form.querySelector('[name="reject-reason"]')?.focus();
    });
  }

  async function handleModerationAction(id, action) {
    const payload = { id, action };
    if (action === 'reject') {
      const choice = await rejectDialog();
      if (!choice) return;
      Object.assign(payload, choice);
    }
    const r = await CivicConnect.apiFetch(`${window.APP_URL}/api/moderation/action`, {
      method: 'POST', body: JSON.stringify(payload),
    });
    if (r.success) {
      const messages = {
        Approved: 'Approved — now visible in its Feedback/Complaints/Proposals module and marked Received for the citizen.',
        Rejected: 'Rejected — this submission will stay hidden from staff and citizen-facing views.',
      };
      CivicConnect.toast(messages[r.data.status] || `Moderation item ${r.data.status.toLowerCase()}.`, 'success');
      CivicConnect.closeModal('moderation-modal');
      loadTable();
    }
  }

  async function openModerationModal(row, subId) {
    const body = document.getElementById('moderation-modal-body');
    if (!body) return;
    body.innerHTML = '<p class="text-sm text-slate py-12 text-center">Loading…</p>';
    CivicConnect.openModal('moderation-modal');

    let fullSub = null;
    if (subId) {
      const res = await CivicConnect.apiFetch(`${window.APP_URL}/api/submissions/get?id=${subId}`);
      if (res.success) fullSub = res.data;
    }

    // Queue items without a linked submission fall back to the queue row itself.
    const s = fullSub || {
      ...row,
      submission_type: (row.content_type || '').toLowerCase(),
      description: row.description || row.content_snapshot,
    };
    const isPending = row.status === 'Pending' || row.status === 'Flagged';

    let notice = '';
    if (row.status === 'Rejected') {
      const hint = (window.REJECT_REASONS || {})[row.reject_reason];
      notice += `
        <div class="rounded-lg border border-red-200 bg-red-50 px-3.5 py-3 text-xs text-red-900">
          <p class="font-semibold mb-1">Rejected: ${CivicConnect.escapeHtml(row.reject_reason || 'No reason recorded')}</p>
          ${hint ? `<p class="text-red-800">${CivicConnect.escapeHtml(hint)}</p>` : ''}
          ${row.reject_note ? `<p class="text-red-800 mt-1"><strong>Note:</strong> ${CivicConnect.escapeHtml(row.reject_note)}</p>` : ''}
        </div>`;
    }
    if (row.spam_score > 0 || row.spam_flags || row.flag === 'Potential Spam') {
      notice += `
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-3 text-xs text-amber-900">
          <div class="flex flex-wrap items-center gap-2 font-semibold mb-1">
            <span>⚠️ Spam &amp; risk check</span>
            <span class="badge badge-amber">Score ${CivicConnect.escapeHtml(String(row.spam_score ?? 0))}/100</span>
          </div>
          <p class="text-amber-800"><strong>Flags:</strong> ${CivicConnect.escapeHtml(row.spam_flags || row.flag || 'None')}</p>
        </div>`;
    }

    const actions = isPending
      ? `<div class="flex flex-wrap items-center justify-between gap-2">
           <p class="text-xs text-slate">Fix the classification above first if the AI got it wrong.</p>
           <div class="flex flex-wrap gap-2">
             <button id="modal-reject-btn" type="button" class="text-sm font-semibold text-red-600 border border-red-200 rounded-lg px-4 py-2 hover:bg-red-50 transition">Reject</button>
             <button id="modal-approve-btn" type="button" class="bg-teal text-white text-sm font-semibold px-5 py-2 rounded-lg hover:bg-teal/90 transition">Approve</button>
           </div>
         </div>`
      : `<div class="flex items-center justify-between gap-2">
           <p class="text-xs text-slate">Already reviewed — ${CivicConnect.escapeHtml(row.status.toLowerCase())}.</p>
           <button type="button" data-close-modal="moderation-modal" class="text-sm text-navy border border-borderc rounded-lg px-4 py-2 hover:bg-pagebg">Close</button>
         </div>`;

    body.innerHTML = CivicConnect.detailView({
      modalId: 'moderation-modal',
      s,
      badges: `<span class="badge ${statusClass(row.status)}">${CivicConnect.escapeHtml(row.status)}</span>
               ${s.priority ? `<span class="badge badge-slate">${CivicConnect.escapeHtml(s.priority)} priority</span>` : ''}`,
      fields: [
        { label: 'Category', html: CivicConnect.escapeHtml(s.category || '—') },
      ],
      notice,
      extra: fullSub ? CivicConnect.classificationEditor(fullSub) : '',
      actions,
    });

    // Reloading the table also refreshes the content type (Feedback/Complaint/Proposal) shown for this row.
    if (fullSub) CivicConnect.bindClassificationEditor(fullSub.id, () => { CivicConnect.closeModal('moderation-modal'); loadTable(); });
    document.getElementById('modal-approve-btn')?.addEventListener('click', () => handleModerationAction(row.id, 'approve'));
    document.getElementById('modal-reject-btn')?.addEventListener('click', () => handleModerationAction(row.id, 'reject'));
  }

  loadTable();
})();
