@if (empty($hideFooter))
<footer class="border-t border-borderc mt-12 py-6 text-center text-xs text-slate">
  <p>&copy; {!! date('Y') !!} CEPFMS — Legislative Services Office. All rights reserved.</p>
</footer>
@endif
<script src="{{ asset_v('frontend/js/app.js') }}"></script>
</body>
</html>
