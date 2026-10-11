<footer class="c-footer">
  <div class="c-footer-inner">
    <div class="c-footer-brand">
      <div class="c-brand">
        <div class="c-brand-seal">
          <img src="{{ brand_logo_url() }}" alt="{{ setting('brand_government') }} logo">
        </div>
        <div class="c-brand-text">
          <span class="c-brand-name">{{ setting('brand_short_name') }}</span>
          <span class="c-brand-sub">{{ setting('brand_office') }}</span>
        </div>
      </div>
      <p class="c-footer-brand-desc">The official {{ str_replace(' & ', ' and ', mb_strtolower(setting('brand_full_name'))) }} for the {{ setting('brand_government') }}.</p>
    </div>

    <div class="c-footer-col">
      <h4>Quick Links</h4>
      <ul>
        <li><a href="{{ app_href('citizen') }}">Home</a></li>
        <li><a href="{{ app_href('citizen/submit') }}">Submit Feedback</a></li>
        <li><a href="{{ app_href('citizen/track') }}">Track a Submission</a></li>
        <li><a href="{{ app_href('auth/login') }}">Login to Portal</a></li>
      </ul>
    </div>

    <div class="c-footer-col">
      <h4>Need Help?</h4>
      <ul>
        <li>{{ setting('brand_office') }}, {{ setting('contact_address') }}</li>
        <li>{{ setting('contact_hours') }}</li>
        <li>{{ setting('contact_phone') }}</li>
        <li style="font-size:12px;color:rgba(255,255,255,0.35);margin-top:4px;">For urgent emergencies, contact your barangay hall directly.</li>
      </ul>
    </div>
  </div>
  <div class="c-footer-bottom">
    <p>&copy; {!! date('Y') !!} {{ setting('brand_government') }} — {{ setting('brand_office') }}. All rights reserved.</p>
    <p class="c-footer-legal">
      <a href="{{ app_href('citizen/privacy') }}">Privacy Policy</a>
      <span aria-hidden="true">·</span>
      <a href="{{ app_href('citizen/terms') }}">Terms of Service</a>
    </p>
    <p>{{ setting('brand_short_name') }} Portal v2.0</p>
  </div>
</footer>
<script src="{{ asset_v('frontend/js/app.js') }}"></script>
</body>
</html>

