/**
 * CEPFMS — Global JS helpers
 * Toasts, modal utilities, CSRF-aware fetch wrapper, mobile sidebar toggle.
 */

const CivicConnect = (() => {
  const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

  function toast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `<span>${escapeHtml(message)}</span>`;
    container.appendChild(el);
    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transition = 'opacity .2s ease';
      setTimeout(() => el.remove(), 200);
    }, duration);
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  /** Idle timeout hit: go to the login page (it shows the "session expired" flash). */
  function redirectIfSessionExpired(res) {
    if (res.headers.get('X-Session-Expired') === '1') {
      window.location.href = `${window.APP_URL}/auth/login`;
      return true;
    }
    // Citizen portal: signed out (or never signed in) — back to "Sign in with Google".
    if (res.headers.get('X-Citizen-Login') === '1') {
      window.location.href = `${window.APP_URL}/citizen/login`;
      return true;
    }
    return false;
  }

  // options.background: an automatic poll, not user activity — the server
  // won't let it reset the idle timeout.
  async function apiFetch(url, options = {}) {
    const { background, ...rest } = options;
    const opts = Object.assign({ headers: {} }, rest);
    opts.headers['X-Requested-With'] = 'XMLHttpRequest';
    if (background) opts.headers['X-Background-Refresh'] = '1';
    if (!(opts.body instanceof FormData)) {
      opts.headers['Content-Type'] = 'application/json';
    }
    if (opts.method && opts.method !== 'GET') {
      opts.headers['X-CSRF-Token'] = csrfToken();
    }
    try {
      const res = await fetch(url, opts);
      if (redirectIfSessionExpired(res)) return { success: false, message: 'Session expired' };
      const data = await res.json().catch(() => ({ success: false, message: 'Unexpected server response.' }));
      if (!res.ok || !data.success) {
        toast(data.message || 'Something went wrong. Please try again.', 'error');
      }
      return data;
    } catch (err) {
      toast('Network error. Please check your connection and try again.', 'error');
      return { success: false, message: 'Network error' };
    }
  }

  /** Seconds → "m:ss" (or "h:mm:ss" past an hour). */
  function formatCountdown(totalSeconds) {
    const s = Math.max(0, Math.floor(totalSeconds));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = String(s % 60).padStart(2, '0');
    return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${sec}` : `${m}:${sec}`;
  }

  /**
   * Tick down from `seconds`, calling onTick(remaining) each second and
   * onDone() at zero. Anchored to a deadline so a backgrounded tab doesn't
   * drift. Returns a function that cancels the countdown.
   */
  function countdown(seconds, onTick, onDone) {
    const deadline = Date.now() + seconds * 1000;
    let timer = null;
    const tick = () => {
      const remaining = Math.ceil((deadline - Date.now()) / 1000);
      if (remaining <= 0) {
        clearInterval(timer);
        onTick?.(0);
        onDone?.();
        return;
      }
      onTick?.(remaining);
    };
    timer = setInterval(tick, 1000);
    tick();
    return () => clearInterval(timer);
  }

  function titleCase(str) {
    if (!str) return str;
    // Only capitalizes the first letter of each word; leaves the rest of each
    // word untouched so legitimate internal capitals (McDonald, DiCaprio)
    // survive instead of being lowercased.
    return str.replace(/(^|[\s'-])\p{L}/gu, (m) => m.toUpperCase());
  }

  function sentimentClass(sm) {
    const map = { Positive: 'badge-green', Neutral: 'badge-slate', Negative: 'badge-red' };
    return map[sm] || 'badge-slate';
  }

  function sentimentBadge(sm) {
    if (!sm) return '—';
    return `<span class="badge ${sentimentClass(sm)}">${escapeHtml(sm)}</span>`;
  }

  /** Full-width "Location" cell for a submission detail grid. */
  function locationCell(s) {
    const address = [s.street, s.barangay, s.city].filter(Boolean).join(', ');
    return `<div class="col-span-2"><p class="text-slate text-xs">Location</p><p class="text-navy font-medium">${escapeHtml(address || '—')}</p></div>`;
  }

  function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    }
  }

  function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.add('hidden');
      document.body.style.overflow = '';
    }
  }

  function confirmDialog(message) {
    return window.confirm(message);
  }

  /**
   * Styled in-app confirmation (instead of the browser's "localhost says" box).
   * Resolves true on confirm, false on cancel / backdrop click / Escape.
   *   await CivicConnect.confirmModal({ title, message, confirmText, danger })
   */
  function confirmModal({ title = 'Are you sure?', message = '', confirmText = 'Confirm', cancelText = 'Cancel', danger = false } = {}) {
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.className = 'fixed inset-0 z-[60] modal-backdrop flex items-center justify-center p-4';
      overlay.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6" role="dialog" aria-modal="true" aria-labelledby="cc-confirm-title">
          <div class="flex items-start gap-3">
            <span class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center ${danger ? 'bg-red-50 text-dangerred' : 'bg-blue-50 text-teal'}">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                ${danger
                  ? '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'
                  : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>'}
              </svg>
            </span>
            <div class="min-w-0">
              <h2 id="cc-confirm-title" class="text-base font-semibold text-navy">${escapeHtml(title)}</h2>
              <p class="text-sm text-slate mt-1.5">${escapeHtml(message)}</p>
            </div>
          </div>
          <div class="flex justify-end gap-2 mt-6">
            <button type="button" data-cc="cancel" class="text-sm font-semibold py-2.5 px-5 rounded border border-borderc text-navy hover:bg-pagebg transition">${escapeHtml(cancelText)}</button>
            <button type="button" data-cc="ok" class="text-sm font-semibold py-2.5 px-5 rounded text-white transition ${danger ? 'bg-dangerred hover:opacity-90' : 'bg-teal hover:bg-teal/90'}">${escapeHtml(confirmText)}</button>
          </div>
        </div>`;

      const prevOverflow = document.body.style.overflow;
      function close(result) {
        document.removeEventListener('keydown', onKey, true);
        overlay.remove();
        document.body.style.overflow = prevOverflow;
        resolve(result);
      }
      function onKey(e) {
        if (e.key === 'Escape') { e.stopPropagation(); close(false); }
      }

      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close(false);
        const action = e.target.closest('[data-cc]')?.dataset.cc;
        if (action) close(action === 'ok');
      });
      document.addEventListener('keydown', onKey, true);
      document.body.appendChild(overlay);
      document.body.style.overflow = 'hidden';
      overlay.querySelector('[data-cc="ok"]').focus();
    });
  }

  /**
   * Renders a "Showing X–Y of Z" + Previous/Next pagination bar into `container`.
   * Styled consistently with the responses page split-panel pagination footer.
   * Calls onChange(newPage) when the user navigates.
   */
  /**
   * Placeholder rows while a table loads: one shimmer bar per column (taken
   * from the table's header). The caller's next tbody update replaces them.
   */
  function showTableSkeleton(tbody, rows = 8) {
    if (!tbody) return;
    const cols = tbody.closest('table')?.querySelectorAll('thead th').length || 1;
    const widths = ['70%', '55%', '85%', '60%', '45%', '75%'];
    const cell = (c, r) => (c === cols - 1 && cols > 1)
      ? '<td class="px-4 py-4"><div class="ls-skeleton h-3 w-12 ml-auto"></div></td>'
      : `<td class="px-4 py-4"><div class="ls-skeleton h-3" style="width:${widths[(c + r) % widths.length]}"></div></td>`;
    tbody.innerHTML =
      `<tr class="sr-only"><td>Loading…</td></tr>` +
      Array.from({ length: rows }, (_, r) =>
        `<tr aria-hidden="true">${Array.from({ length: cols }, (_, c) => cell(c, r)).join('')}</tr>`
      ).join('');
  }

  /* ----- Admin table cells ----- */

  // "Oct 1, 2026" with "3 days ago" underneath; full timestamp on hover.
  // "3 days ago" / "just now". Empty string for an unparseable date.
  function timeAgo(value) {
    const d = new Date(value);
    if (isNaN(d)) return '';
    const secs = Math.round((Date.now() - d.getTime()) / 1000);
    const units = [[31536000, 'year'], [2592000, 'month'], [86400, 'day'], [3600, 'hour'], [60, 'minute']];
    for (const [size, unit] of units) {
      if (secs >= size) { const n = Math.floor(secs / size); return `${n} ${unit}${n > 1 ? 's' : ''} ago`; }
    }
    return 'just now';
  }

  function dateCell(value) {
    const d = new Date(value);
    if (isNaN(d)) return '<span class="text-slate">—</span>';
    const ago = timeAgo(value);
    return `<span class="block whitespace-nowrap text-xs text-navy" title="${escapeHtml(d.toLocaleString())}">${d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}</span>`
         + `<span class="block whitespace-nowrap text-[11px] text-slate">${ago}</span>`;
  }

  // "Maria Santos" -> "MS"; "" for an empty name.
  function initials(name) {
    const parts = (name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '';
    return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
  }

  // Initials avatar + name.
  function citizenCell(name) {
    const full = titleCase(name || '');
    if (!full) return '<span class="text-slate">—</span>';
    return `<span class="flex items-center gap-2 min-w-0"><span class="table-avatar" aria-hidden="true">${escapeHtml(initials(full))}</span><span class="truncate">${escapeHtml(full)}</span></span>`;
  }

  // Clicking anywhere on a row (or Enter on a focused row) presses that row's
  // open-detail button. Clicks on other buttons/links in the row are left alone.
  function enableRowClick(tbody, buttonSelector) {
    if (!tbody || tbody.dataset.rowClick) return;
    tbody.dataset.rowClick = buttonSelector;
    const open = (tr) => tr?.querySelector(buttonSelector)?.click();
    tbody.addEventListener('click', (e) => {
      if (e.target.closest('button, a, input, select, textarea, label')) return;
      if (window.getSelection()?.toString()) return; // let staff copy text from a row
      open(e.target.closest('tr'));
    });
    tbody.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && e.target.matches('tr')) open(e.target);
    });
    new MutationObserver(() => {
      tbody.querySelectorAll('tr:not([tabindex])').forEach(tr => {
        if (tr.querySelector(buttonSelector)) tr.tabIndex = 0;
      });
    }).observe(tbody, { childList: true });
  }

  function renderPagination(container, { page, perPage, total }, onChange) {
    if (!container) return;
    if (typeof total !== 'number' || total <= 0) {
      container.innerHTML = '';
      return;
    }
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const start = (page - 1) * perPage + 1;
    const end = Math.min(total, page * perPage);
    const prevDisabled = page <= 1;
    const nextDisabled = page >= totalPages;

    container.innerHTML = `
      <div class="flex items-center justify-between px-4 py-2.5 border-t border-borderc text-xs text-slate bg-white">
        <span>Showing ${start}&ndash;${end} of ${total}</span>
        <div class="flex items-center gap-1.5">
          <button type="button" data-page="${page - 1}" ${prevDisabled ? 'disabled' : ''}
            class="page-btn flex items-center justify-center w-7 h-7 rounded border border-borderc transition-all ${prevDisabled ? 'opacity-35 cursor-not-allowed bg-white text-slate-400' : 'bg-white hover:bg-pagebg text-navy hover:border-slate-300'}"
            title="Previous page">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <span class="font-semibold text-navy text-xs px-1.5">${page} / ${totalPages}</span>
          <button type="button" data-page="${page + 1}" ${nextDisabled ? 'disabled' : ''}
            class="page-btn flex items-center justify-center w-7 h-7 rounded border border-borderc transition-all ${nextDisabled ? 'opacity-35 cursor-not-allowed bg-white text-slate-400' : 'bg-white hover:bg-pagebg text-navy hover:border-slate-300'}"
            title="Next page">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
      </div>
    `;
    container.querySelectorAll('.page-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.disabled) return;
        onChange(Number(btn.dataset.page));
      });
    });
  }

  function initSidebarToggle() {
    const btn = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('admin-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (!btn || !sidebar) return;
    btn.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      backdrop?.classList.toggle('hidden');
    });
    backdrop?.addEventListener('click', () => {
      sidebar.classList.remove('open');
      backdrop.classList.add('hidden');
    });
  }

  function initCitizenNavToggle() {
    const btn = document.getElementById('citizen-nav-toggle');
    const menu = document.getElementById('citizen-mobile-nav');
    if (!btn || !menu) return;
    btn.addEventListener('click', () => {
      const willOpen = menu.classList.contains('hidden');
      menu.classList.toggle('hidden');
      btn.setAttribute('aria-expanded', String(willOpen));
    });
    // Close the mobile menu automatically if the viewport grows past the mobile breakpoint.
    window.addEventListener('resize', () => {
      if (window.innerWidth >= 640 && !menu.classList.contains('hidden')) {
        menu.classList.add('hidden');
        btn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* Topbar bell: alerts only on submissions that arrived since this user last
     opened it. "Last seen" is the newest created_at they were shown (a DB
     timestamp from the API), kept per user in this browser. */
  function initNotifications() {
    const bell = document.getElementById('notif-bell');
    const badge = document.getElementById('notif-dot');
    const panel = document.getElementById('notif-panel');
    const list = document.getElementById('notif-list');
    if (!bell || !panel || !list) return;

    const storageKey = `cepfms_notif_seen_${bell.dataset.user || '0'}`;
    const readSeen = () => { try { return localStorage.getItem(storageKey) || ''; } catch (e) { return ''; } };
    const writeSeen = (v) => { try { if (v) localStorage.setItem(storageKey, v); } catch (e) { /* private mode: badge just resets per page */ } };

    let seen = readSeen();
    let latestAt = null;
    let lastToastedAt = null;

    function setBadge(count) {
      badge.textContent = count > 9 ? '9+' : String(count);
      badge.classList.toggle('hidden', count === 0);
      bell.title = count ? `${count} new submission${count > 1 ? 's' : ''}` : 'Notifications';
    }

    async function load(background = false) {
      const params = seen ? `?since=${encodeURIComponent(seen)}` : '';
      const result = await apiFetch(`${window.APP_URL}/api/notifications/recent${params}`, { background });
      if (!result.success) {
        if (!background) list.innerHTML = '<p class="px-4 py-6 text-xs text-slate text-center">Couldn\'t load notifications.</p>';
        return;
      }
      const { new_count, pending_count, latest_at, items, link } = result.data;
      latestAt = latest_at;

      // First visit on this browser: start from "now" instead of flagging the whole backlog.
      if (!seen && latest_at) { seen = latest_at; writeSeen(seen); }

      const panelOpen = !panel.classList.contains('hidden');
      setBadge(panelOpen ? 0 : new_count);

      // A submission arrived while this page was open — say so once. (latest_at can also
      // move backwards when the newest item gets moderated; that's not a new arrival.)
      // DB timestamps are "YYYY-MM-DD HH:MM:SS", so string comparison orders them correctly.
      if (background && new_count > 0 && latest_at && (!lastToastedAt || latest_at > lastToastedAt)) {
        const newest = items.find(it => it.is_new);
        toast(new_count === 1 && newest
          ? `New ${newest.type.toLowerCase()}: ${newest.subject}`
          : `${new_count} new submissions — open the bell to view.`, 'info');
      }
      if (latest_at && (!lastToastedAt || latest_at > lastToastedAt)) lastToastedAt = latest_at;

      const viewAll = document.getElementById('notif-view-all');
      if (viewAll && link) viewAll.href = link;

      if (!items.length) {
        list.innerHTML = '<p class="px-4 py-6 text-xs text-slate text-center">You\'re all caught up.</p>';
        return;
      }
      list.innerHTML = items.map(item => `
        <a href="${escapeHtml(link)}" class="flex gap-2.5 px-4 py-3 transition ${item.is_new ? 'bg-blue-50/60 hover:bg-blue-50' : 'hover:bg-pagebg'}">
          <span class="mt-1.5 w-2 h-2 rounded-full flex-shrink-0 ${item.is_new ? 'bg-teal' : 'bg-transparent'}" aria-hidden="true"></span>
          <span class="min-w-0">
            <span class="block text-xs ${item.is_new ? 'font-semibold' : 'font-medium'} text-navy truncate">New ${escapeHtml(item.type).toLowerCase()}: ${escapeHtml(item.subject)}</span>
            <span class="block text-[11px] text-slate mt-0.5">${escapeHtml(item.reference_number || '')} · ${escapeHtml(item.created_at)}${item.is_new ? ' · <span class="text-teal font-semibold">New</span>' : ''}</span>
          </span>
        </a>
      `).join('') + (pending_count > items.length
        ? `<p class="px-4 py-2 text-[11px] text-slate text-center">${pending_count - items.length} more waiting</p>`
        : '');
    }

    bell.addEventListener('click', async (e) => {
      e.stopPropagation();
      const willOpen = panel.classList.contains('hidden');
      panel.classList.toggle('hidden');
      if (!willOpen) return;
      await load();          // render with "New" highlights first…
      if (latestAt && latestAt > seen) { seen = latestAt; writeSeen(seen); }  // …then mark them seen
      setBadge(0);
    });
    document.addEventListener('click', (e) => {
      if (!panel.contains(e.target) && e.target !== bell) panel.classList.add('hidden');
    });

    load();
    setInterval(() => load(true), 60000); // poll for new citizen submissions every minute
  }

  /* Typing or clicking never reaches the server, so to the 3-minute idle
     timeout (StaffIdleTimeout middleware) a staff member writing a long response looked
     idle and was logged out on Send. Real activity pings the server at most
     every 30 seconds (well inside the 3 minutes, so activity just before a
     ping is never lost); an untouched tab still times out. */
  function initKeepAlive() {
    const url = window.STAFF_KEEPALIVE_URL;
    if (!url) return; // citizen and login pages
    const EVERY_MS = 30 * 1000;
    let lastPing = Date.now(); // loading the page already counted as activity

    const onActivity = () => {
      if (Date.now() - lastPing < EVERY_MS) return;
      lastPing = Date.now();
      // Plain fetch, not apiFetch: a failed ping shouldn't pop an error toast.
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(redirectIfSessionExpired)
        .catch(() => { lastPing = Date.now() - EVERY_MS + 15000; }); // offline: retry in ~15s, not every keystroke
    };
    ['keydown', 'pointerdown', 'wheel'].forEach((type) =>
      document.addEventListener(type, onActivity, { passive: true, capture: true }));
  }

  document.addEventListener('DOMContentLoaded', () => {
    initSidebarToggle();
    initCitizenNavToggle();
    initNotifications();
    initKeepAlive();
  });

  // Event delegation: modal bodies are injected dynamically (after fetch calls),
  // so close buttons must be handled via a single delegated listener on
  // document rather than bound once at page load — otherwise buttons added
  // later (e.g. the × in a submission detail modal) never receive a handler.
  document.addEventListener('click', (e) => {
    const closeBtn = e.target.closest('[data-close-modal]');
    if (closeBtn) {
      closeModal(closeBtn.getAttribute('data-close-modal'));
      return;
    }
    // Clicking directly on the dimmed backdrop (not the white content box) also closes it.
    if (e.target.classList && e.target.classList.contains('modal-backdrop')) {
      closeModal(e.target.id);
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-backdrop:not(.hidden)').forEach(m => closeModal(m.id));
    }
  });

  /* Shared layout for the Submission / Complaint / Proposal detail modals.
     The modal body element must have no padding (the layout pads itself) so
     the header and the actions footer can stick to the scroll container.
       opts.modalId  — id for the close button
       opts.s        — /api/submissions/get payload
       opts.badges   — extra badge HTML for the header (status, priority…)
       opts.fields   — [{ label, html }] for the details grid (Category, …)
       opts.actions  — HTML for the sticky footer (buttons/forms); optional
       opts.notice   — HTML shown first in the body (e.g. a spam warning); optional
       opts.extra    — HTML section after the AI summary (e.g. an editor); optional */
  function detailView({ modalId, s, badges = '', fields = [], actions = '', notice = '', extra = '' }) {
    const icon = (d) => `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${d}</svg>`;
    const address = [s.street, s.barangay, s.city].filter(Boolean).join(', ');
    const hasPin = s.latitude && s.longitude;
    const mapLink = hasPin
      ? ` <a href="https://www.google.com/maps?q=${encodeURIComponent(s.latitude + ',' + s.longitude)}" target="_blank" rel="noopener" class="text-teal text-xs font-medium hover:underline whitespace-nowrap">Open map ↗</a>`
      : '';
    const created = new Date(s.created_at);

    const contact = [
      s.citizen_email ? `<a href="mailto:${escapeHtml(s.citizen_email)}" class="dv-contact">${icon('<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>')}<span class="truncate">${escapeHtml(s.citizen_email)}</span></a>` : '',
      s.citizen_phone ? `<a href="tel:${escapeHtml(s.citizen_phone)}" class="dv-contact">${icon('<path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.12.96.36 1.9.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.34 1.85.58 2.81.7A2 2 0 0122 16.92z"/>')}<span>${escapeHtml(s.citizen_phone)}</span></a>` : '',
    ].join('');

    const allFields = [
      { label: 'Type', html: `<span class="capitalize">${escapeHtml(s.submission_type || '—')}</span>` },
      ...fields,
      { label: 'Submitted', html: isNaN(created) ? '—' : `${escapeHtml(created.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }))} <span class="text-slate font-normal">· ${escapeHtml(timeAgo(s.created_at))}</span>` },
      { label: 'Location', html: `${escapeHtml(address || '—')}${mapLink}`, wide: true },
    ];

    const attachments = s.attachments || [];
    const attachmentsHtml = attachments.length
      ? `<div class="grid grid-cols-3 sm:grid-cols-4 gap-2">${attachments.map(a =>
          `<a href="${window.APP_URL}/${escapeHtml(a.file_path)}" target="_blank" rel="noopener" class="dv-photo"><img src="${window.APP_URL}/${escapeHtml(a.file_path)}" alt="${escapeHtml(a.original_name || 'Attachment')}" loading="lazy"></a>`
        ).join('')}</div>`
      : '<p class="dv-muted">No photos attached.</p>';

    const history = s.history || [];
    const historyHtml = history.length
      ? `<ol class="dv-timeline">${history.map(h => {
          const when = new Date(h.created_at);
          return `<li><p class="text-xs text-navy"><span class="font-medium">${escapeHtml(h.action)}</span> <span class="text-slate">by ${escapeHtml(h.staff_name || 'System')}</span></p>
                  <p class="text-[11px] text-slate" title="${escapeHtml(isNaN(when) ? '' : when.toLocaleString())}">${escapeHtml(timeAgo(h.created_at))}</p></li>`;
        }).join('')}</ol>`
      : '<p class="dv-muted">No activity yet.</p>';

    return `
      <div class="dv-header">
        <div class="min-w-0">
          <p class="dv-ref">${escapeHtml(s.reference_number || '')}</p>
          <h2 class="dv-title">${escapeHtml(s.subject || 'Untitled submission')}</h2>
          <div class="flex flex-wrap items-center gap-1.5 mt-2">${badges}${s.sentiment ? sentimentBadge(s.sentiment) : ''}</div>
        </div>
        <button type="button" data-close-modal="${modalId}" class="dv-close" aria-label="Close">&times;</button>
      </div>

      <div class="dv-body">
        ${notice}
        <section class="dv-citizen">
          <span class="table-avatar dv-avatar" aria-hidden="true">${escapeHtml(initials(s.citizen_name) || '?')}</span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-navy truncate">${escapeHtml(titleCase(s.citizen_name) || 'Anonymous citizen')}</p>
            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-0.5">${contact || '<span class="dv-muted">No contact details</span>'}</div>
          </div>
        </section>

        <dl class="dv-grid">
          ${allFields.map(f => `<div class="${f.wide ? 'sm:col-span-2' : ''}"><dt>${escapeHtml(f.label)}</dt><dd>${f.html}</dd></div>`).join('')}
        </dl>

        <section>
          <h3 class="dv-label">Citizen's message</h3>
          <p class="dv-message">${escapeHtml(s.description || 'No description provided.')}</p>
        </section>

        <section>
          <h3 class="dv-label"><span class="dv-ai">AI</span> Summary</h3>
          <p class="dv-summary">${escapeHtml(s.summary || 'No summary available.')}</p>
        </section>
        ${extra}

        <section>
          <h3 class="dv-label">Photos${attachments.length ? ` <span class="text-slate font-normal normal-case tracking-normal">(${attachments.length})</span>` : ''}</h3>
          ${attachmentsHtml}
        </section>

        <section>
          <h3 class="dv-label">Activity</h3>
          ${historyHtml}
        </section>
      </div>

      ${actions ? `<div class="dv-footer">${actions}</div>` : ''}
    `;
  }

  /* "Edit classification" panel for the Moderation & Validation modal, so
     admins can correct the AI before approving. Allowed values come from window.CLASSIFY_OPTIONS
     (partials/admin-footer.blade.php). Render with classificationEditor(s), then call
     bindClassificationEditor(s.id, onSaved) once it is in the DOM. */
  function classificationEditor(s) {
    const opts = window.CLASSIFY_OPTIONS;
    if (!opts) return '';
    const cap = (v) => v ? v.charAt(0).toUpperCase() + v.slice(1) : '';
    const current = {
      type: s.submission_type || '',
      category: s.category || '',
      priority: s.priority || '',
      sentiment: s.sentiment || 'Neutral',
    };
    const corrected = (s.history || []).some(h => h.action === 'Classification corrected');
    const conf = s.ai_confidence !== null && s.ai_confidence !== undefined && s.ai_confidence !== '' ? Math.round(parseFloat(s.ai_confidence) * 100) : null;

    let source;
    if (corrected) {
      source = '<span class="cls-source cls-source-staff">Corrected by staff</span>';
    } else if (conf !== null && !isNaN(conf)) {
      const low = conf < 60;
      source = `<span class="cls-source ${low ? 'cls-source-low' : ''}" title="How sure the AI was about this classification">AI · ${conf}% confident${low ? ' — please check' : ''}</span>`;
    } else {
      source = '<span class="cls-source">Set by AI</span>';
    }

    const select = (label, key, values, display = v => v) => {
      const options = values.map(v => `<option value="${escapeHtml(v)}" ${v === current[key] ? 'selected' : ''}>${escapeHtml(display(v))}</option>`).join('');
      return `<label class="cls-field"><span>${label}</span><select id="cls-${key}" data-original="${escapeHtml(current[key])}">${options}</select></label>`;
    };

    return `
      <section>
        <h3 class="dv-label">Classification ${source}</h3>
        <details class="cls-panel" id="cls-panel">
          <summary class="cls-summary">
            <span class="cls-chips">
              <span class="cls-chip">${escapeHtml(cap(current.type) || '—')}</span>
              <span class="cls-chip">${escapeHtml(current.category || '—')}</span>
              <span class="cls-chip">${escapeHtml(current.priority || '—')} priority</span>
              <span class="cls-chip">${escapeHtml(current.sentiment)}</span>
            </span>
            <span class="cls-edit" aria-hidden="true">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              <span class="cls-edit-open">Edit</span><span class="cls-edit-close">Editing</span>
            </span>
          </summary>
          <div class="cls-body">
            <div class="cls-grid">
              ${select('Type', 'type', opts.types, cap)}
              ${select('Category', 'category', opts.categories)}
              ${select('Priority', 'priority', opts.priorities)}
              ${select('Sentiment', 'sentiment', opts.sentiments)}
            </div>
            <div class="cls-actions">
              <p class="cls-hint" id="cls-hint">Changes are saved to the activity history.</p>
              <div class="flex gap-2">
                <button type="button" id="classify-cancel" class="text-xs font-semibold text-slate border border-borderc rounded-lg px-3 py-2 hover:bg-pagebg">Cancel</button>
                <button type="button" id="classify-btn" class="bg-teal text-white text-xs font-semibold px-3.5 py-2 rounded-lg hover:bg-teal/90 disabled:opacity-40 disabled:cursor-not-allowed" disabled>Save changes</button>
              </div>
            </div>
          </div>
        </details>
      </section>`;
  }

  function bindClassificationEditor(id, onSaved) {
    const panel = document.getElementById('cls-panel');
    const saveBtn = document.getElementById('classify-btn');
    const hint = document.getElementById('cls-hint');
    if (!panel || !saveBtn) return;
    const selects = ['type', 'category', 'priority', 'sentiment'].map(k => document.getElementById(`cls-${k}`));

    const refresh = () => {
      let changed = 0;
      selects.forEach(sel => {
        const isChanged = sel.value !== sel.dataset.original;
        sel.closest('.cls-field').classList.toggle('is-changed', isChanged);
        if (isChanged) changed++;
      });
      saveBtn.disabled = changed === 0;
      hint.textContent = changed
        ? `${changed} change${changed > 1 ? 's' : ''} — saved to the activity history.`
        : 'Changes are saved to the activity history.';
    };
    selects.forEach(sel => sel.addEventListener('change', refresh));

    document.getElementById('classify-cancel')?.addEventListener('click', () => {
      selects.forEach(sel => { sel.value = sel.dataset.original; });
      refresh();
      panel.open = false;
    });

    saveBtn.addEventListener('click', async () => {
      saveBtn.disabled = true;
      const [type, category, priority, sentiment] = selects.map(sel => sel.value);
      const r = await apiFetch(`${window.APP_URL}/api/submissions/update`, {
        method: 'POST',
        body: JSON.stringify({ id, action: 'reclassify', submission_type: type, category, priority, sentiment }),
      });
      if (r.success) { toast('Classification updated.', 'success'); onSaved?.(); }
      else refresh();
    });
  }

  /* -------------------------------------------------------------------------
   * Password-protected PDF of a page (Engagement Analytics, Dashboard), as it
   * looks on screen. Each top-level child of `root` is rendered to a JPEG
   * (html2canvas) and posted to `endpoint` (see app/Services/ProtectedPdf.php),
   * which builds the encrypted PDF and emails a fresh password for it. Print
   * opens that PDF in a new tab to print from. Inside `root`, .no-print is left
   * out of the file and .print-hidden is shown in it.
   * ---------------------------------------------------------------------- */
  const PDF_CAPTURE_WIDTH = 1100; // fixed layout width, so the PDF looks the same on any screen
  let html2canvasLoading = null;
  function loadHtml2canvas() {
    if (window.html2canvas) return Promise.resolve();
    html2canvasLoading ??= new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
      s.integrity = 'sha384-ZZ1pncU3bQe8y31yfZdMFdSpttDoPmOZg2wguVK9almUodir1PghgT0eY7Mrty8H';
      s.crossOrigin = 'anonymous';
      s.onload = resolve;
      s.onerror = () => { html2canvasLoading = null; reject(new Error('html2canvas failed to load')); };
      document.head.appendChild(s);
    });
    return html2canvasLoading;
  }

  /**
   * html2canvas draws an inline SVG (a chart) at the size it has on screen, not
   * at its size in the narrower export layout, so on a wide screen a chart ran
   * past its card and its last days were cut off. In the export copy only,
   * swap each SVG for a picture of itself, sized to its card and keeping its
   * proportions; html2canvas scales pictures correctly.
   */
  function svgsToImages(doc, container) {
    container.querySelectorAll('svg').forEach((svg) => {
      const box = svg.getBoundingClientRect();
      if (!box.width || !box.height) return;
      const cs = doc.defaultView.getComputedStyle(svg);
      // The SVG's own width can exceed its card here; the card's is the real limit.
      const cardW = svg.parentElement ? svg.parentElement.clientWidth : 0;
      let w = cardW ? Math.min(box.width, cardW) : box.width;
      let h = box.height * (w / box.width);
      const vb = svg.viewBox && svg.viewBox.baseVal;
      if (vb && vb.width && vb.height) {
        h = w * vb.height / vb.width;
        const maxH = parseFloat(cs.maxHeight);
        if (maxH && h > maxH) { h = maxH; w = h * vb.width / vb.height; }
      }
      const copy = svg.cloneNode(true);
      copy.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
      copy.setAttribute('width', w);
      copy.setAttribute('height', h);
      copy.removeAttribute('style');
      copy.style.color = cs.color; // icons draw with currentColor
      const img = doc.createElement('img');
      img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(copy));
      img.width = w;
      img.height = h;
      img.style.cssText = `display:${cs.display === 'inline' ? 'inline-block' : 'block'};width:${w}px;height:${h}px;max-width:none;flex-shrink:0;`;
      // Keep CSS-class rotations (the dashboard donut starts at 12 o'clock via -rotate-90).
      if (cs.transform && cs.transform !== 'none') {
        img.style.transform = cs.transform;
        img.style.transformOrigin = cs.transformOrigin;
      }
      svg.replaceWith(img);
    });
  }

  /** Renders `root` once, then cuts it into one JPEG per top-level section. */
  async function capturePdfSections(root) {
    await loadHtml2canvas();
    let blocks = [];
    const scale = 2;
    // html2canvas finds each font's baseline with a tiny 1×1 GIF it adds to this
    // page. Tailwind's base styles make every img display:block, which throws
    // that measurement off, so all text was drawn too low: truncated lines lost
    // their bottom half and badge text sank. Keep that one image inline while capturing.
    const metricsFix = document.createElement('style');
    metricsFix.textContent = 'img[src^="data:image/gif"]{display:inline-block!important}';
    document.head.appendChild(metricsFix);
    let canvas;
    try {
      canvas = await window.html2canvas(root, {
        scale,
        logging: false,
        backgroundColor: getComputedStyle(document.body).backgroundColor || '#ffffff',
        windowWidth: PDF_CAPTURE_WIDTH + 200,
        onclone: (doc) => {
          const r = doc.getElementById(root.id);
          r.style.width = `${PDF_CAPTURE_WIDTH}px`;
          // Same as printing: drop controls and raw-data toggles, show print-only lines.
          r.querySelectorAll('.no-print').forEach((el) => { el.style.display = 'none'; });
          r.querySelectorAll('.print-hidden').forEach((el) => { el.style.display = 'block'; });
          const st = doc.createElement('style');
          st.textContent = '*,*::before,*::after{animation:none!important;transition:none!important}';
          doc.head.appendChild(st);
          svgsToImages(doc, r);
          const top = r.getBoundingClientRect().top;
          blocks = [...r.children]
            .map((c) => c.getBoundingClientRect())
            .filter((b) => b.height > 0)
            .map((b) => ({ top: b.top - top, height: b.height }));
        },
      });
    } finally {
      metricsFix.remove();
    }
    return blocks.map((b) => {
      const part = document.createElement('canvas');
      part.width = canvas.width;
      part.height = Math.max(1, Math.round(b.height * scale));
      const ctx = part.getContext('2d');
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, part.width, part.height);
      ctx.drawImage(canvas, 0, Math.round(b.top * scale), canvas.width, part.height, 0, 0, part.width, part.height);
      return part.toDataURL('image/jpeg', 0.9);
    });
  }

  /**
   * Call straight from the click handler (before any await of your own): the
   * print tab must open inside the click or pop-up blockers stop it.
   * `body` = extra fields for the endpoint (e.g. the page's filters).
   * `capture: false` = the server builds the file itself (e.g. a table report),
   * so no page images are made or sent.
   */
  async function protectedPdf({ root, endpoint, forPrint = false, body = {}, capture = true }) {
    const tab = forPrint ? window.open('', '_blank') : null;
    if (tab) tab.document.write('<p style="font:14px system-ui,sans-serif;color:#64748b;padding:24px;">Preparing the PDF…</p>');
    toast('Preparing the PDF…', 'info', 3000);
    try {
      const images = capture ? await capturePdfSections(root) : undefined;
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': csrfToken(),
        },
        body: JSON.stringify({ ...body, purpose: forPrint ? 'print' : 'export', images }),
      });
      if (redirectIfSessionExpired(res)) { tab?.close(); return; }
      if (!res.ok || !(res.headers.get('Content-Type') || '').includes('application/pdf')) {
        tab?.close();
        const data = await res.json().catch(() => ({}));
        toast(data.message || 'Could not create the PDF. Please try again.', 'error');
        return;
      }
      const url = URL.createObjectURL(await res.blob());
      if (tab) {
        tab.location.href = url;
      } else {
        const a = document.createElement('a');
        a.href = url;
        a.download = (res.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/)?.[1] || 'export.pdf';
        document.body.appendChild(a);
        a.click();
        a.remove();
      }
      setTimeout(() => URL.revokeObjectURL(url), 5 * 60 * 1000);

      const devPw = res.headers.get('X-Dev-File-Password');
      const done = forPrint ? 'PDF opened in a new tab for printing.' : 'PDF downloaded.';
      toast(devPw
        ? `${done} Development mode: the password is ${devPw}`
        : `${done} The password to open it was sent to ${res.headers.get('X-File-Password-Sent-To') || 'your email'}.`,
        'success', devPw ? 15000 : 8000);
    } catch (err) {
      tab?.close();
      toast('Could not create the PDF. Please check your connection and try again.', 'error');
    }
  }

  return { toast, apiFetch, redirectIfSessionExpired, countdown, formatCountdown, openModal, closeModal, confirmDialog, confirmModal, csrfToken, escapeHtml, titleCase, renderPagination, showTableSkeleton, sentimentClass, sentimentBadge, locationCell, classificationEditor, bindClassificationEditor, detailView, dateCell, timeAgo, citizenCell, enableRowClick, protectedPdf };
})();
