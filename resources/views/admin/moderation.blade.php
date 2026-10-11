{{-- VIEW: Admin Moderation & Validation Queue
     Expects (set by Admin\PageController): $staff, $activeNav, $pageTitle --}}
@include('partials.admin-header')

<div class="card p-4 mb-4 flex flex-wrap gap-3">
  <select id="m-type" class="border border-borderc rounded px-3 py-2 text-sm">
    <option value="">All Types</option>
    @foreach (['Feedback','Complaint','Proposal'] as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
  </select>
  <select id="m-status" class="border border-borderc rounded px-3 py-2 text-sm">
    <option value="Pending">Pending</option>
    <option value="">All Status</option>
    <option value="Approved">Approved</option>
    <option value="Rejected">Rejected</option>
  </select>
</div>

<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-pagebg text-[11px] uppercase tracking-wide text-slate">
        <tr>
          <th class="text-left px-4 py-3 font-medium">ID</th>
          <th class="text-left px-4 py-3 font-medium">Type</th>
          <th class="text-left px-4 py-3 font-medium">Citizen</th>
          <th class="text-left px-4 py-3 font-medium">Content</th>
          <th class="text-left px-4 py-3 font-medium">Submitted</th>
          <th class="text-left px-4 py-3 font-medium">Status</th>
          <th class="text-right px-4 py-3 font-medium">Actions</th>
        </tr>
      </thead>
      <tbody id="moderation-tbody" class="divide-y divide-borderc"></tbody>
    </table>
  </div>
  <div id="moderation-empty" class="hidden table-empty">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg>
    <p class="table-empty-title">Moderation queue is clear.</p>
    <p class="table-empty-hint">New citizen submissions will show up here for review.</p>
  </div>
  <div id="moderation-pagination"></div>
</div>

<!-- Moderation Detail Modal -->
<div id="moderation-modal" class="hidden fixed inset-0 z-50 modal-backdrop flex items-center justify-center p-4">
  <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <div id="moderation-modal-body"></div>
  </div>
</div>

@include('partials.admin-footer')
<script>window.REJECT_REASONS = {!! json_encode(moderation_reject_reasons(), JSON_HEX_TAG | JSON_HEX_AMP) !!};</script>
<script src="{{ app_href('frontend/js/moderation.js') }}?v={!! time() !!}"></script>
