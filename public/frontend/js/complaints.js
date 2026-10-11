/**
 * CEPFMS — Admin: Complaints & Issues table + detail modal
 *
 * Hick's Law applied:
 *  - Filters limited to the 2 most useful dimensions (search, priority).
 *  - Detail modal split into clear labeled sections: info grid -> description
 *    -> AI summary -> attachments -> history -> grouped actions.
 *  - Action controls each in their own labelled zone, reducing simultaneous
 *    decisions the admin must make.
 */
(() => {
  const tbody = document.getElementById('complaints-tbody');
  if (!tbody) return;

  const emptyEl      = document.getElementById('complaints-empty');
  const paginationEl = document.getElementById('complaints-pagination');
  const filters      = ['c-search', 'c-priority'].map(id => document.getElementById(id));

  let currentPage = 1;
  let debounceTimer;
  filters.forEach(el => el?.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      currentPage = 1;
      loadTable();
    }, 250);
  }));

  /* Badge helpers */
  function priorityClass(p) {
    const map = { Critical: 'badge-red', High: 'badge-amber', Medium: 'badge-blue', Low: 'badge-slate' };
    return map[p] || 'badge-slate';
  }
  function statusClass(status) {
    const map = { Pending: 'badge-slate', Received: 'badge-blue', 'In Review': 'badge-amber', Assigned: 'badge-teal', Escalated: 'badge-red', Resolved: 'badge-green' };
    return map[status] || 'badge-slate';
  }

  /* Table */
  async function loadTable() {
    CivicConnect.showTableSkeleton(tbody);
    emptyEl.classList.add('hidden');

    const params = new URLSearchParams({
      search:   document.getElementById('c-search')?.value   || '',
      priority: document.getElementById('c-priority')?.value || '',
      page:     currentPage,
    });

    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/complaints/list?${params}`);
    tbody.innerHTML = '';
    if (!result.success) {
      if (paginationEl) paginationEl.innerHTML = '';
      return;
    }

    if (!result.data.rows.length) {
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
        <td class="px-4 py-3"><span class="badge ${priorityClass(row.priority)}">${row.priority}</span></td>
        <td class="px-4 py-3">${CivicConnect.sentimentBadge(row.sentiment)}</td>
        <td class="px-4 py-3"><span class="badge ${statusClass(row.status)}">${row.status}</span></td>
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
    const body = document.getElementById('complaint-modal-body');
    body.innerHTML = '<p class="text-sm text-slate py-12 text-center">Loading…</p>';
    CivicConnect.openModal('complaint-modal');

    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/submissions/get?id=${id}`);
    if (!result.success) { CivicConnect.closeModal('complaint-modal'); return; }
    const s = result.data;

    body.innerHTML = CivicConnect.detailView({
      modalId: 'complaint-modal',
      s,
      badges: `<span class="badge ${statusClass(s.status)}">${CivicConnect.escapeHtml(s.status || '—')}</span>
               <span class="badge ${priorityClass(s.priority)}">${CivicConnect.escapeHtml(s.priority || '—')} priority</span>`,
      fields: [
        { label: 'Category', html: CivicConnect.escapeHtml(s.category || '—') },
      ],
      actions: `
        <div class="flex flex-wrap gap-2">
          <button id="c-close-btn" type="button" class="bg-teal text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-teal/90 transition">Close complaint</button>
        </div>`,
    });

    document.getElementById('c-close-btn')?.addEventListener('click', async () => {
      if (!CivicConnect.confirmDialog('Close this complaint as resolved?')) return;
      const r = await CivicConnect.apiFetch(`${window.APP_URL}/api/complaints/update`, {
        method: 'POST', body: JSON.stringify({ id, action: 'close' }),
      });
      if (r.success) { CivicConnect.toast('Complaint closed successfully.', 'success'); CivicConnect.closeModal('complaint-modal'); loadTable(); }
    });
  }

  loadTable();
})();
