{{-- VIEW: Admin Engagement Analytics
     Expects (set by Admin\PageController): $staff, $activeNav, $pageTitle --}}
@include('partials.admin-header')

<div id="analytics-page-head" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
  <div>
    <h1 class="text-xl font-semibold text-navy">Engagement Analytics</h1>
    <p class="text-sm text-slate mt-1">Citizen participation, submission trends, and service performance.</p>
  </div>
  <div class="no-print" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
    <button id="analytics-export-btn" type="button" disabled title="Downloads the numbers as a table report (PDF); the password to open it is sent to your email"
      style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#475569;
             background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:8px 14px;cursor:pointer;
             transition:background .15s,color .15s;white-space:nowrap;"
      onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
      Export PDF
    </button>
    <button id="analytics-print-btn" type="button" disabled title="Opens the page with its charts as a PDF to print; the password is sent to your email"
      style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#475569;
             background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:8px 14px;cursor:pointer;
             transition:background .15s,color .15s;white-space:nowrap;"
      onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
        <rect x="6" y="14" width="12" height="8"/>
      </svg>
      Print
    </button>
  </div>
</div>

<div id="analytics-root">
  <div style="display:flex;align-items:center;justify-content:center;padding:60px 0;">
    <p style="font-size:13px;color:#94a3b8;">Loading analytics…</p>
  </div>
</div>

<!-- The page itself never prints (Ctrl+P / browser menu) — that would make an
     unprotected copy. Printing goes through the password-protected PDF instead. -->
<div id="analytics-print-notice" style="display:none;padding:40px;text-align:center;">
  <p style="font-size:16px;font-weight:600;color:#0f172a;margin:0 0 6px;">Printing this page is disabled</p>
  <p style="font-size:12px;color:#64748b;margin:0;">Use the Print button on the Engagement Analytics page. It opens a password-protected PDF; the password is sent to your email.</p>
</div>
<style>
  @media print {
    #analytics-page-head, #analytics-root { display: none !important; }
    #analytics-print-notice { display: block !important; }
  }
</style>

@include('partials.admin-footer')
<script src="{{ app_href('frontend/js/analytics.js') }}?v={!! time() !!}"></script>
