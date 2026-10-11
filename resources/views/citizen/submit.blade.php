{{-- VIEW: Citizen Submission Form
     Expects (set by CitizenController): $citizen (signed in and verified), $prefType, $categories, $priorities --}}
@extends('layouts.citizen-portal')

@section('content')
  <div class="cp-card cp-form-card">
    <header class="sf-head">
      <p class="sf-sub">Feedback, a complaint, or a proposal — write in English, Filipino, or Taglish. Our team will review it and follow up.</p>
      @if ($prefType)
        <?php $typeLabels = ['feedback' => 'General Feedback', 'complaint' => 'Complaint / Issue', 'proposal' => 'Proposal / Idea']; ?>
        <p class="sf-type">
          Sending as <strong>{{ $typeLabels[$prefType] }}</strong>
          <a href="{{ app_href('citizen/submit') }}">Change</a>
        </p>
      @endif
    </header>

    <div id="draft-banner-wrap"></div>

    <form id="submission-form" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="preferred_type" value="{{ $prefType }}">

      <!-- Your feedback -->
      <fieldset class="sf-group">
        <legend class="sf-group-title">Your feedback</legend>
        <div class="sf-field">
          <label class="sf-label" for="subject">Subject <span class="sf-req">*</span></label>
          <input id="subject" name="subject" type="text" required maxlength="200" class="sf-input"
            placeholder="e.g. Missed garbage collection on Sampaguita St.">
          <p id="subject-error" class="sf-error hidden" role="alert"></p>
        </div>
        <div class="sf-field">
          <label class="sf-label" for="description">Description <span class="sf-req">*</span></label>
          <textarea id="description" name="description" rows="5" required minlength="10"
            aria-describedby="description-help description-counter" class="sf-input"
            placeholder="Ilarawan ang iyong feedback, reklamo, o mungkahi…"></textarea>
          <div class="flex items-start justify-between gap-2 mt-1.5">
            <p id="description-help" class="sf-help">Say what happened, where, and since when.</p>
            <p id="description-counter" class="sf-help flex-shrink-0" aria-live="polite">0 characters</p>
          </div>
          <p id="description-error" class="sf-error hidden" role="alert"></p>
        </div>
        @if ($prefType)
        <!-- Only for citizens who came from a "How can we help?" card. Optional:
             left on "let us decide", the system classifies it automatically. -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
          <div class="sf-field">
            <label class="sf-label" for="category">Category</label>
            <select id="category" name="category" class="sf-input">
              <option value="">Not sure — let us decide</option>
              @foreach ($categories as $c)
                <option value="{{ $c }}">{{ $c }}</option>
              @endforeach
            </select>
          </div>
          <div class="sf-field">
            <label class="sf-label" for="priority">Priority</label>
            <select id="priority" name="priority" class="sf-input">
              <option value="">Not sure — let us decide</option>
              @foreach ($priorities as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
        </div>
        @endif
        <!-- Location: a summary field; the barangay/street/map live in the dialog below -->
        <div class="sf-field">
          <p class="sf-label" id="location-label">Location <span class="sf-req">*</span></p>
          <button type="button" id="location-trigger" class="sf-input sf-loc-trigger"
            aria-haspopup="dialog" aria-controls="location-modal" aria-labelledby="location-label location-summary">
            <span class="sf-loc-icon" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a7 7 0 00-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 00-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
            </span>
            <span class="min-w-0 flex-1">
              <span id="location-summary" class="sf-loc-text is-empty">Tap to select location</span>
              <span id="location-sub" class="sf-loc-sub hidden"></span>
            </span>
            <svg class="sf-loc-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
          <p id="location-error" class="sf-error hidden" role="alert"></p>
          <p class="sf-help mt-1.5">We accept feedback for San Jose del Monte, Bulacan only.</p>
        </div>
      </fieldset>

      <!-- Select Location dialog (stays inside the form so its fields are submitted) -->
      <div id="location-modal" class="sf-modal hidden" role="dialog" aria-modal="true" aria-labelledby="location-modal-title">
        <div class="sf-modal-panel" tabindex="-1">
          <div class="sf-modal-head">
            <h2 id="location-modal-title" class="sf-modal-title">Select Location</h2>
            <button type="button" id="location-close" class="sf-modal-x" aria-label="Close">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>
            </button>
          </div>

          <div class="sf-modal-body">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
              <div class="sf-field">
                <label for="barangay" class="sf-label">Barangay <span class="sf-req">*</span></label>
                <input name="barangay" id="barangay" type="text" list="barangay-list" required autocomplete="off" placeholder="Type your barangay…" class="sf-input">
                <datalist id="barangay-list">
                  @foreach (SJDM_BARANGAYS as $b)
                    <option value="{{ $b }}"></option>
                  @endforeach
                </datalist>
                <p id="barangay-error" class="sf-error hidden" role="alert"></p>
              </div>
              <div class="sf-field">
                <label for="street" class="sf-label">Street / Landmark</label>
                <input id="street" name="street" type="text" class="sf-input" placeholder="e.g. near the barangay hall">
              </div>
            </div>
            <div class="sf-field mb-0">
              <div class="flex items-center justify-between gap-2 mb-2">
                <p class="sf-label sf-label-inline">Pin on map <span class="sf-opt">(Optional)</span></p>
                <button type="button" id="clear-pin-btn" class="hidden text-xs font-semibold text-teal hover:underline">Remove pin</button>
              </div>
              <div id="location-map" class="sf-map isolate"
                data-boundary="{{ json_encode(sjdm_boundary()) }}"
                aria-label="Map of San Jose del Monte. Tap inside the city outline to place a pin."></div>
              <p id="location-map-hint" class="sf-help mt-1.5" aria-live="polite">Tap inside the outlined area to mark the exact spot. You can drag the pin to adjust it.</p>
            </div>
          </div>

          <div class="sf-modal-foot">
            <button type="button" id="location-cancel" class="sf-btn-ghost">Cancel</button>
            <button type="button" id="location-save" class="sf-btn-primary">Save Location</button>
          </div>
        </div>
      </div>
      <input type="hidden" name="latitude" id="latitude">
      <input type="hidden" name="longitude" id="longitude">

      <!-- Photos -->
      <fieldset class="sf-group">
        <legend class="sf-group-title">Evidence photos <span class="sf-req">*</span></legend>
        <div id="dropzone" class="dropzone sf-dropzone cursor-pointer" role="button" tabindex="0" aria-label="Upload photos, drag and drop or click to browse">
          <!-- Empty-state prompt (hidden once images are added) -->
          <div id="dropzone-prompt" class="sf-drop-prompt">
            <span class="sf-drop-icon" aria-hidden="true">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 16l-4-4-4 4"/><path d="M12 12v9"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/></svg>
            </span>
            <p class="text-sm font-semibold text-navy">
              <span class="sf-touch-only">Tap to upload photos</span>
              <span class="sf-mouse-only">Click or drag photos here to upload</span>
            </p>
            <p class="sf-help">1–5 images · JPG, PNG, WEBP, HEIC · max 5MB each</p>
            <button type="button" id="camera-btn" class="sf-camera-btn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
              Take a photo
            </button>
          </div>
          <!-- Preview thumbnails (shown inside the dropzone once files are selected) -->
          <div id="photo-previews" class="grid grid-cols-3 sm:grid-cols-5 gap-3 p-3 hidden"></div>
          <!-- Add-more button (shown when there are previews but slots remain) -->
          <div id="dropzone-add-more" class="hidden border-t border-dashed border-borderc py-2.5 text-center text-xs font-semibold text-slate hover:text-navy transition">
            + Add more photos
          </div>
          <input type="file" id="photo-input" name="photos[]" accept="image/*" multiple class="hidden" aria-label="Photo attachments">
          <input type="file" id="camera-input" accept="image/*" capture="environment" class="hidden" aria-label="Take a photo">
        </div>
        <p id="photos-error" class="sf-error hidden" role="alert"></p>
      </fieldset>

      <!-- Contact: name and email come from the verified account -->
      <fieldset class="sf-group">
        <legend class="sf-group-title">Your details</legend>
        <div class="sf-account">
          <span class="sf-account-badge" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          </span>
          <span class="min-w-0">
            <span class="sf-account-name">{{ $citizen['full_name'] }}</span>
            <span class="sf-account-email">{{ $citizen['email'] }} · Verified</span>
          </span>
        </div>
        <div class="sf-field">
          <label class="sf-label" for="phone">Mobile number <span class="sf-req">*</span></label>
          <input id="phone" name="phone" type="tel" required inputmode="tel" autocomplete="tel" class="sf-input" placeholder="09XX XXX XXXX" value="{{ $citizen['phone'] ?? '' }}">
          <p id="phone-error" class="sf-error hidden" role="alert"></p>
        </div>
        <p class="sf-note">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/></svg>
          We'll email your reference number and progress updates.
        </p>
      </fieldset>

      <div class="sf-consent">
        <label class="flex items-start gap-3 cursor-pointer">
          <input id="consent" name="consent" type="checkbox" required value="1" class="sf-check">
          <span class="text-[13px] text-slate leading-relaxed">I agree to the
            <a href="{{ app_href('citizen/privacy') }}" target="_blank" rel="noopener" class="font-semibold text-teal hover:underline">Privacy Policy</a>
            and
            <a href="{{ app_href('citizen/terms') }}" target="_blank" rel="noopener" class="font-semibold text-teal hover:underline">Terms of Service</a>,
            and consent to my information being processed to review and act on this feedback.
            <span class="sf-req">*</span>
          </span>
        </label>
        <p id="consent-error" class="sf-error hidden ml-8" role="alert"></p>
      </div>

      <!-- Hidden Bot Trap / Honeypot -->
      <div style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;" aria-hidden="true">
        <label for="website_url">Leave empty</label>
        <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
      </div>
      <input type="hidden" name="form_loaded_at" id="form_loaded_at" value="{!! time() !!}">

      <button type="submit" id="submit-btn" class="sf-submit">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Submit Feedback
      </button>
    </form>
  </div>
@endsection

@push('scripts')
<!-- Open the address-lookup connection early so the first map tap fills in faster -->
<link rel="preconnect" href="https://nominatim.openstreetmap.org" crossorigin>
<link rel="stylesheet" href="{{ app_href('frontend/lib/leaflet/leaflet.css') }}">
<script src="{{ app_href('frontend/lib/leaflet/leaflet.js') }}"></script>
<script src="{{ app_href('frontend/js/citizen.js') }}?v={!! time() !!}"></script>
@endpush
