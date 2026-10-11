{{-- VIEW: Citizen Submission Tracker
     Expects (set by CitizenController): $ref, $submission, $notFound, and the progress
     variables from SubmissionProgress::timeline() ($stageOrder, $stageDescriptions, $stageBadge,
     $currentStage, $currentIndex, $notAccepted, $rejectReason, $rejectHint, $rejectNote, $stageTimes) --}}
@include('partials.header')
@include('partials.navbar')

<main id="main-content" class="tr-page">
  <div class="sf-card">
    <header class="sf-head">
      <span class="sf-eyebrow">San Jose del Monte · Citizen Portal</span>
      <h1 class="sf-title">Track Your Submission</h1>
      <p class="sf-sub">Enter the reference number you received after submitting to see its current status.</p>
    </header>

    <form method="get">
      <label for="ref-input" class="sf-label">Reference number <span class="sf-req">*</span></label>
      <p id="ref-help" class="sf-help -mt-1 mb-2.5">It was shown on the confirmation page and emailed to you.</p>
      <div class="tr-search-row">
        <div class="relative flex-1 min-w-0">
          <svg class="tr-ref-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input id="ref-input" type="text" name="ref" value="{{ $ref }}" placeholder="e.g. FB-2847" required
            autocomplete="off" autocapitalize="characters" spellcheck="false" aria-describedby="ref-help" class="sf-input tr-ref-input">
          <button type="button" id="ref-clear-btn" class="hidden tr-ref-clear" aria-label="Clear reference number">&times;</button>
        </div>
        <button type="submit" class="sf-submit tr-search-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Track Submission
        </button>
      </div>
    </form>

    <div id="recent-refs-wrap" class="hidden mt-3">
      <div class="flex flex-wrap items-center gap-2">
        <span class="sf-help font-semibold">Recent lookups:</span>
        <div id="recent-refs-list" class="contents"></div>
      </div>
    </div>

  <div class="tr-results" aria-live="polite">
  @if ($notFound)
    <div class="text-center">
      <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3" style="background:#fee2e2;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      </div>
      <p class="font-medium text-navy mb-1">No submission found for "{{ $ref }}"</p>
      <p class="text-sm text-slate mb-5">Double-check the reference number for typos — it's not case-sensitive. If you just submitted, it may take a moment to appear.</p>
      <div class="flex flex-wrap justify-center gap-3">
        <a href="{{ app_href('citizen/submit') }}" class="sf-btn-primary inline-flex items-center justify-center">Submit New Feedback</a>
        <a href="{{ app_href('citizen') }}" class="sf-btn-ghost inline-flex items-center justify-center">Back to Home</a>
      </div>
    </div>
  @elseif ($submission)
    <div>
      <div class="flex items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3 min-w-0">
          <p class="text-xl font-mono font-semibold tr-accent">{{ $submission['reference_number'] }}</p>
          <button type="button" id="copy-ref-btn" data-ref="{{ $submission['reference_number'] }}"
            class="text-xs font-semibold tr-accent hover:underline inline-flex items-center gap-1.5">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
            Copy
          </button>
        </div>
        @if ($notAccepted)
          <span class="badge badge-red">Not accepted</span>
        @else
          <span class="badge {!! $stageBadge[$currentStage] ?? 'badge-slate' !!}">{{ $currentStage }}</span>
        @endif
      </div>

      @include('citizen.partials.progress')

      @if (!$notAccepted)
      @if (!empty($submission['responded_at']))
        <div class="flex gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 mb-4">
          <svg class="flex-shrink-0 mt-0.5" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <p class="text-sm text-navy">We sent you an official response on <span class="font-semibold">{!! date('M j, Y', strtotime($submission['responded_at'])) !!}</span>. Check your email, including the spam folder.</p>
        </div>
      @endif
      @endif

      <dl class="border-t border-borderc pt-4 mt-2 space-y-3 text-sm">
        <div class="flex justify-between gap-4"><dt class="text-slate flex-shrink-0">Type</dt><dd class="text-navy font-medium capitalize text-right">{{ $submission['submission_type'] }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-slate flex-shrink-0">Subject</dt><dd class="text-navy font-medium text-right break-words min-w-0">{{ $submission['subject'] }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-slate flex-shrink-0">Date Submitted</dt><dd class="text-navy font-medium text-right">{!! date('M j, Y', strtotime($submission['created_at'])) !!}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-slate flex-shrink-0">Last Update</dt><dd class="text-navy font-medium text-right" title="{{ date('M j, Y g:i A', strtotime($submission['updated_at'])) }}">{{ time_ago($submission['updated_at']) }}</dd></div>
      </dl>
    </div>
  @else
    <div>
      <p class="sf-group-title">How tracking works</p>
      <ol class="space-y-5">
        @foreach ($stageOrder as $i => $stage)
        <li class="flex gap-3">
          <span class="w-6 h-6 rounded-full bg-pagebg border border-borderc text-[11px] font-mono text-slate flex items-center justify-center flex-shrink-0 mt-0.5">{!! $i + 1 !!}</span>
          <div>
            <p class="text-sm font-medium text-navy">{{ $stage }}</p>
            <p class="text-xs text-slate">{{ $stageDescriptions[$stage] ?? '' }}</p>
          </div>
        </li>
        @endforeach
      </ol>
    </div>
  @endif
  </div>
  </div>
</main>

<script>window.CURRENT_REF = {!! $submission ? json_encode($submission['reference_number']) : 'null' !!};</script>
@include('partials.citizen-footer')
<script src="{{ asset_v('frontend/js/track.js') }}"></script>
