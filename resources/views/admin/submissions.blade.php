{{-- VIEW: Admin Feedback Submission Table
     Expects (set by Admin\PageController): $categories, $staff, $activeNav, $pageTitle --}}
@include('partials.admin-header')

<div class="card p-4 mb-4 space-y-3">
  <div class="flex flex-wrap gap-3">
    <input type="text" id="f-search" value="{{ request()->query()['search'] ?? '' }}" placeholder="Search by subject, citizen, or reference..." class="flex-1 min-w-[200px] border border-borderc rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal">
    <button type="button" id="export-csv-btn" class="border border-borderc rounded px-3 py-2 text-sm text-navy hover:bg-pagebg focus:outline-none focus:ring-2 focus:ring-teal/30">
      Export CSV
    </button>
  </div>
  <div class="flex flex-wrap items-center gap-3">
    <select id="f-type" class="border border-borderc rounded px-3 py-2 text-sm">
      <option value="">All Types</option>
      @foreach (['proposal' => 'Proposals & Suggestions', 'complaint' => 'Complaints & Issues', 'feedback' => 'General Feedback'] as $tv => $tl)<option value="{{ $tv }}">{{ $tl }}</option>@endforeach
    </select>
    <select id="f-category" class="border border-borderc rounded px-3 py-2 text-sm">
      <option value="">All Categories</option>
      @foreach ($categories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
    </select>
    <select id="f-status" class="border border-borderc rounded px-3 py-2 text-sm">
      <option value="">All Status</option>
      <option value="Unresolved">Unresolved (not yet Resolved)</option>
      @foreach (['Pending','Received','In Review','Assigned','Escalated','Resolved'] as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
    </select>
    <select id="f-priority" class="border border-borderc rounded px-3 py-2 text-sm">
      <option value="">All Priority</option>
      @foreach (['Critical','High','Medium','Low'] as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
    </select>
    <select id="f-sentiment" class="border border-borderc rounded px-3 py-2 text-sm">
      <option value="">All Sentiment</option>
      @foreach (['Positive','Neutral','Negative'] as $sm)<option value="{{ $sm }}">{{ $sm }}</option>@endforeach
    </select>
    <div class="flex items-center gap-2">
      <label for="f-date-from" class="text-xs text-slate">From</label>
      <input type="date" id="f-date-from" class="border border-borderc rounded px-3 py-2 text-sm">
      <label for="f-date-to" class="text-xs text-slate">To</label>
      <input type="date" id="f-date-to" class="border border-borderc rounded px-3 py-2 text-sm">
    </div>
    <button type="button" id="clear-filters-btn" class="text-sm text-teal hover:underline focus:outline-none focus:ring-2 focus:ring-teal/30 rounded px-1">
      Clear filters
    </button>
  </div>
</div>

<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-pagebg text-[11px] uppercase tracking-wide text-slate">
        <tr>
          <th class="text-left px-4 py-3 font-medium">ID</th>
          <th class="text-left px-4 py-3 font-medium">Citizen</th>
          <th class="text-left px-4 py-3 font-medium">Subject</th>
          <th class="text-left px-4 py-3 font-medium">Category</th>
          <th class="text-left px-4 py-3 font-medium">Status</th>
          <th class="text-left px-4 py-3 font-medium">Priority</th>
          <th class="text-left px-4 py-3 font-medium">Sentiment</th>
          <th class="text-left px-4 py-3 font-medium">Date</th>
          <th class="text-right px-4 py-3 font-medium">Actions</th>
        </tr>
      </thead>
      <tbody id="submissions-tbody" class="divide-y divide-borderc"></tbody>
    </table>
  </div>
  <div id="submissions-empty" class="hidden table-empty">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg>
    <p class="table-empty-title">No submissions found.</p>
    <p class="table-empty-hint">Try a different search, or clear the filters.</p>
  </div>
  <div id="submissions-pagination"></div>
</div>

<!-- Detail modal -->
<div id="submission-modal" class="hidden fixed inset-0 z-50 modal-backdrop flex items-center justify-center p-4">
  <div class="bg-white rounded max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <div id="submission-modal-body"></div>
  </div>
</div>

@include('partials.admin-footer')
<script src="{{ app_href('frontend/js/submissions.js') }}?v={!! time() !!}"></script>
