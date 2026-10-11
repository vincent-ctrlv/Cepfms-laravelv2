{{-- VIEW: Privacy Policy --}}
@include('partials.header')
@include('partials.navbar')

<main id="main-content" class="max-w-3xl mx-auto px-4 sm:px-6 py-12">
  <h1 class="text-2xl font-semibold text-navy mb-1">Privacy Policy</h1>
  <p class="text-xs text-slate mb-8">Last updated: {!! date('F j, Y') !!}</p>

  <div class="card p-6 mb-6 text-sm text-slate leading-relaxed">
    <p>This Privacy Policy explains what personal information the Citizen Engagement and Public Feedback
    Management System (CEPFMS) of the City Government of San Jose del Monte — Legislative Services Office
    collects when you use this portal, why we collect it, and the rights you have over it under the
    <strong class="text-navy">Data Privacy Act of 2012 (Republic Act No. 10173)</strong> and its implementing
    rules and regulations.</p>
  </div>

  <div class="space-y-8 text-sm text-slate leading-relaxed">

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">1. Information We Collect</h2>
      <p class="mb-2">To submit, you sign in with Google and verify your identity once. For this we collect:</p>
      <ul class="list-disc pl-5 space-y-1 mb-3">
        <li>From your Google account: your name, email address, and Google account ID. We do not receive your
          Google password, contacts, or any other Google data.</li>
        <li>For identity verification: your full name as printed on your ID, the type of ID, your barangay of
          residence, a photo of the front of your ID, and a selfie of you holding it. We do <strong>not</strong>
          store your full ID number — only its last 4 characters, plus a one-way code made from it so we can tell
          if the same ID is used on more than one account.</li>
      </ul>
      <p class="mb-2">When you submit feedback, a complaint, or a proposal, we also collect:</p>
      <ul class="list-disc pl-5 space-y-1">
        <li>Your mobile number (required). Your name and email come from your verified account.</li>
        <li>The subject and description of your submission, and the photos you attach (at least one is required).</li>
        <li>Location details you provide or share, such as city, barangay, street, or GPS coordinates
          (only if you place a pin on the map).</li>
        <li>Technical information collected automatically, such as your IP address and submission
          timestamp, used for spam prevention and rate-limiting.</li>
      </ul>
      <p class="mt-2">If you log in as staff, we additionally process your work email, hashed password,
        and account role to manage access to the administrative side of the system.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">2. How We Use Your Information</h2>
      <ul class="list-disc pl-5 space-y-1">
        <li>To confirm that each submission comes from a real resident of San Jose del Monte. Your ID photos are
          used only for this check.</li>
        <li>To review, classify, and act on your submission.</li>
        <li>To update you on the status of your submission and respond to it.</li>
        <li>To detect and prevent spam, duplicate, or abusive submissions.</li>
        <li>To generate anonymized, aggregate statistics (e.g. how many complaints a barangay received)
          for public transparency reporting and internal planning. These reports do not identify you.</li>
      </ul>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">3. Legal Basis for Processing</h2>
      <p>We process your personal information because it is necessary to perform a public service function —
        receiving and acting on citizen feedback — and because you consent to this Policy when you submit a
        report through this portal. Where you provide precise location data, this is done only with your
        explicit action (placing a pin on the map) and is never accessed automatically.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">4. Who We Share It With</h2>
      <p>Your submission and contact details are shared only with the Legislative Services Office and, where
        needed to act on your report, the city office responsible for it (for example, the engineering office
        for a road complaint). We do not sell, rent, or share your personal information with private third
        parties for marketing purposes.</p>
      <p class="mt-2">Your ID photos are never shared outside the Legislative Services Office. They can be viewed
        only by authorized administrators while checking your identity.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">5. Data Retention</h2>
      <p>We retain submission records for as long as needed to act on them and to meet the government's
        records-retention obligations, after which they are archived or disposed of in accordance with
        applicable records-management rules for local government units.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">6. Data Security</h2>
      <p>We apply reasonable organizational and technical safeguards to protect your information, including
        restricted staff access by role, encrypted password storage, and protections against
        common web attacks. ID photos are kept in storage that is not reachable from the web and are shown only
        to signed-in administrators. No online system can guarantee absolute security, but we take reasonable steps to
        protect your data against loss, misuse, or unauthorized access.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">7. Your Rights as a Data Subject</h2>
      <p class="mb-2">Under the Data Privacy Act, you have the right to:</p>
      <ul class="list-disc pl-5 space-y-1">
        <li>Be informed that your personal data will be, are being, or have been processed.</li>
        <li>Access your personal data that we hold.</li>
        <li>Request correction of inaccurate or outdated personal data.</li>
        <li>Object to or withdraw consent for further processing, where applicable.</li>
        <li>Request the erasure or blocking of your personal data under certain conditions.</li>
        <li>Be indemnified for damages sustained due to inaccurate, incomplete, or unlawfully obtained
          personal data.</li>
        <li>Lodge a complaint with the National Privacy Commission.</li>
      </ul>
      <p class="mt-2">To exercise any of these rights, contact the Legislative Services Office using the
        details below.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">8. Cookies</h2>
      <p>This portal uses a single strictly-necessary session cookie to keep you (or staff) signed in and to
        protect forms against forgery. We do not use advertising or third-party tracking cookies.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">9. Changes to This Policy</h2>
      <p>We may update this Privacy Policy from time to time. The "Last updated" date at the top of this page
        reflects the most recent revision.</p>
    </section>

    <section>
      <h2 class="text-base font-semibold text-navy mb-2">10. Contact Us</h2>
      <p>For questions about this Policy or to exercise your data privacy rights, contact:</p>
      <p class="mt-2">{{ setting('brand_office') }}<br>
        {{ setting('contact_address') }}<br>
        {{ setting('contact_hours') }} · {{ setting('contact_phone') }}<br>
        {{ setting('contact_email') }}</p>
    </section>

  </div>
</main>

@include('partials.citizen-footer')
