{{-- VIEW: Submission Success Page
     Expects (set by CitizenController): $submission --}}
@include('partials.header')
@include('partials.navbar')

<main id="main-content" class="max-w-xl mx-auto px-4 sm:px-6 py-16 text-center">
  <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6" style="background:rgba(13,122,110,0.1)">
    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
  </div>
  <h1 class="text-xl font-semibold text-navy mb-2">Thank you — your submission was received</h1>
  <p class="text-sm text-slate mb-8">Please save your reference number below. You'll need it to track the status of your submission. We've also sent a copy to your email.</p>

  <div class="card p-6 mb-8">
    <p class="text-[11px] text-slate uppercase tracking-wider mb-1">Reference Number</p>
    <p class="text-3xl font-mono font-semibold tr-accent mb-2">{{ $submission['reference_number'] }}</p>
    <button type="button" id="copy-ref-btn" data-ref="{{ $submission['reference_number'] }}"
      class="text-xs font-semibold tr-accent hover:underline inline-flex items-center gap-1.5 mb-4">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
      Copy reference number
    </button>
    <div class="border-t border-borderc pt-4 text-left text-sm">
      <p class="text-slate mb-1">Subject</p>
      <p class="text-navy font-medium mb-3">{{ $submission['subject'] }}</p>
      <p class="text-slate mb-1">Submitted</p>
      <p class="text-navy font-medium">{!! date('F j, Y g:i A', strtotime($submission['created_at'])) !!}</p>
    </div>
  </div>

  <div class="card p-5 mb-8 text-left">
    <p class="text-xs font-semibold text-navy mb-2">What happens next?</p>
    <ol class="text-xs text-slate space-y-1.5 list-decimal list-inside">
      <li>Our team reviews your submission to confirm it's ready to be routed (usually within one business day).</li>
      <li>Once cleared, its status updates from "Submitted" to "Received" and our staff start working on it.</li>
      <li>Check back anytime with your reference number to see the latest status.</li>
    </ol>
  </div>

  <div class="flex flex-wrap justify-center gap-3">
    <a href="{{ app_href('citizen/track') }}?ref={!! urlencode($submission['reference_number']) !!}" class="sf-btn-primary inline-flex items-center justify-center">Track This Submission</a>
    <a href="{{ app_href('citizen') }}" class="sf-btn-ghost inline-flex items-center justify-center">Back to Home</a>
  </div>
</main>

@include('partials.citizen-footer')
<script src="{{ app_href('frontend/js/success.js') }}"></script>
