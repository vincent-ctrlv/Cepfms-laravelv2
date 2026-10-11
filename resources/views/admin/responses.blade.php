{{-- VIEW: Admin Response Composer — Split-panel UI
     Expects (set by Admin\PageController): $staff, $activeNav, $pageTitle --}}
@include('partials.admin-header')

<style>
/* ---- Split-panel response composer ------------------------------------ */
.resp-panel {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 0;
  border: 1px solid var(--borderc, #e2e8f0);
  border-radius: 12px;
  overflow: hidden;
  background: #fff;
  height: calc(100vh - 135px);
  min-height: 600px;
  box-shadow: 0 1px 3px rgba(15, 30, 61, 0.04);
}

@media (max-width: 960px) {
  .resp-panel {
    grid-template-columns: 1fr;
    height: auto;
    min-height: 0;
  }
  .resp-list-col {
    max-height: 460px;
    border-right: none !important;
    border-bottom: 1px solid var(--borderc, #e2e8f0);
  }
}

/* LEFT — submission list */
.resp-list-col {
  border-right: 1px solid var(--borderc, #e2e8f0);
  display: flex;
  flex-direction: column;
  background: #fafaf9;
  height: 100%;
  overflow: hidden;
}

.resp-list-header {
  padding: 14px 16px 12px;
  border-bottom: 1px solid var(--borderc, #e2e8f0);
  background: #fff;
  flex-shrink: 0;
}

.resp-header-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.resp-header-title-row p {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: #64748b;
  margin: 0;
}

.resp-search-wrap {
  position: relative;
  margin-bottom: 10px;
}

.resp-search-icon {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
}

.resp-search {
  width: 100%;
  border: 1px solid var(--borderc, #e2e8f0);
  border-radius: 7px;
  padding: 7px 28px 7px 30px;
  font-size: 12px;
  background: #f8fafc;
  outline: none;
  transition: all .15s ease;
  box-sizing: border-box;
}

.resp-search:focus {
  background: #fff;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.resp-search-clear {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #94a3b8;
  font-size: 16px;
  line-height: 1;
  cursor: pointer;
  padding: 2px;
}
.resp-search-clear:hover { color: #475569; }

/* Filter Tabs */
.resp-filter-tabs {
  display: flex;
  gap: 6px;
  background: #f1f5f9;
  padding: 3px;
  border-radius: 8px;
}

.resp-tab-btn {
  flex: 1;
  font-size: 11px;
  font-weight: 600;
  padding: 5px 8px;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  transition: all .15s ease;
}

.resp-tab-btn:hover {
  color: #0f1e3d;
}

.resp-tab-btn.active {
  background: #fff;
  color: #0f1e3d;
  box-shadow: 0 1px 2px rgba(0,0,0,0.06);
}

.resp-tab-badge {
  font-size: 10px;
  padding: 1px 5px;
  border-radius: 10px;
  background: #e2e8f0;
  color: #475569;
  font-weight: 700;
}

.resp-tab-btn.active .resp-tab-badge {
  background: #f1f5f9;
}

.resp-tab-badge-warn {
  background: #fef3c7 !important;
  color: #b45309 !important;
}

/* Scrollable body */
.resp-list-body {
  flex: 1;
  overflow-y: auto;
}

.resp-list-body::-webkit-scrollbar,
.resp-detail-col::-webkit-scrollbar {
  width: 6px;
}
.resp-list-body::-webkit-scrollbar-thumb,
.resp-detail-col::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}
.resp-list-body::-webkit-scrollbar-track,
.resp-detail-col::-webkit-scrollbar-track {
  background: transparent;
}

.resp-sub-item {
  padding: 11px 14px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  transition: all .12s ease;
  background: #fff;
}

.resp-sub-item:hover {
  background: #f8fafc;
}
.resp-sub-item:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: -2px;
}
.resp-sub-item .sub-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  margin-top: 5px;
  font-size: 11px;
  color: #94a3b8;
}

.resp-sub-item.active {
  background: #eff6ff;
  border-left: 3px solid #2563eb;
  padding-left: 11px;
}

.resp-sub-item .title {
  font-size: 13px;
  font-weight: 600;
  color: #0f1e3d;
  margin: 0 0 5px;
  line-height: 1.35;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.resp-sub-item .meta-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: #64748b;
}

.resp-sub-item .ref-badge {
  font-family: 'Roboto Mono', monospace;
  font-size: 10px;
  font-weight: 600;
  background: #f1f5f9;
  color: #475569;
  padding: 1px 5px;
  border-radius: 4px;
  flex-shrink: 0;
}

.resp-sub-item .citizen-label {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  min-width: 0;
}

.resp-sub-item .resp-badge {
  font-size: 10px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 10px;
  flex-shrink: 0;
}

.resp-badge-needs {
  background: #fef3c7;
  color: #92400e;
}

.resp-badge-done {
  background: #e0f2fe;
  color: #0369a1;
}

.resp-list-empty,
.resp-list-loading {
  padding: 40px 16px;
  font-size: 12px;
  color: #94a3b8;
  text-align: center;
}

/* Pagination Footer */
.resp-list-footer {
  padding: 9px 14px;
  border-top: 1px solid var(--borderc, #e2e8f0);
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  font-size: 11px;
  color: #64748b;
}

.resp-page-nav {
  display: flex;
  align-items: center;
  gap: 4px;
}

.resp-page-btn {
  border: 1px solid var(--borderc, #e2e8f0);
  background: #fff;
  border-radius: 5px;
  width: 26px;
  height: 26px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #475569;
  transition: all .12s;
}

.resp-page-btn:hover:not(:disabled) {
  background: #f8fafc;
  border-color: #cbd5e1;
  color: #0f1e3d;
}

.resp-page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* RIGHT — detail + composer */
.resp-detail-col {
  display: flex;
  flex-direction: column;
  padding: 24px 28px;
  overflow-y: auto;
  height: 100%;
  box-sizing: border-box;
}

.resp-detail-placeholder {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #94a3af;
  font-size: 13px;
  gap: 8px;
}
.resp-detail-placeholder svg { opacity: .35; }

.resp-ref-num  { font-size: 11px; color: #64748b; letter-spacing: .05em; margin: 0 0 4px; }
.resp-title    { font-size: 22px; font-weight: 700; color: #0f1e3d; margin: 0 0 6px; line-height: 1.25; }
.resp-desc {
  font-size: 13px; color: #334155; margin: 0 0 20px; line-height: 1.6;
  white-space: pre-line; background: #f8fafc; border-left: 3px solid #cbd5e1;
  border-radius: 0 8px 8px 0; padding: 12px 14px;
}
.resp-meta-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 0 0 14px; font-size: 11px; color: #64748b; }
.resp-section-label {
  font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
  color: #64748b; margin: 0 0 8px;
}

/* Previous replies thread */
.resp-thread { display: flex; flex-direction: column; gap: 8px; margin: 0 0 18px; }
.resp-thread-item { border: 1px solid #dbeafe; background: #f5f9ff; border-radius: 8px; padding: 10px 12px; }
.resp-thread-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 6px; font-size: 11px; color: #64748b; margin-bottom: 4px; }
.resp-thread-head strong { color: #0f1e3d; font-weight: 600; }
.resp-thread-body { font-size: 12.5px; color: #334155; line-height: 1.55; white-space: pre-line; }
.resp-thread-empty { font-size: 12px; color: #94a3b8; margin: 0 0 18px; }

.resp-divider  { border: none; border-top: 1px solid var(--borderc, #e2e8f0); margin: 0 0 16px; }

/* Quick templates */
.resp-templates-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: #64748b;
  margin: 0 0 8px;
}
.resp-templates {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 14px;
}
.resp-tpl-btn {
  font-size: 12px;
  padding: 5px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 20px;
  background: #fff;
  color: #374151;
  cursor: pointer;
  transition: background .12s, border-color .12s;
}
.resp-tpl-btn:hover { background: #f1f5f9; border-color: #94a3b8; }

/* Textarea */
.resp-textarea {
  width: 100%;
  border: 1px solid var(--borderc, #e2e8f0);
  border-radius: 8px;
  padding: 12px 14px;
  font-size: 13px;
  resize: vertical;
  outline: none;
  transition: border-color .15s;
  min-height: 120px;
  box-sizing: border-box;
}
.resp-textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); }

/* Status change that goes with a status template */
.resp-status-note {
  display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px;
  margin: 8px 0 0; padding: 8px 12px; border-radius: 8px;
  background: #eff6ff; border: 1px solid #bfdbfe; font-size: 12px; color: #1e3a8a;
}
.resp-status-note.hidden { display: none; }
.resp-status-note svg { color: #2563eb; flex-shrink: 0; }
.resp-status-note span { flex: 1; min-width: 200px; }
.resp-status-note button {
  font-size: 11px; font-weight: 600; color: #2563eb; background: none; border: none;
  padding: 2px 0; cursor: pointer;
}
.resp-status-note button:hover { text-decoration: underline; }

/* Notify row */
.resp-notify-row {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12px;
  color: #374151;
  margin: 10px 0;
}
.resp-notify-row input[type="checkbox"] { accent-color: #1e3a5f; width: 14px; height: 14px; }

/* Send button */
.resp-send-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #2563eb;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  padding: 9px 18px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  box-shadow: 0 4px 12px -4px rgba(37, 99, 235, 0.55);
  transition: background .15s, transform .1s;
}
.resp-send-btn:hover   { background: #1d4ed8; }
.resp-send-footer { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-top: 10px; }
.resp-send-footer .resp-notify-row { margin: 0; }
.resp-char-count { font-size: 11px; color: #94a3b8; }.resp-send-btn:active  { transform: scale(.98); }
.resp-send-btn:disabled { opacity: .5; cursor: not-allowed; }

/* Composer visible state */
#resp-detail-placeholder.hidden { display: none; }
#resp-composer.hidden { display: none; }
#resp-composer:not(.hidden) {
  display: flex;
  flex-direction: column;
  flex: 1;
}

/* Citizen meta card */
.resp-citizen-box {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 24px;
  background: #f8fafc;
  border: 1px solid var(--borderc, #e2e8f0);
  border-radius: 8px;
  padding: 10px 14px;
  margin: 0 0 16px;
}
.resp-citizen-field {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
}
.resp-citizen-field svg {
  color: #2563eb;
  flex-shrink: 0;
}
.resp-field-label {
  display: block;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #64748b;
}
.resp-field-value {
  font-weight: 600;
  color: #0f1e3d;
}
</style>

<!-- Response Composer Panel -->
<div class="resp-panel">

  <!-- LEFT: submission list -->
  <div class="resp-list-col">
    <div class="resp-list-header">
      <div class="resp-header-title-row">
        <p>Select a Submission</p>
      </div>

      <div class="resp-search-wrap">
        <svg class="resp-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input id="resp-search" type="text" class="resp-search" value="{{ request()->query()['search'] ?? '' }}" placeholder="Search title, ref #, citizen…">
        <button id="resp-search-clear" class="resp-search-clear{!! empty(request()->query()['search']) ? ' hidden' : '' !!}" type="button" title="Clear search">&times;</button>
      </div>

      <!-- Quick Filter Tabs -->
      <div class="resp-filter-tabs">
        <button type="button" class="resp-tab-btn active" data-filter="all" id="tab-filter-all">
          All <span class="resp-tab-badge" id="badge-count-all">0</span>
        </button>
        <button type="button" class="resp-tab-btn" data-filter="needs_response" id="tab-filter-needs">
          Needs Reply <span class="resp-tab-badge resp-tab-badge-warn" id="badge-count-needs">0</span>
        </button>
        <button type="button" class="resp-tab-btn" data-filter="responded" id="tab-filter-responded">
          Responded
        </button>
      </div>
    </div>

    <!-- Scrollable list of submissions -->
    <div class="resp-list-body" id="resp-list-body">
      <div class="resp-list-loading" id="resp-list-loading">Loading…</div>
    </div>

    <!-- Pagination Footer -->
    <div class="resp-list-footer">
      <span id="resp-page-info">Showing 0–0 of 0</span>
      <div class="resp-page-nav">
        <button type="button" id="resp-prev-btn" class="resp-page-btn" disabled title="Previous page">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <span id="resp-page-current" class="font-semibold px-1">1 / 1</span>
        <button type="button" id="resp-next-btn" class="resp-page-btn" disabled title="Next page">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    </div>
  </div>

  <!-- RIGHT: detail + composer -->
  <div class="resp-detail-col" id="resp-detail-col">
    <div class="resp-detail-placeholder" id="resp-detail-placeholder">
      <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z"/>
      </svg>
      Select a submission from the left to compose a response.
    </div>

    <div id="resp-composer" class="hidden">
      <p class="resp-ref-num font-mono" id="resp-d-ref"></p>
      <h2 class="resp-title" id="resp-d-title"></h2>
      <div class="resp-meta-chips" id="resp-d-meta"></div>

      <!-- Submitter info: Name & Email -->
      <div class="resp-citizen-box">
        <div class="resp-citizen-field">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <div>
            <span class="resp-field-label">Citizen Name</span>
            <span class="resp-field-value" id="resp-d-name">—</span>
          </div>
        </div>
        <div class="resp-citizen-field">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <div>
            <span class="resp-field-label">Email / Gmail</span>
            <span class="resp-field-value" id="resp-d-email">—</span>
          </div>
        </div>
      </div>

      <p class="resp-section-label">Citizen's message</p>
      <p class="resp-desc" id="resp-d-desc"></p>

      <p class="resp-section-label">Previous replies</p>
      <div id="resp-thread"><p class="resp-thread-empty">Loading…</p></div>
      <hr class="resp-divider">

      <p class="resp-templates-label">Quick Templates</p>
      <div class="resp-templates">
        <button type="button" class="resp-tpl-btn" data-tpl="Acknowledge receipt">Acknowledge receipt</button>
        <button type="button" class="resp-tpl-btn" data-tpl="Request more info">Request more info</button>
        <button type="button" class="resp-tpl-btn" data-tpl="Received">Received</button>
        <button type="button" class="resp-tpl-btn" data-tpl="In Review">In Review</button>
        <button type="button" class="resp-tpl-btn" data-tpl="Assigned">Assigned</button>
        <button type="button" class="resp-tpl-btn" data-tpl="Resolved">Resolved</button>
      </div>

      <textarea id="resp-body" class="resp-textarea" placeholder="Write your official response…" rows="5"></textarea>
      <p id="resp-status-note" class="resp-status-note hidden">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        <span>Sending this will also change the status from <strong id="resp-status-from"></strong> to <strong id="resp-status-to"></strong>.</span>
        <button type="button" id="resp-status-keep">Keep current status</button>
      </p>

      <div class="resp-send-footer">
        <div class="resp-notify-row">
          <input type="checkbox" id="resp-notify" checked>
          <label for="resp-notify">Notify citizen by email</label>
          <span class="resp-char-count" id="resp-char-count"></span>
        </div>
        <button id="resp-send-btn" type="button" class="resp-send-btn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          <span id="resp-send-label">Send &amp; notify citizen</span>
        </button>
      </div>
    </div>
  </div>

</div>

@include('partials.admin-footer')
<script>window.EMAIL_SIGNOFF = {!! json_encode(setting('email_signoff'), JSON_HEX_TAG | JSON_HEX_AMP) !!};</script>
<script src="{{ asset_v('frontend/js/responses.js') }}"></script>


