{{-- VIEW: Citizen portal dashboard
     Expects (set by CitizenAccountController::dashboard): $citizen, $status, $verification,
     $counts (total, reviewing, in_progress, resolved, not_accepted), $recent, $replies --}}
@extends('layouts.citizen-portal')

@section('content')
@php
  $firstName = explode(' ', trim($citizen['full_name']))[0] ?? '';
  $hour = (int) date('G');
  $greeting = $hour < 12 ? 'Magandang umaga' : ($hour < 18 ? 'Magandang hapon' : 'Magandang gabi');
  $typeLabels = ['feedback' => 'Feedback', 'complaint' => 'Complaint', 'proposal' => 'Proposal'];
  $statCards = [
      ['total',       'Total sent',         '',             'cp-tone-blue',  '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>'],
      ['reviewing',   'Waiting for review', 'reviewing',    'cp-tone-slate', '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
      ['in_progress', 'In progress',        'in_progress',  'cp-tone-amber', '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>'],
      ['resolved',    'Resolved',           'resolved',     'cp-tone-green', '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
  ];
@endphp

<section class="cp-welcome">
  <h2 class="cp-title">{{ $greeting }}, {{ $firstName }}!</h2>
  <p class="cp-sub">Here's what's happening with your reports to the {{ setting('brand_office') }}.</p>
</section>

{{-- Verification: until approved, it's the one thing to do --}}
@if ($status !== 'Approved')
  <section class="cp-banner {{ $status === 'Pending' ? 'cp-banner-wait' : 'cp-banner-todo' }}">
    <div>
      @if ($status === 'Pending')
        <h2>Your ID is being reviewed</h2>
        <p>Sent {{ date('M j, Y', strtotime($verification['created_at'])) }}. We'll email {{ $citizen['email'] }} once an admin has checked it — then you can start submitting.</p>
      @elseif ($status === 'Rejected')
        <h2>We couldn't verify your ID</h2>
        <p>{{ $verification['reject_reason'] ?: 'Please send a clearer photo of your ID.' }}</p>
      @else
        <h2>Verify your ID to start submitting</h2>
        <p>One time only: a photo of a government ID with your San Jose del Monte address, and a selfie holding it.</p>
      @endif
    </div>
    @if ($status !== 'Pending')
      <a href="{{ app_href('citizen/verify') }}" class="cp-btn cp-btn-primary">{{ $status === 'Rejected' ? 'Send again' : 'Verify my ID' }}</a>
    @endif
  </section>
@endif

{{-- Counts --}}
<section class="cp-stats" aria-label="Your submissions at a glance">
  @foreach ($statCards as [$key, $label, $filter, $tone, $paths])
    <a class="cp-stat" href="{{ app_href('citizen/my-submissions') }}{{ $filter ? '?filter=' . $filter : '' }}">
      <span class="cp-stat-icon {{ $tone }}" aria-hidden="true">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $paths !!}</svg>
      </span>
      <span class="cp-stat-text">
        <span class="cp-stat-value">{{ $counts[$key] }}</span>
        <span class="cp-stat-label">{{ $label }}</span>
      </span>
    </a>
  @endforeach
</section>

{{-- Recent submissions --}}
<section class="cp-card">
  <div class="cp-card-head">
    <h2>Recent submissions</h2>
    @if ($counts['total'] > 0)<a href="{{ app_href('citizen/my-submissions') }}">View all</a>@endif
  </div>
  @if (!$recent)
    <div class="cp-empty">
      <p>You haven't sent anything yet.</p>
      @if ($status === 'Approved')<a href="{{ app_href('citizen/submit') }}" class="cp-btn cp-btn-primary">Send your first report</a>@endif
    </div>
  @else
    <ul class="cp-list">
      @foreach ($recent as $s)
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
  @endif
</section>

<div class="cp-grid cp-grid-even">
  {{-- Latest official replies --}}
  <section class="cp-card">
    <div class="cp-card-head"><h2>Replies from the office</h2></div>
    @if (!$replies)
      <p class="cp-muted">When the office replies to one of your reports, you'll see it here and in your email.</p>
    @else
      <ul class="cp-replies">
        @foreach ($replies as $r)
          <li>
            <a href="{{ app_href('citizen/submissions/' . $r['reference_number']) }}#replies">
              <span class="cp-row-meta">{{ $r['reference_number'] }} · {{ date('M j, Y', strtotime($r['sent_at'])) }}</span>
              <span class="cp-row-title">{{ $r['subject'] }}</span>
              <span class="cp-reply-excerpt">{{ \Illuminate\Support\Str::limit($r['body'], 120) }}</span>
            </a>
          </li>
        @endforeach
      </ul>
    @endif
  </section>

  {{-- Help --}}
  <section class="cp-card">
    <div class="cp-card-head"><h2>Need help?</h2></div>
    <ul class="cp-help">
      <li><span>Office</span>{{ setting('brand_office') }}, {{ setting('contact_address') }}</li>
      <li><span>Hours</span>{{ setting('contact_hours') }}</li>
      <li><span>Phone</span>{{ setting('contact_phone') }}</li>
      <li><span>Email</span><a href="mailto:{{ setting('contact_email') }}">{{ setting('contact_email') }}</a></li>
    </ul>
    <p class="cp-muted cp-mt">For emergencies, contact your barangay hall directly.</p>
  </section>
</div>
@endsection
