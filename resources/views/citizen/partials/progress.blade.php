{{-- Progress for one submission: the "not accepted" notice, or the 5-stage timeline.
     Expects the variables from App\Support\SubmissionProgress::timeline(). Used by the
     public Track page and the citizen portal's submission page. --}}
@if ($notAccepted)
  <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3.5 mb-6">
    <p class="text-sm font-semibold text-red-800 mb-1">This submission wasn't accepted</p>
    @if ($rejectReason)
      <p class="text-xs text-red-700 leading-relaxed"><span class="font-semibold">Reason: {{ $rejectReason }}</span>{!! $rejectHint ? ' — ' . e($rejectHint) : '' !!}</p>
      @if ($rejectNote)
        <p class="text-xs text-red-700 leading-relaxed mt-1.5"><span class="font-semibold">Note from the office:</span> {{ $rejectNote }}</p>
      @endif
      <p class="text-xs text-red-700 leading-relaxed mt-1.5">See the <a href="{{ app_href('citizen/terms') }}" class="underline">Terms</a>. You're welcome to send a new report with more details.</p>
    @else
    <p class="text-xs text-red-700 leading-relaxed">After review, it couldn't be processed — for example, it was a duplicate, outside San Jose del Monte, or didn't follow the <a href="{{ app_href('citizen/terms') }}" class="underline">Terms</a>. You're welcome to send a new report with more details.</p>
    @endif
    <a href="{{ app_href('citizen/submit') }}" class="sf-btn-primary inline-flex items-center justify-center mt-3">Submit new feedback</a>
  </div>
@else
<!-- Vertical progress timeline: finished steps get a check, the current step a ring and highlight. -->
<ol class="mb-6" aria-label="Progress">
  <?php $lastIndex = count($stageOrder) - 1; ?>
  @foreach ($stageOrder as $i => $stage) @php $done = $i < $currentIndex; $current = $i === $currentIndex; @endphp
  <li class="relative flex gap-3.5 {!! $i < $lastIndex ? 'pb-4' : '' !!}" {!! $current ? 'aria-current="step"' : '' !!}>
    @if ($i < $lastIndex)
      <span class="absolute left-[13px] top-7 bottom-0 w-0.5" style="background: {!! $i < $currentIndex ? '#059669' : '#e2e8f0' !!};" aria-hidden="true"></span>
    @endif
    <span class="tracker-dot flex-shrink-0 {!! $current ? ($stage === 'Resolved' ? 'tracker-dot-current' : 'tracker-dot-current is-live') : '' !!}" style="background: {!! ($done || $current) ? '#059669' : '#e2e8f0' !!}; color: {!! ($done || $current) ? '#fff' : '#475569' !!};">
      @if ($done || ($current && $stage === 'Resolved'))
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
      @elseif ($current)
        <span class="w-2 h-2 rounded-full bg-white" aria-hidden="true"></span>
      @else
        <span class="text-[10px] font-mono">{!! $i + 1 !!}</span>
      @endif
    </span>
    <div class="flex-1 min-w-0 {!! $current ? 'rounded-lg border border-emerald/30 bg-emerald/5 px-3 py-2 -mt-1' : 'pt-1' !!}">
      <p class="text-sm {!! $current ? 'text-navy font-semibold' : ($done ? 'text-navy font-medium' : 'text-slate') !!}">
        {{ $stage }}
        @if ($current && $stage !== 'Resolved')<span class="ml-1.5 align-middle text-[10px] font-semibold uppercase tracking-wider text-emerald">Current</span>@endif
      </p>
      <p class="text-xs {!! $current ? 'text-navy/80' : 'text-slate' !!} mt-0.5">{{ $stageDescriptions[$stage] ?? '' }}</p>
      @if (($done || $current) && !empty($stageTimes[$stage])) @php $at = strtotime($stageTimes[$stage]); @endphp
        <p class="tr-stage-time"><time datetime="{{ date('c', $at) }}">{{ date('M j, Y · g:i A', $at) }}</time></p>
      @endif
    </div>
  </li>
  @endforeach
</ol>
@endif
