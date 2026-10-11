{{-- VIEW: Citizen identity verification
     Expects (set by CitizenAccountController): $citizen, $verification (latest, or null),
     $status ('none' | 'Pending' | 'Approved' | 'Rejected'), $idTypes --}}
@extends('layouts.citizen-portal')

@section('content')
<header class="cp-head">
  <div>
    <p class="cp-sub">Signed in as <strong>{{ $citizen['email'] }}</strong>. We check one government ID per person so every report comes from a real resident of San Jose del Monte.</p>
  </div>
</header>

  <div class="cp-card">

    @if ($status === 'Approved')
      <div class="ca-status ca-status-approved">
        <span class="ca-status-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
        <div>
          <h2>You're verified</h2>
          <p>Your ID was approved. You can now send feedback, complaints, and proposals.</p>
          <div class="ca-status-actions"><a href="{{ app_href('citizen/submit') }}" class="sf-btn-primary">Submit feedback</a></div>
        </div>
      </div>

    @elseif ($status === 'Pending')
      <div class="ca-status ca-status-pending">
        <span class="ca-status-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
        <div>
          <h2>We're checking your ID</h2>
          <p>Sent {{ date('M j, Y g:i A', strtotime($verification['created_at'])) }} · {{ $verification['id_type'] }} ending in {{ $verification['id_number_last4'] }}.</p>
          <p>An admin reviews IDs during office hours ({{ setting('contact_hours') }}). We'll email {{ $citizen['email'] }} as soon as it's done.</p>
        </div>
      </div>

    @else
      @if ($status === 'Rejected')
        <div class="ca-status ca-status-rejected">
          <span class="ca-status-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span>
          <div>
            <h2>We couldn't verify your last ID</h2>
            <p>{{ $verification['reject_reason'] ?: 'No reason was given.' }}</p>
            <p>Please send it again below.</p>
          </div>
        </div>
      @endif

      <form id="verify-form" enctype="multipart/form-data" novalidate>
        <fieldset class="sf-group">
          <legend class="sf-group-title">Your ID</legend>
          <div class="sf-field">
            <label class="sf-label" for="full_name">Full name, exactly as on your ID <span class="sf-req">*</span></label>
            <input id="full_name" name="full_name" type="text" required maxlength="150" autocomplete="name" class="sf-input"
              value="{{ $verification['full_name'] ?? $citizen['full_name'] }}">
          </div>
          <div class="ca-grid">
            <div class="sf-field">
              <label class="sf-label" for="id_type">Type of ID <span class="sf-req">*</span></label>
              <select id="id_type" name="id_type" required class="sf-input">
                <option value="">Choose your ID…</option>
                @foreach ($idTypes as $t)
                  <option value="{{ $t }}" @selected(($verification['id_type'] ?? '') === $t)>{{ $t }}</option>
                @endforeach
              </select>
            </div>
            <div class="sf-field">
              <label class="sf-label" for="id_number">ID number <span class="sf-req">*</span></label>
              <input id="id_number" name="id_number" type="text" required maxlength="40" autocomplete="off" spellcheck="false" class="sf-input" placeholder="As printed on the ID">
              <p class="sf-help mt-1.5">We only keep the last 4 characters.</p>
            </div>
          </div>
          <div class="sf-field">
            <label class="sf-label" for="barangay">Barangay where you live <span class="sf-req">*</span></label>
            <input id="barangay" name="barangay" type="text" list="barangay-list" required autocomplete="off" class="sf-input"
              placeholder="Type your barangay…" value="{{ $verification['barangay'] ?? '' }}">
            <datalist id="barangay-list">
              @foreach (SJDM_BARANGAYS as $b)
                <option value="{{ $b }}"></option>
              @endforeach
            </datalist>
            <p class="sf-help mt-1.5">It must match the San Jose del Monte address on your ID.</p>
          </div>
        </fieldset>

        <fieldset class="sf-group">
          <legend class="sf-group-title">Photos</legend>
          <div class="ca-grid">
            <div class="sf-field">
              <label class="ca-photo" for="id_photo" data-photo>
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="12" r="2.5"/><line x1="13" y1="10" x2="18" y2="10"/><line x1="13" y1="14" x2="17" y2="14"/></svg>
                <span class="ca-photo-title">Front of your ID</span>
                <span class="ca-photo-hint">Whole card in frame, flat, no glare. Name, photo, and address readable.</span>
                <span class="ca-photo-change">Change photo</span>
                <input id="id_photo" name="id_photo" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" required>
              </label>
            </div>
            <div class="sf-field">
              <label class="ca-photo" for="selfie_photo" data-photo>
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1"/></svg>
                <span class="ca-photo-title">Selfie holding your ID</span>
                <span class="ca-photo-hint">Your face and the ID both clearly visible, in good light.</span>
                <span class="ca-photo-change">Change photo</span>
                <input id="selfie_photo" name="selfie_photo" type="file" accept="image/jpeg,image/png,image/webp" capture="user" required>
              </label>
            </div>
          </div>
          <p class="sf-help">JPG, PNG, or WEBP · up to 5MB each. Only authorized admins can see these photos.</p>
        </fieldset>

        <div class="sf-consent">
          <label class="flex items-start gap-3 cursor-pointer">
            <input id="consent" name="consent" type="checkbox" required value="1" class="sf-check">
            <span class="text-[13px] text-slate leading-relaxed">I confirm this is my own ID, and I agree to it being processed to verify my identity as described in the
              <a href="{{ app_href('citizen/privacy') }}" target="_blank" rel="noopener" class="font-semibold text-teal hover:underline">Privacy Policy</a>.
              <span class="sf-req">*</span>
            </span>
          </label>
        </div>

        <p id="verify-error" class="sf-error hidden" role="alert"></p>
        <button type="submit" id="verify-btn" class="sf-submit">Send for verification</button>
      </form>
    @endif
  </div>
@endsection

@push('scripts')
<script src="{{ asset_v('frontend/js/verify.js') }}"></script>
@endpush
