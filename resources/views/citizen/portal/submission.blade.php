{{-- VIEW: Citizen portal — one of the citizen's own submissions
     Expects (set by CitizenAccountController::submission): $submission, $photos, $replies,
     and the progress variables from SubmissionProgress::timeline() --}}
@extends('layouts.citizen-portal')

@section('content')
@php
  $typeLabels = ['feedback' => 'Feedback', 'complaint' => 'Complaint', 'proposal' => 'Proposal'];
  $location = implode(', ', array_filter([$submission['street'], $submission['barangay'], $submission['city']]));
@endphp

@if (request()->boolean('submitted'))
  <section class="cp-banner cp-banner-ok" role="status">
    <div>
      <h2>Thank you — your report was sent</h2>
      <p>Your reference number is <strong class="cp-mono">{{ $submission['reference_number'] }}</strong>. We've emailed it to you too. Our team reviews every report first; you can follow its progress right here.</p>
    </div>
  </section>
@endif

<a href="{{ app_href('citizen/my-submissions') }}" class="cp-back">← My Submissions</a>

<header class="cp-head">
  <div class="min-w-0">
    <p class="cp-eyebrow cp-mono">{{ $submission['reference_number'] }} · {{ $typeLabels[$submission['submission_type']] ?? 'Feedback' }}</p>
    <h2 class="cp-title cp-break">{{ $submission['subject'] }}</h2>
    <p class="cp-sub">Sent {{ date('F j, Y · g:i A', strtotime($submission['created_at'])) }} · last update {{ time_ago($submission['updated_at']) }}</p>
  </div>
  @if ($notAccepted)
    <span class="badge badge-red">Not accepted</span>
  @else
    <span class="badge {{ $stageBadge[$currentStage] ?? 'badge-slate' }}">{{ $currentStage }}</span>
  @endif
</header>

<div class="cp-grid cp-grid-detail">
  <div class="cp-stack">
    <section class="cp-card">
      <div class="cp-card-head"><h2>Your report</h2></div>
      <p class="cp-body">{!! nl2br(e($submission['description'])) !!}</p>
      <dl class="cp-facts">
        @if ($submission['category'])<div><dt>Category</dt><dd>{{ $submission['category'] }}</dd></div>@endif
        <div><dt>Location</dt><dd>{{ $location ?: '—' }}</dd></div>
      </dl>
      @if ($photos)
        <div class="cp-photos">
          @foreach ($photos as $i => $path)
            <a href="{{ app_href($path) }}" target="_blank" rel="noopener"><img src="{{ app_href($path) }}" alt="Photo {{ $i + 1 }} you attached" loading="lazy"></a>
          @endforeach
        </div>
      @endif
    </section>

    <section class="cp-card" id="replies">
      <div class="cp-card-head"><h2>Replies from the office</h2></div>
      @if (!$replies)
        <p class="cp-muted">{{ $notAccepted ? 'This report was not accepted, so there are no replies.' : "No replies yet. When the office responds, you'll see it here and in your email." }}</p>
      @else
        <ol class="cp-thread">
          @foreach ($replies as $r)
            <li class="cp-reply">
              <div class="cp-reply-head">
                <strong>{{ setting('brand_office') }}</strong>
                <time datetime="{{ date('c', strtotime($r['sent_at'])) }}">{{ date('M j, Y · g:i A', strtotime($r['sent_at'])) }}</time>
              </div>
              <p class="cp-reply-subject">{{ $r['subject'] }}</p>
              <p class="cp-body">{!! nl2br(e($r['body'])) !!}</p>
            </li>
          @endforeach
        </ol>
      @endif
    </section>
  </div>

  <section class="cp-card cp-progress-card">
    <div class="cp-card-head"><h2>Progress</h2></div>
    @include('citizen.partials.progress')
  </section>
</div>
@endsection
