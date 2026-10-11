/**
 * CEPFMS — Admin: Proposals & Suggestions table + detail modal
 *
 * Hick's Law applied:
 *  - Filter bar limited to search + status only (the two most useful axes).
 *  - Detail modal organised into clear labelled sections so the admin can
 *    scan context before acting: info grid -> description -> AI summary ->
 *    attachments -> activity history -> grouped action controls.
 *  - Status options pre-select the current value to minimise re-reads.
 */
(() => {
  const tbody = document.getElementById('proposals-tbody');
  if (!tbody) return;

  const emptyEl      = document.getElementById('proposals-empty');
  const paginationEl = document.getElementById('proposals-pagination');
  let currentPage    = 1;
  let debounceTimer;

  ['p-search', 'p-status'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        currentPage = 1;
        loadTable();
      }, 250);
    });
  });

  /* Badge helpers */
  function statusClass(s) {
    const map = { 'Under Review': 'badge-amber', Approved: 'badge-green', Rejected: 'badge-red', Implemented: 'badge-teal' };
    return map[s] || 'badge-slate';
  }

  /* Table */
  async function loadTable() {
    CivicConnect.showTableSkeleton(tbody);
    emptyEl.classList.add('hidden');

    const params = new URLSearchParams({
      search: document.getElementById('p-search')?.value || '',
      status: document.getElementById('p-status')?.value || '',
      page:   currentPage,
    });

    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/proposals/list?${params}`);
    tbody.innerHTML = '';

    if (!result.success || !result.data.rows.length) {
      emptyEl.classList.remove('hidden');
      if (paginationEl) paginationEl.innerHTML = '';
      return;
    }

    CivicConnect.renderPagination(paginationEl, {
      page: result.data.page,
      perPage: result.data.per_page,
      total: result.data.total,
    }, (newPage) => {
      currentPage = newPage;
      loadTable();
    });

    result.data.rows.forEach(row => {
      const tr = document.createElement('tr');
      tr.className = 'hover:bg-pagebg/60 transition';
      tr.innerHTML = `
        <td class="px-4 py-3 font-mono text-xs text-slate">${CivicConnect.escapeHtml(row.reference_number)}</td>
        <td class="px-4 py-3 max-w-[200px]">${CivicConnect.citizenCell(row.citizen_name)}</td>
        <td class="px-4 py-3 max-w-[260px] truncate font-medium text-navy" title="${CivicConnect.escapeHtml(row.subject)}">${CivicConnect.escapeHtml(row.subject)}</td>
        <td class="px-4 py-3 text-xs text-teal font-medium">${CivicConnect.escapeHtml(row.category || '—')}</td>
        <td class="px-4 py-3">${CivicConnect.sentimentBadge(row.sentiment)}</td>
        <td class="px-4 py-3"><span class="badge ${statusClass(row.proposal_status)}">${CivicConnect.escapeHtml(row.proposal_status)}</span></td>
        <td class="px-4 py-3">${CivicConnect.dateCell(row.created_at)}</td>
        <td class="px-4 py-3 text-right"><button type="button" data-id="${row.id}" class="view-btn inline-flex items-center text-xs font-semibold text-teal border border-teal/30 rounded-md px-2.5 py-1 hover:bg-teal/5">View</button></td>
      `;
      tbody.appendChild(tr);
    });

    CivicConnect.enableRowClick(tbody, '.view-btn');
    tbody.querySelectorAll('.view-btn').forEach(btn =>
      btn.addEventListener('click', () => openDetail(btn.dataset.id))
    );
  }

  /* Detail modal — Hick's Law: structured sections, grouped controls */
  async function openDetail(id) {
    const body = document.getElementById('proposal-modal-body');
    body.innerHTML = '<p class="text-sm text-slate py-12 text-center">Loading…</p>';
    CivicConnect.openModal('proposal-modal');

    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/submissions/get?id=${id}`);
    if (!result.success) { CivicConnect.closeModal('proposal-modal'); return; }
    const s = result.data;
    const current = s.proposal_status || 'Under Review';

    const statusOptions = ['Under Review', 'Approved', 'Rejected', 'Implemented']
      .map(st => `<option value="${st}" ${st === current ? 'selected' : ''}>${st}</option>`)
      .join('');

    body.innerHTML = CivicConnect.detailView({
      modalId: 'proposal-modal',
      s,
      badges: `<span class="badge ${statusClass(current)}">${CivicConnect.escapeHtml(current)}</span>`,
      fields: [
        { label: 'Category', html: CivicConnect.escapeHtml(s.category || '—') },
      ],
      actions: `
        <label for="pr-status-select" class="block text-xs font-medium text-slate mb-1.5">Proposal status</label>
        <div class="flex gap-2">
          <select id="pr-status-select" class="flex-1 min-w-0 border border-borderc rounded-lg px-2.5 py-2 text-sm">${statusOptions}</select>
          <button id="pr-status-btn" type="button" class="bg-teal text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-teal/90 transition">Update status</button>
        </div>`,
    });

    document.getElementById('pr-status-btn')?.addEventListener('click', async () => {
      const status = document.getElementById('pr-status-select').value;
      const r = await CivicConnect.apiFetch(`${window.APP_URL}/api/proposals/update`, {
        method: 'POST', body: JSON.stringify({ id, action: 'change_status', status }),
      });
      if (r.success) { CivicConnect.toast('Proposal status updated successfully.', 'success'); CivicConnect.closeModal('proposal-modal'); loadTable(); }
    });
  }

  loadTable();
})();
