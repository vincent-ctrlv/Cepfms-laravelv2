{{-- VIEW: Citizen portal — all of the citizen's submissions
     Expects (set by CitizenAccountController::mySubmissions): $citizen, $filters, $filter,
     $submissions, $total, $page, $totalPages --}}
@extends('layouts.citizen-portal')

@section('content')
@php
  $typeLabels = ['feedback' => 'Feedback', 'complaint' => 'Complaint', 'proposal' => 'Proposal'];
  $link = fn (string $f, int $p = 1) => app_href('citizen/my-submissions') . '?' . http_build_query(array_filter(['filter' => $f, 'page' => $p > 1 ? $p : null]));
@endphp

<p class="cp-sub cp-intro">Everything you've sent from {{ $citizen['email'] }}. Open one to see its progress and the office's replies.</p>

<nav class="cp-tabs" aria-label="Filter submissions">
  @foreach ($filters as $key => $label)
    <a href="{{ $link($key) }}" class="cp-tab{{ $filter === $key ? ' is-active' : '' }}" @if ($filter === $key) aria-current="page" @endif>{{ $label }}</a>
  @endforeach
</nav>

<section class="cp-card">
  @if (!$submissions)
    <div class="cp-empty">
      <p>{{ $filter === '' ? "You haven't sent anything yet." : 'Nothing here right now.' }}</p>
      @if ($filter === '' && \App\Support\CitizenAuth::isVerified())
        <a href="{{ app_href('citizen/submit') }}" class="cp-btn cp-btn-primary">Send your first report</a>
      @endif
    </div>
  @else
    <ul class="cp-list">
      @foreach ($submissions as $s)
        @php [$label, $badge, $pct] = \App\Support\SubmissionProgress::summary($s); @endphp
        <li>
          <a class="cp-row" href="{{ app_href('citizen/submissions/' . $s['reference_number']) }}">
            <span class="min-w-0 cp-row-main">
              <span class="cp-row-meta">{{ $s['reference_number'] }} · {{ $typeLabels[$s['submission_type']] ?? 'Feedback' }} · {{ date('M j, Y', strtotime($s['created_at'])) }}</span>
              <span class="cp-row-title">{{ $s['subject'] }}</span>
              @if ($label !== 'Not accepted')
                <span class="cp-progress" role="progressbar" aria-label="Progress" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $pct }}%"></span></span>
              @endif
            </span>
            <span class="badge {{ $badge }} flex-shrink-0">{{ $label }}</span>
          </a>
        </li>
      @endforeach
    </ul>

    @if ($totalPages > 1)
      <nav class="cp-pager" aria-label="Pages">
        @if ($page > 1)<a href="{{ $link($filter, $page - 1) }}">← Newer</a>@else<span></span>@endif
        <span>Page {{ $page }} of {{ $totalPages }} · {{ $total }} total</span>
        @if ($page < $totalPages)<a href="{{ $link($filter, $page + 1) }}">Older →</a>@else<span></span>@endif
      </nav>
    @endif
  @endif
</section>
@endsection
