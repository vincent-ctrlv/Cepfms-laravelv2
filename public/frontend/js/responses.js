/**
 * CivicConnect — Admin: Response Composer (split-panel)
 * Powers admin/responses.blade.php
 */
(() => {
  const listBody    = document.getElementById('resp-list-body');
  const searchInput = document.getElementById('resp-search');
  const searchClear = document.getElementById('resp-search-clear');
  const filterTabs  = document.querySelectorAll('.resp-tab-btn');
  const badgeAll    = document.getElementById('badge-count-all');
  const badgeNeeds  = document.getElementById('badge-count-needs');
  const pageInfo    = document.getElementById('resp-page-info');
  const pageCurrent = document.getElementById('resp-page-current');
  const prevBtn     = document.getElementById('resp-prev-btn');
  const nextBtn     = document.getElementById('resp-next-btn');

  const placeholder = document.getElementById('resp-detail-placeholder');
  const composer    = document.getElementById('resp-composer');
  const refEl       = document.getElementById('resp-d-ref');
  const titleEl     = document.getElementById('resp-d-title');
  const nameEl      = document.getElementById('resp-d-name');
  const emailEl     = document.getElementById('resp-d-email');
  const descEl      = document.getElementById('resp-d-desc');
  const bodyEl      = document.getElementById('resp-body');
  const notifyEl    = document.getElementById('resp-notify');
  const sendBtn     = document.getElementById('resp-send-btn');
  const sendLabel   = document.getElementById('resp-send-label');
  const metaEl      = document.getElementById('resp-d-meta');
  const threadEl    = document.getElementById('resp-thread');
  const charCountEl = document.getElementById('resp-char-count');
  const statusNote  = document.getElementById('resp-status-note');

  if (!listBody) return;

  // ---- Quick template chips -------------------------------------------------
  const SIGNOFF = window.EMAIL_SIGNOFF || 'CEPFMS Team'; // System Settings → Branding
  const TEMPLATES = {
    'Acknowledge receipt':
      'Thank you for your submission. We have received it and it is currently being reviewed by our team. We will keep you updated on any developments.\n\nSincerely,\n' + SIGNOFF,
    'Request more info':
      'Thank you for reaching out. To better assist you, we kindly request additional information or clarification regarding your concern. Please reply with the relevant details at your earliest convenience.\n\nSincerely,\n' + SIGNOFF,
    // One per tracker status, matching what the citizen sees on the Track page.
    'Received':
      'Thank you for your submission. It has been confirmed and logged into our system, and our team will begin reviewing it shortly. You can follow its progress anytime on the Track page using your reference number.\n\nSincerely,\n' + SIGNOFF,
    'In Review':
      'We would like to let you know that your submission is now being reviewed by our staff. We are evaluating the details you provided and will update you once a decision or next step is in place.\n\nSincerely,\n' + SIGNOFF,
    'Assigned':
      'Your submission has been assigned to a staff member who is now working on it. We will keep you informed of the progress and let you know once it has been addressed.\n\nSincerely,\n' + SIGNOFF,
    'Resolved':
      'We are pleased to inform you that your submission has been resolved. The necessary steps have been taken to address it. Thank you for helping improve San Jose del Monte, and please do not hesitate to reach out if you have further concerns.\n\nSincerely,\n' + SIGNOFF,
  };

  // Status templates also move the submission to that status when the reply is sent.
  const STATUS_TEMPLATES = ['Received', 'In Review', 'Assigned', 'Resolved'];
  let currentStatus = null; // status of the selected submission
  let pendingStatus = null; // status the next reply will set, if any

  function updateStatusNote() {
    const show = !!pendingStatus && pendingStatus !== currentStatus;
    statusNote?.classList.toggle('hidden', !show);
    if (show) {
      document.getElementById('resp-status-from').textContent = currentStatus || 'Pending';
      document.getElementById('resp-status-to').textContent = pendingStatus;
    }
  }

  document.getElementById('resp-status-keep')?.addEventListener('click', () => {
    pendingStatus = null;
    updateStatusNote();
  });

  document.querySelectorAll('.resp-tpl-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const text = TEMPLATES[btn.dataset.tpl] || btn.dataset.tpl;
      const draft = bodyEl?.value.trim();
      if (draft && draft !== text && !CivicConnect.confirmDialog('Replace your current draft with this template?')) return;
      if (bodyEl) {
        bodyEl.value = text;
        pendingStatus = STATUS_TEMPLATES.includes(btn.dataset.tpl) ? btn.dataset.tpl : null;
        updateStatusNote();
        updateComposerState();
        bodyEl.focus();
      }
    });
  });

  // ---- Composer: send-button label follows the notify checkbox ------------
  function sendLabelText() {
    return notifyEl?.checked ? 'Send & notify citizen' : 'Save response';
  }
  function updateComposerState() {
    if (sendLabel && !sendBtn.disabled) sendLabel.textContent = sendLabelText();
    if (charCountEl) {
      const n = bodyEl?.value.trim().length || 0;
      charCountEl.textContent = n ? `· ${n} characters` : '';
    }
  }
  notifyEl?.addEventListener('change', updateComposerState);
  bodyEl?.addEventListener('input', () => {
    // Clearing the box drops the template, and its status change with it.
    if (!bodyEl.value.trim() && pendingStatus) { pendingStatus = null; updateStatusNote(); }
    updateComposerState();
  });

  const STATUS_BADGE = { Pending: 'badge-slate', Received: 'badge-blue', 'In Review': 'badge-amber', Assigned: 'badge-teal', Escalated: 'badge-red', Resolved: 'badge-green' };

  // ---- State ----------------------------------------------------------------
  let currentRefNum     = null;
  let currentFilter     = 'all';
  let currentPage       = 1;
  let totalPages        = 1;
  let totalCount        = 0;
  let debounceTimer;

  // ---- Search Events --------------------------------------------------------
  searchInput?.addEventListener('input', () => {
    const val = searchInput.value.trim();
    if (searchClear) {
      searchClear.classList.toggle('hidden', val === '');
    }
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      currentPage = 1;
      loadList();
    }, 280);
  });

  searchClear?.addEventListener('click', () => {
    if (searchInput) {
      searchInput.value = '';
      searchInput.focus();
    }
    searchClear.classList.add('hidden');
    currentPage = 1;
    loadList();
  });

  // ---- Filter Tabs ----------------------------------------------------------
  filterTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const filter = tab.dataset.filter || 'all';
      if (filter === currentFilter) return;

      filterTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      currentFilter = filter;
      currentPage   = 1;
      loadList();
    });
  });

  // ---- Pagination Buttons ---------------------------------------------------
  prevBtn?.addEventListener('click', () => {
    if (currentPage > 1) {
      currentPage--;
      loadList();
    }
  });

  nextBtn?.addEventListener('click', () => {
    if (currentPage < totalPages) {
      currentPage++;
      loadList();
    }
  });

  // ---- Helpers: show/hide loading in list body ------------------------------
  function setListHtml(html) {
    listBody.innerHTML = html;
  }

  // ---- Load submission list -------------------------------------------------
  async function loadList() {
    setListHtml('<div class="resp-list-loading">Loading submissions…</div>');

    const params = new URLSearchParams({
      search: searchInput?.value.trim() ?? '',
      filter: currentFilter,
      page:   currentPage,
    });

    const result = await CivicConnect.apiFetch(
      `${window.APP_URL}/api/responses/submissions-for-response?${params}`
    );

    if (!result.success || !result.data) {
      setListHtml('<div class="resp-list-empty">Error loading submissions. Please refresh.</div>');
      updatePagination(0, 1, 1, 15);
      return;
    }

    const { rows, total, page, per_page, total_pages, counts } = result.data;

    // Update global tab badge counts
    if (counts) {
      if (badgeAll)   badgeAll.textContent   = counts.all ?? 0;
      if (badgeNeeds) badgeNeeds.textContent = counts.needs_response ?? 0;
    }

    currentPage = page;
    totalPages  = total_pages || 1;
    totalCount  = total;

    updatePagination(total, page, totalPages, per_page);

    if (!rows || !rows.length) {
      let emptyMsg = 'No submissions found.';
      if (currentFilter === 'needs_response') {
        emptyMsg = 'All submissions have been responded to! Great job.';
      } else if (searchInput?.value.trim()) {
        emptyMsg = 'No submissions matching your search.';
      }
      setListHtml(`<div class="resp-list-empty">${CivicConnect.escapeHtml(emptyMsg)}</div>`);
      return;
    }

    setListHtml('');
    let hasActiveItem = false;

    rows.forEach(row => {
      const item = document.createElement('div');
      item.className = 'resp-sub-item';
      item.tabIndex = 0;
      item.setAttribute('role', 'button');
      item.dataset.id  = row.id;
      item.dataset.ref = row.reference_number;

      if (currentRefNum === row.reference_number) {
        item.classList.add('active');
        hasActiveItem = true;
      }

      const responseCount = parseInt(row.response_count, 10) || 0;
      const citizenName   = row.citizen_name ? CivicConnect.titleCase(row.citizen_name) : 'Anonymous';
      const badgeClass    = responseCount > 0 ? 'resp-badge-done' : 'resp-badge-needs';
      const badgeText     = responseCount > 0
        ? `${responseCount} ${responseCount === 1 ? 'reply' : 'replies'}`
        : 'Needs reply';

      item.innerHTML = `
        <p class="title" title="${CivicConnect.escapeHtml(row.subject)}">${CivicConnect.escapeHtml(row.subject)}</p>
        <div class="meta-row">
          <span class="ref-badge">${CivicConnect.escapeHtml(row.reference_number)}</span>
          <span class="citizen-label" title="${CivicConnect.escapeHtml(citizenName)}">&middot; ${CivicConnect.escapeHtml(citizenName)}</span>
          <span class="resp-badge ${badgeClass}">${badgeText}</span>
        </div>
        <div class="sub-row">
          <span class="capitalize">${CivicConnect.escapeHtml(row.submission_type || '')}</span>
          <span>${CivicConnect.escapeHtml(CivicConnect.timeAgo(row.created_at))}</span>
        </div>
      `;

      item.addEventListener('click', () => selectSubmission(item, row));
      item.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectSubmission(item, row); }
      });
      listBody.appendChild(item);
    });
  }

  // ---- Update pagination bar ------------------------------------------------
  function updatePagination(total, page, totalPages, perPage) {
    if (pageInfo) {
      if (total === 0) {
        pageInfo.textContent = 'Showing 0 of 0';
      } else {
        const start = (page - 1) * perPage + 1;
        const end   = Math.min(page * perPage, total);
        pageInfo.textContent = `Showing ${start}–${end} of ${total}`;
      }
    }

    if (pageCurrent) {
      pageCurrent.textContent = `${page} / ${totalPages}`;
    }

    if (prevBtn) prevBtn.disabled = (page <= 1);
    if (nextBtn) nextBtn.disabled = (page >= totalPages);
  }

  // ---- Select a submission --------------------------------------------------
  function selectSubmission(itemEl, row) {
    // Highlight active item
    listBody.querySelectorAll('.resp-sub-item').forEach(el => el.classList.remove('active'));
    itemEl.classList.add('active');

    currentRefNum = row.reference_number;

    // Populate detail panel
    if (refEl)   refEl.textContent   = row.reference_number;
    if (titleEl) titleEl.textContent = row.subject;
    if (descEl)  descEl.textContent  = row.description || 'No description provided.';
    if (metaEl) {
      const created = new Date(row.created_at);
      metaEl.innerHTML = `
        <span class="badge badge-slate capitalize">${CivicConnect.escapeHtml(row.submission_type || '')}</span>
        <span class="badge ${STATUS_BADGE[row.status] || 'badge-slate'}" data-status-badge>${CivicConnect.escapeHtml(row.status || '')}</span>
        <span title="${isNaN(created) ? '' : CivicConnect.escapeHtml(created.toLocaleString())}">Submitted ${CivicConnect.escapeHtml(CivicConnect.timeAgo(row.created_at))}</span>
      `;
    }
    loadThread(row.id);

    if (nameEl) {
      nameEl.textContent = CivicConnect.titleCase(row.citizen_name) || 'Anonymous Citizen';
    }
    if (emailEl) {
      if (row.citizen_email) {
        emailEl.innerHTML = `<a href="mailto:${CivicConnect.escapeHtml(row.citizen_email)}" class="text-teal hover:underline font-mono">${CivicConnect.escapeHtml(row.citizen_email)}</a>`;
      } else {
        emailEl.textContent = 'No email provided';
      }
    }

    // Reset composer form
    currentStatus = row.status || null;
    pendingStatus = null;
    updateStatusNote();
    if (bodyEl)   bodyEl.value    = '';
    if (notifyEl) notifyEl.checked = true;
    updateComposerState();

    // Show composer, hide placeholder
    placeholder?.classList.add('hidden');
    composer?.classList.remove('hidden');
    // Stacked layout (narrow screens): the composer sits below the list, so bring it into view.
    if (window.matchMedia('(max-width: 960px)').matches) {
      composer?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
      bodyEl?.focus();
    }
  }

  // ---- Previous replies for the selected submission ----------------------
  let threadRequest = 0;
  async function loadThread(submissionId) {
    if (!threadEl) return;
    const requestId = ++threadRequest;
    threadEl.innerHTML = '<p class="resp-thread-empty">Loading…</p>';
    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/responses/list?submission_id=${encodeURIComponent(submissionId)}`);
    if (requestId !== threadRequest) return; // a different submission was selected meanwhile
    const replies = result.success ? (result.data.rows || []) : null;
    if (!replies) {
      threadEl.innerHTML = '<p class="resp-thread-empty">Could not load previous replies.</p>';
      return;
    }
    if (!replies.length) {
      threadEl.innerHTML = '<p class="resp-thread-empty">No replies yet — this will be the first response.</p>';
      return;
    }
    threadEl.innerHTML = `<div class="resp-thread">${replies.slice().reverse().map(r => `
      <div class="resp-thread-item">
        <div class="resp-thread-head">
          <span><strong>${CivicConnect.escapeHtml(r.staff_name || 'Staff')}</strong> · ${CivicConnect.escapeHtml(CivicConnect.timeAgo(r.sent_at || r.created_at))}</span>
          <span class="badge ${r.status === 'Sent' ? 'badge-green' : 'badge-slate'}">${CivicConnect.escapeHtml(r.status)}</span>
        </div>
        <div class="resp-thread-body">${CivicConnect.escapeHtml(r.body || '')}</div>
      </div>`).join('')}</div>`;
  }

  // ---- Send response --------------------------------------------------------
  sendBtn?.addEventListener('click', async () => {
    const body = bodyEl?.value.trim();
    if (!body) {
      CivicConnect.toast('Please write a response before sending.', 'warning');
      return;
    }
    if (!currentRefNum) {
      CivicConnect.toast('Please select a submission first.', 'warning');
      return;
    }

    sendBtn.disabled    = true;
    if (sendLabel) sendLabel.textContent = 'Sending…';

    // 1. Create the response record
    const createResult = await CivicConnect.apiFetch(
      `${window.APP_URL}/api/responses/create`,
      {
        method: 'POST',
        body: JSON.stringify({
          reference_number: currentRefNum,
          subject:          `Regarding: ${titleEl?.textContent ?? currentRefNum}`,
          body,
          internal_notes:   '',
          save_action:      'pending_approval',
          ...(pendingStatus && pendingStatus !== currentStatus ? { status: pendingStatus } : {}),
        }),
      }
    );

    if (!createResult.success) {
      sendBtn.disabled    = false;
      updateComposerState();
      return;
    }

    // The reply used a status template: show the new status right away.
    const statusMsg = createResult.data.status_changed ? ` Status changed to ${createResult.data.submission_status}.` : '';
    if (createResult.data.status_changed) {
      currentStatus = createResult.data.submission_status;
      const badge = metaEl?.querySelector('[data-status-badge]');
      if (badge) {
        badge.className = `badge ${STATUS_BADGE[currentStatus] || 'badge-slate'}`;
        badge.textContent = currentStatus;
      }
    }
    pendingStatus = null;
    updateStatusNote();

    // 2. If notify is checked, send immediately via email
    if (notifyEl?.checked) {
      const sendResult = await CivicConnect.apiFetch(`${window.APP_URL}/api/responses/send`, {
        method: 'POST',
        body: JSON.stringify({ id: createResult.data.id }),
      });

      if (!sendResult.success) {
        // /api/responses/create already saved the response — just surface that the email didn't go out.
      } else if (!sendResult.data?.citizen_email) {
        CivicConnect.toast('Response saved, but no citizen email is on file to notify.' + statusMsg, 'warning');
      } else if (!sendResult.data.email_sent) {
        CivicConnect.toast('Response saved, but the notification email failed to send. Check SMTP settings.' + statusMsg, 'error');
      } else {
        CivicConnect.toast('Response sent and citizen notified by email.' + statusMsg, 'success');
      }
    } else {
      CivicConnect.toast('Response saved.' + statusMsg, 'success');
    }

    sendBtn.disabled    = false;
    if (bodyEl) bodyEl.value = '';
    updateComposerState();
    const selected = listBody.querySelector('.resp-sub-item.active');
    if (selected) loadThread(selected.dataset.id);

    // Refresh list to update response counts and active tab badges
    loadList();
  });

  // ---- Init -----------------------------------------------------------------
  loadList();
})();
