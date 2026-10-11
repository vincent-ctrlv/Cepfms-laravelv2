{{-- VIEW: Citizen Homepage
     Expects (set by CitizenController): $total, $resolutionRate, $avgDays, $recent --}}
@include('partials.header')
@include('partials.navbar')

<!-- ═══════════════════════════════════════════════════ HERO ══ -->
<section id="main-content" class="c-hero">
  <!-- City hall photo background -->
  <div class="c-hero-photo" style="background-image:url('{{ app_href('frontend/images/site/city-hall-hero.jpg') }}');"></div>
  <!-- Gradient overlay -->
  <div class="c-hero-overlay"></div>
  <!-- Ambient orbs -->
  <div class="c-hero-orb c-hero-orb-1"></div>
  <div class="c-hero-orb c-hero-orb-2"></div>

  <div class="c-hero-content">
    <!-- Eyebrow tag -->
    <div class="c-hero-eyebrow">
      <span class="c-hero-eyebrow-icon" aria-hidden="true">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
      </span>
      <span>Citizen Engagement Portal</span>
      <span class="c-hero-eyebrow-sep" aria-hidden="true"></span>
      <span class="c-hero-eyebrow-city">San Jose del Monte</span>
    </div>

    <!-- Headline -->
    <h1 class="c-hero-title">
      Your Voice,<br>
      <span class="c-gold-text">Simplified.</span>
    </h1>

    <!-- Subtext -->
    <p class="c-hero-sub">
      Report an issue, share feedback, or propose an idea for San Jose del Monte.
      No account needed — every submission gets a reference number you can track anytime.
    </p>

    <!-- CTAs -->
    <div class="c-hero-actions">
      <a href="{{ app_href('citizen/submit') }}" class="c-btn-primary" id="hero-submit-btn">
        Submit Feedback
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
      <a href="{{ app_href('citizen/track') }}" class="c-btn-ghost" id="hero-track-btn">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Track a Submission
      </a>
    </div>
  </div>
</section>


<!-- ══════════════════════════════════════════ SUBMISSION TYPES ══ -->
<!-- Each card opens the form with that type chosen; the citizen's choice wins over the AI's guess. -->
<section id="submission-types" class="c-section c-cards-bg c-photo-section">
  <div class="c-section-inner">
    <div class="c-section-header centered">
      <div class="c-section-tag">
        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Submit a Report
      </div>
      <h2 class="c-section-title">How can we help?</h2>
      <p class="c-section-desc">Choose the option that best describes what you'd like to share with us.</p>
    </div>

    <div class="c-cards-grid">
      <?php
      $types = [
        ['feedback', 'General Feedback', 'Share your thoughts, praise, or observations about local services.', 'rgba(37,99,235,0.10)',
         '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>'],
        ['complaint', 'Complaint / Issue', 'Report a problem in your barangay that needs attention.', '#fee2e2',
         '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'],
        ['proposal', 'Proposal / Idea', 'Suggest an improvement or new initiative for your barangay.', 'rgba(230,176,32,0.12)',
         '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#b8871a" stroke-width="1.8"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'],
      ];
      foreach ($types as $i => [$type, $title, $desc, $iconBg, $icon]): 
?>
      <a href="{{ app_href('citizen/submit') }}?type={!! $type !!}" class="c-type-card c-reveal{!! $i ? ' c-reveal-d' . $i : '' !!}" id="card-{!! $type !!}">
        <div class="c-icon-wrap" style="background:{!! $iconBg !!};" aria-hidden="true">{!! $icon !!}</div>
        <h3>{{ $title }}</h3>
        <p>{{ $desc }}</p>
        <span class="c-card-arrow">
          Get started
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </span>
      </a>
      @endforeach
    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════ HOW IT WORKS ══ -->
<section class="c-steps-section">
  <div class="c-section-inner">
    <div class="c-section-header centered">
      <div class="c-section-tag" style="background:rgba(230,176,32,0.10);border-color:rgba(230,176,32,0.22);">
        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Simple process
      </div>
      <h2 class="c-section-title">How it works</h2>
      <p class="c-section-desc">Four easy steps from submission to resolution.</p>
    </div>

    <div class="c-steps-grid">
      <div class="c-steps-connector" aria-hidden="true"></div>
      <?php
      $steps = [
        ['01', 'Submit',                'Describe your feedback, complaint, or proposal in your own words.'],
        ['02', 'Get a Reference Number','We generate a unique tracking code instantly — no account needed.'],
        ['03', 'We Review & Validate',  'Our team checks each submission before staff act on it.'],
        ['04', 'Track Progress',        'Check your reference number anytime to see the current status.'],
      ];
      foreach ($steps as $i => [$n, $title, $desc]): 
?>
      <div class="c-step c-reveal c-reveal-d{!! $i + 1 !!}">
        <div class="c-step-num">{!! $n !!}</div>
        <h3>{{ $title }}</h3>
        <p>{{ $desc }}</p>
      </div>
      @endforeach
    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════════════ FAQ ══ -->
<section class="c-section c-faq-section c-photo-section">
  <div class="c-section-inner">
    <div class="c-section-header centered">
      <div class="c-section-tag">
        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Help Center
      </div>
      <h2 class="c-section-title">Frequently Asked Questions</h2>
      <p class="c-section-desc">Everything you need to know about submitting and tracking feedback.</p>
    </div>

    <div class="c-faq-list">
      <?php
      $faqs = [
        ['Do I need to create an account to submit feedback?',
         'No. CEPFMS is designed for low-friction reporting — just fill out the form with your contact details and submit. You\'ll get a reference number to track your submission without ever needing to register or log in.'],
        ['Can I write in Filipino or Taglish?',
         'Yes. Write however feels natural — English, Filipino, Taglish, or a mix. Choosing a category and priority is optional too — if you\'re not sure, leave them and our system classifies your description automatically.'],
        ['Why isn\'t my submission showing up yet?',
         'Every new submission is briefly reviewed by our team before it\'s formally logged — this keeps spam and duplicate reports out of the system. Track your reference number anytime to see its current stage; it moves from "Submitted" to "Received" once that review is complete.'],
        ['What if I lose my reference number?',
         'Check the confirmation email sent to the address you provided when submitting. If you still can\'t find it, reach out to the ' . setting('brand_office') . ' directly with your name and approximate submission date.'],
        ['Is this for emergencies?',
         'No — CEPFMS is for non-urgent feedback, complaints, and proposals, and isn\'t monitored around the clock. For emergencies, please contact your barangay hall or local authorities directly.'],
      ];
      foreach ($faqs as $idx => [$q, $a]): 
?>
      <details class="c-faq-item c-reveal" id="faq-{!! $idx + 1 !!}">
        <summary>
          {{ $q }}
          <svg class="c-faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </summary>
        <p class="c-faq-body">{{ $a }}</p>
      </details>
      @endforeach
    </div>
  </div>
</section>


<!-- ══════════════════════════════════════ CONTACT INFO ══ -->
<section class="c-section c-contact-section c-photo-section">
  <div class="c-section-inner">
    <div class="c-section-header centered" style="margin-bottom:32px;">
      <div class="c-section-tag">
        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 014 11.91a19.79 19.79 0 01-3.07-8.67A2 2 0 012.93 1h3a2 2 0 012 1.72c.127.96.362 1.903.7 2.81a2 2 0 01-.45 2.11L7 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
        Get in Touch
      </div>
      <h2 class="c-section-title">Contact Information</h2>
    </div>
    <div class="c-contact-card c-reveal">
      <div class="c-contact-item">
        <div class="c-contact-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2a6ef5" stroke-width="1.8"><path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
        </div>
        <div>
          <span class="c-contact-label">Address</span>
          <span class="c-contact-value">{{ setting('brand_office') }},<br>{{ setting('contact_address') }}</span>
        </div>
      </div>
      <div class="c-contact-item">
        <div class="c-contact-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2a6ef5" stroke-width="1.8"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 014 11.91a19.79 19.79 0 01-3.07-8.67A2 2 0 012.93 1h3a2 2 0 012 1.72c.127.96.362 1.903.7 2.81a2 2 0 01-.45 2.11L7 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
        </div>
        <div>
          <span class="c-contact-label">Phone &amp; Hours</span>
          <span class="c-contact-value">{{ setting('contact_phone') }}<br>{{ setting('contact_hours') }}</span>
        </div>
      </div>
      <div class="c-contact-item">
        <div class="c-contact-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2a6ef5" stroke-width="1.8"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/></svg>
        </div>
        <div>
          <span class="c-contact-label">Email</span>
          <span class="c-contact-value">
            {{ setting('contact_email') }}<br>
            <span style="font-size:12px;color:#94a3b8;">Not monitored 24/7 — not for emergencies.</span>
          </span>
        </div>
      </div>
    </div>
  </div>
</section>




@include('partials.citizen-footer')

<script>
/* Scroll-reveal observer */
(function () {
  var els = document.querySelectorAll('.c-reveal');
  if (!window.IntersectionObserver) {
    els.forEach(function(el){ el.classList.add('visible'); });
    return;
  }
  var io = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); }
    });
  }, { threshold: 0.12 });
  els.forEach(function(el){ io.observe(el); });
})();
</script>
