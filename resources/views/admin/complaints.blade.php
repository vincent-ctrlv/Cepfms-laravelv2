{{-- VIEW: Admin Complaints & Issues Table
     Expects (set by Admin\PageController): $staff, $activeNav, $pageTitle --}}
@include('partials.admin-header')

<div class="card p-3 mb-4 flex flex-wrap items-center gap-2.5">
  <input type="text" id="c-search" value="{{ request()->query()['search'] ?? '' }}" placeholder="Search complaints..." class="flex-1 min-w-[200px] border border-borderc rounded px-3 py-2 text-sm">
  <select id="c-priority" class="border border-borderc rounded px-3 py-2 text-sm">
    <option value="">All Priority</option>
    @foreach (['Critical','High','Medium','Low'] as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
  </select>
</div>

<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-pagebg text-[11px] uppercase tracking-wide text-slate">
        <tr>
          <th class="text-left px-4 py-3 font-medium">Complaint ID</th>
          <th class="text-left px-4 py-3 font-medium">Citizen</th>
          <th class="text-left px-4 py-3 font-medium">Subject</th>
          <th class="text-left px-4 py-3 font-medium">Category</th>
          <th class="text-left px-4 py-3 font-medium">Priority</th>
          <th class="text-left px-4 py-3 font-medium">Sentiment</th>
          <th class="text-left px-4 py-3 font-medium">Status</th>
          <th class="text-left px-4 py-3 font-medium">Date</th>
          <th class="text-right px-4 py-3 font-medium">Actions</th>
        </tr>
      </thead>
      <tbody id="complaints-tbody" class="divide-y divide-borderc"></tbody>
    </table>
  </div>
  <div id="complaints-empty" class="hidden table-empty">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg>
    <p class="table-empty-title">No complaints currently require attention.</p>
    <p class="table-empty-hint">New complaints will appear here once they pass moderation.</p>
  </div>
  <div id="complaints-pagination"></div>
</div>

<div id="complaint-modal" class="hidden fixed inset-0 z-50 modal-backdrop flex items-center justify-center p-4">
  <div class="bg-white rounded max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <div id="complaint-modal-body"></div>
  </div>
</div>

@include('partials.admin-footer')
<script src="{{ app_href('frontend/js/complaints.js') }}?v={!! time() !!}"></script>
