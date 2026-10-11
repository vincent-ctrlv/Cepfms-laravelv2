{{-- VIEW: Terms of Service --}}
@include('partials.header')
@include('partials.navbar')

<main id="main-content" class="max-w-3xl mx-auto px-4 sm:px-6 py-12">
  <h1 class="text-2xl font-semibold text-navy mb-1">Terms of Service</h1>
  <p class="text-xs text-slate mb-8">Last updated: {!! date('F j, Y') !!}</p>

  <div class="card p-6 mb-6 text-sm text-slate leading-relaxed">
    <p>These Terms of Service govern your use of the Citizen Engagement and Public Feedback Management
    System (CEPFMS), operated by the Legislative Services Office of the City Government of San Jose del
    Monte. By submitting feedback, a complaint, or a proposal through this portal, you agree to these Terms
    and to our <a href="{{ app_href('citizen/privacy') }}" class="text-teal hover:underline">Privacy Policy</a>.</p>
  </div>

  <div class="space-y-8 text-sm text-slate leading-relaxed">

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">1. Purpose of This Portal</h2>
      <p>CEPFMS lets residents submit feedback, complaints, and proposals to the city government and track
        their status. It is a channel for civic engagement and is not a substitute for emergency services.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">2. Not for Emergencies</h2>
      <p>Do not use this portal to report medical, fire, crime, or other life-threatening emergencies. For
        urgent emergencies, contact your barangay hall or the appropriate emergency hotline directly.
        Submissions made here are reviewed during regular office hours and are not monitored in real time.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">3. Accuracy of Information</h2>
      <p>You agree to provide truthful, accurate information when submitting a report and to use your real
        name and a working email address. Knowingly submitting false, misleading, or fabricated reports may
        result in your submission being rejected and, where applicable, may be referred to the appropriate
        office for further action.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">4. Prohibited Conduct</h2>
      <p class="mb-2">When using this portal, you agree not to:</p>
      <ul class="list-disc pl-5 space-y-1">
        <li>Submit spam, advertising, or content unrelated to city services.</li>
        <li>Submit content that is defamatory, obscene, harassing, or that threatens harm to any person.</li>
        <li>Attempt to interfere with, disrupt, or gain unauthorized access to this system.</li>
        <li>Impersonate another person or misrepresent your affiliation with any person or organization.</li>
      </ul>
      <p class="mt-2">We reserve the right to reject, remove, or flag submissions that violate these Terms.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">5. No Guaranteed Resolution Timeline</h2>
      <p>We aim to review and act on submissions promptly, but resolution times vary depending on the nature
        and complexity of the report. Submitting a report does not guarantee a specific
        outcome or timeframe for resolution.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">6. Intellectual Property</h2>
      <p>The portal's design, branding, and software are the property of the City Government of San Jose del
        Monte or its licensors. Content you submit remains yours, but you grant the city government the right
        to use, store, and process it for the purpose of reviewing and acting on your report, and for
        anonymized, aggregate public reporting.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">7. Limitation of Liability</h2>
      <p>This portal is provided on an "as is" and "as available" basis. To the extent permitted by law, the
        City Government of San Jose del Monte is not liable for any indirect or incidental damages arising
        from your use of, or inability to use, this portal.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">8. Governing Law</h2>
      <p>These Terms are governed by the laws of the Republic of the Philippines, including the Data Privacy
        Act of 2012 (RA 10173) and other applicable local government regulations.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">9. Changes to These Terms</h2>
      <p>We may update these Terms from time to time. Continued use of this portal after changes are posted
        constitutes acceptance of the revised Terms.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">10. Contact Us</h2>
      <p>{{ setting('brand_office') }}<br>
        {{ setting('contact_address') }}<br>
        {{ setting('contact_hours') }} · {{ setting('contact_phone') }}<br>
        {{ setting('contact_email') }}</p>
    </section>

  </div>
</main>

@include('partials.citizen-footer')
