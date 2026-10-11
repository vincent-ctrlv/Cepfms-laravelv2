{{-- VIEW: Admin ID Verification queue
     Expects (set by Admin\PageController): $staff, $activeNav, $pageTitle, $rejectReasons --}}
@include('partials.admin-header')

<div class="card p-4 mb-4 flex flex-wrap items-center gap-3">
  <select id="v-status" class="border border-borderc rounded px-3 py-2 text-sm" aria-label="Filter by status">
    <option value="Pending">Waiting for review</option>
    <option value="Approved">Approved</option>
    <option value="Rejected">Rejected</option>
    <option value="">All</option>
  </select>
  <p class="text-xs text-slate">Approve only when the name matches, the address on the ID is in San Jose del Monte, and the selfie shows the same person holding the same ID.</p>
</div>

<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-pagebg text-[11px] uppercase tracking-wide text-slate">
        <tr>
          <th class="text-left px-4 py-3 font-medium">Name on ID</th>
          <th class="text-left px-4 py-3 font-medium">Google account</th>
          <th class="text-left px-4 py-3 font-medium">ID</th>
          <th class="text-left px-4 py-3 font-medium">Barangay</th>
          <th class="text-left px-4 py-3 font-medium">Sent</th>
          <th class="text-left px-4 py-3 font-medium">Status</th>
          <th class="text-right px-4 py-3 font-medium">Actions</th>
        </tr>
      </thead>
      <tbody id="verify-tbody" class="divide-y divide-borderc"></tbody>
    </table>
  </div>
  <div id="verify-empty" class="hidden table-empty">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="12" r="2.5"/><line x1="13" y1="10" x2="18" y2="10"/><line x1="13" y1="14" x2="17" y2="14"/></svg>
    <p class="table-empty-title">No IDs here.</p>
    <p class="table-empty-hint">Citizens' ID verifications show up here for review.</p>
  </div>
  <div id="verify-pagination"></div>
</div>

<div id="verify-modal" class="hidden fixed inset-0 z-50 modal-backdrop flex items-center justify-center p-4">
  <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
    <div id="verify-modal-body"></div>
  </div>
</div>

@include('partials.admin-footer')
<script>window.VERIFY_REJECT_REASONS = {!! json_encode($rejectReasons, JSON_HEX_TAG | JSON_HEX_AMP) !!};</script>
<script src="{{ asset_v('frontend/js/verifications.js') }}"></script>
