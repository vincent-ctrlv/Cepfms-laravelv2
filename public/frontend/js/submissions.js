/**
 * CEPFMS — Admin: Feedback Submission table + detail modal
 */
(() => {
  const tbody = document.getElementById('submissions-tbody');
  if (!tbody) return;

  const emptyEl = document.getElementById('submissions-empty');
  const paginationEl = document.getElementById('submissions-pagination');
  const filters = ['f-search', 'f-type', 'f-category', 'f-status', 'f-priority', 'f-sentiment', 'f-date-from', 'f-date-to'].map(id => document.getElementById(id));

  // Pre-apply filters passed in the URL (e.g. from an Engagement Analytics card).
  const urlParams = new URLSearchParams(window.location.search);
  [['f-type', 'type'], ['f-category', 'category'], ['f-status', 'status'], ['f-priority', 'priority'],
   ['f-sentiment', 'sentiment'], ['f-date-from', 'date_from'], ['f-date-to', 'date_to']].forEach(([id, key]) => {
    const el = document.getElementById(id);
    const v = urlParams.get(key);
    if (!el || !v) return;
    el.value = v;
    if (el.tagName === 'SELECT' && el.value !== v) el.value = ''; // ignore values the dropdown doesn't offer
  });

  let currentPage = 1;

  let debounceTimer;
  filters.forEach(el => el?.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => { currentPage = 1; loadTable(); }, 250);
  }));

  function filterParams() {
    const params = new URLSearchParams({
      search: document.getElementById('f-search')?.value || '',
      type: document.getElementById('f-type')?.value || window.SUBMISSIONS_TYPE_FILTER || '',
      category: document.getElementById('f-category')?.value || '',
      status: document.getElementById('f-status')?.value || '',
      priority: document.getElementById('f-priority')?.value || '',
      sentiment: document.getElementById('f-sentiment')?.value || '',
      date_from: document.getElementById('f-date-from')?.value || '',
      date_to: document.getElementById('f-date-to')?.value || '',
    });
    return params;
  }

  document.getElementById('clear-filters-btn')?.addEventListener('click', () => {
    filters.forEach(el => { if (el) el.value = ''; });
    currentPage = 1;
    loadTable();
  });

  document.getElementById('export-csv-btn')?.addEventListener('click', () => {
    const params = filterParams();
    params.set('format', 'csv');
    window.location.href = `${window.APP_URL}/api/submissions/list?${params}`;
  });

  async function loadTable() {
    CivicConnect.showTableSkeleton(tbody);
    emptyEl.classList.add('hidden');

    const params = filterParams();
    params.set('page', currentPage);

    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/submissions/list?${params}`);
    tbody.innerHTML = '';

    if (!result.success || !result.data.rows.length) {
      emptyEl.classList.remove('hidden');
      paginationEl.innerHTML = '';
      return;
    }

    CivicConnect.renderPagination(paginationEl, {
      page: result.data.page, perPage: result.data.per_page, total: result.data.total,
    }, (newPage) => { currentPage = newPage; loadTable(); });

    result.data.rows.forEach(row => {
      const tr = document.createElement('tr');
      tr.className = 'hover:bg-pagebg/60 transition';
      tr.innerHTML = `
        <td class="px-4 py-3 font-mono text-xs text-slate">${CivicConnect.escapeHtml(row.reference_number)}</td>
        <td class="px-4 py-3 max-w-[200px]">${CivicConnect.citizenCell(row.citizen_name)}</td>
        <td class="px-4 py-3 max-w-[260px] truncate font-medium text-navy" title="${CivicConnect.escapeHtml(row.subject)}">${CivicConnect.escapeHtml(row.subject)}</td>
        <td class="px-4 py-3 text-xs">${CivicConnect.escapeHtml(row.category || 'Other')}</td>
        <td class="px-4 py-3"><span class="badge ${badgeClass(row.status)}">${CivicConnect.escapeHtml(row.status)}</span></td>
        <td class="px-4 py-3"><span class="badge ${priorityClass(row.priority)}">${CivicConnect.escapeHtml(row.priority)}</span></td>
        <td class="px-4 py-3">${CivicConnect.sentimentBadge(row.sentiment)}</td>
        <td class="px-4 py-3">${CivicConnect.dateCell(row.created_at)}</td>
        <td class="px-4 py-3 text-right">
          <button type="button" data-id="${row.id}" class="view-btn inline-flex items-center text-xs font-semibold text-teal border border-teal/30 rounded-md px-2.5 py-1 hover:bg-teal/5">View</button>
        </td>
      `;
      tbody.appendChild(tr);
    });

    CivicConnect.enableRowClick(tbody, '.view-btn');
    tbody.querySelectorAll('.view-btn').forEach(btn => {
      btn.addEventListener('click', () => openDetail(btn.dataset.id));
    });
  }

  function badgeClass(status) {
    const map = { Pending: 'badge-slate', Received: 'badge-blue', 'In Review': 'badge-amber', Assigned: 'badge-teal', Escalated: 'badge-red', Resolved: 'badge-green' };
    return map[status] || 'badge-slate';
  }
  function priorityClass(p) {
    const map = { Critical: 'badge-red', High: 'badge-amber', Medium: 'badge-blue', Low: 'badge-slate' };
    return map[p] || 'badge-slate';
  }
  async function openDetail(id) {
    const body = document.getElementById('submission-modal-body');
    body.innerHTML = '<p class="text-sm text-slate py-12 text-center">Loading…</p>';
    CivicConnect.openModal('submission-modal');

    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/submissions/get?id=${id}`);
    if (!result.success) { CivicConnect.closeModal('submission-modal'); return; }
    const s = result.data;

    // Escalated is no longer offered; older records keep it only as their current value.
    const statusOptions = ['Pending','Received','In Review','Assigned','Resolved']
      .concat(s.status === 'Escalated' ? ['Escalated'] : [])
      .map(st => `<option value="${st}" ${st === s.status ? 'selected' : ''}>${st}</option>`).join('');

    body.innerHTML = CivicConnect.detailView({
      modalId: 'submission-modal',
      s,
      badges: `<span class="badge ${badgeClass(s.status)}">${CivicConnect.escapeHtml(s.status)}</span>
               <span class="badge ${priorityClass(s.priority)}">${CivicConnect.escapeHtml(s.priority || '—')} priority</span>`,
      fields: [
        { label: 'Category', html: CivicConnect.escapeHtml(s.category || '—') },
      ],
      actions: `
        <label for="status-select" class="block text-xs font-medium text-slate mb-1.5">Status</label>
        <div class="flex flex-wrap gap-2">
          <select id="status-select" class="flex-1 min-w-0 border border-borderc rounded-lg px-2.5 py-2 text-sm">${statusOptions}</select>
          <button id="status-btn" type="button" class="bg-teal text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-teal/90 transition">Update status</button>
        </div>`,
    });

    document.getElementById('status-btn')?.addEventListener('click', async () => {
      const status = document.getElementById('status-select').value;
      const r = await CivicConnect.apiFetch(`${window.APP_URL}/api/submissions/update`, {
        method: 'POST', body: JSON.stringify({ id, action: 'change_status', status }),
      });
      if (r.success) { CivicConnect.toast('Status updated successfully.', 'success'); CivicConnect.closeModal('submission-modal'); loadTable(); }
    });
  }

  loadTable();
})();
