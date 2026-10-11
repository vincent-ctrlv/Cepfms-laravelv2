    </main>
  </div>
</div>
<script>
  window.CLASSIFY_OPTIONS = {!! json_encode([
      'types' => \App\Services\AIClassifier::TYPES,
      'categories' => \App\Services\AIClassifier::CATEGORIES,
      'priorities' => \App\Services\AIClassifier::PRIORITIES,
      'sentiments' => \App\Services\AIClassifier::SENTIMENTS,
  ], JSON_HEX_TAG | JSON_HEX_AMP) !!};
  // Logged-in admin pages only: lets app.js keep the session alive while staff work.
  window.STAFF_KEEPALIVE_URL = {!! json_encode(app_href('api/auth/keepalive'), JSON_HEX_TAG | JSON_HEX_AMP) !!};
</script>
<script src="{{ asset_v('frontend/js/app.js') }}"></script>
</body>
</html>
