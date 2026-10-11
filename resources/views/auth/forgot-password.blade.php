{{-- VIEW: Forgot Password (request a reset link)
     Expects (set by AuthController): $flashes --}}
@include('partials.header')
<meta name="csrf-token" content="{{ csrf_token() }}">

<main class="min-h-screen relative flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
  <!-- Background photo -->
  <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ app_href('frontend/images/site/city-hall-hero.jpg') }}'); filter: brightness(0.85);"></div>
  <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(12,26,53,0.25) 0%, rgba(12,26,53,0.55) 55%, rgba(12,26,53,0.92) 100%);"></div>

  <!-- Card -->
  <div class="relative w-full max-w-md rounded-2xl border border-borderc bg-white/90 backdrop-blur-xl shadow-2xl p-8 sm:p-10">

    <div class="w-20 h-20 rounded-full bg-white border border-borderc mx-auto flex items-center justify-center shadow-lg overflow-hidden p-2">
      <img src="{{ brand_logo_url() }}" alt="{{ setting('brand_government') }} logo" class="w-full h-full object-contain">
    </div>

    <h1 class="mt-5 text-center text-navy font-bold text-xl leading-snug">Forgot Password</h1>
    <p class="mt-2 text-center text-slate text-sm">Enter your staff email and we'll send you a link to reset it.</p>

    <div class="border-t border-borderc my-6"></div>

    @foreach ($flashes as $f)
      <div class="mb-4 text-xs rounded-lg px-3 py-2.5 {!! $f['type'] === 'warning' ? 'bg-[#d97706]/10 text-[#b45309] border border-[#d97706]/30' : 'bg-[#10b981]/10 text-[#047857] border border-[#10b981]/30' !!}">{{ $f['message'] }}</div>
    @endforeach

    <!-- Request form -->
    <div id="request-step">
      <form id="forgot-password-form" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate mb-1.5">Email Address</label>
          <div class="relative">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate/50 pointer-events-none"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/></svg>
            <input type="email" name="email" id="forgot-email" required autocomplete="username" autofocus
              class="w-full bg-white border border-borderc text-navy placeholder-slate/40 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d97706]/30 focus:border-[#d97706]/60">
          </div>
        </div>
        <button type="submit" id="forgot-submit-btn" class="w-full bg-[#d97706] text-white text-sm font-bold py-2.5 rounded-lg hover:bg-[#b45309] transition disabled:opacity-60 disabled:cursor-not-allowed">Send Reset Link</button>
        <p id="forgot-lockout" class="hidden text-xs text-center rounded-lg px-3 py-2 bg-red-50 text-red-700 border border-red-200" role="status" aria-live="polite"></p>
      </form>
    </div>

    <!-- Confirmation (shown after a successful request) -->
    <div id="sent-step" class="hidden text-center">
      <div class="w-12 h-12 rounded-full bg-[#10b981]/15 border border-[#10b981]/30 mx-auto flex items-center justify-center mb-4">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
      </div>
      <p class="text-sm text-slate leading-relaxed">If an account exists for that email, a password reset link has been sent to it.</p>
      <p id="dev-reset-hint" class="hidden mt-4 text-xs bg-[#d97706]/10 text-[#b45309] border border-[#d97706]/30 rounded-lg px-3 py-2 break-all"></p>
    </div>

    <a href="{{ app_href('auth/login') }}" class="mt-6 flex items-center justify-center gap-1.5 text-xs text-slate hover:text-navy transition">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Back to Login
    </a>

    <div class="border-t border-borderc mt-6 pt-4">
      <p class="text-center text-[11px] text-slate/60">&copy; {!! date('Y') !!} {{ setting('brand_short_name') }} {{ setting('brand_office') }}. All rights reserved.</p>
    </div>
  </div>
</main>

<script>
document.getElementById('forgot-password-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('forgot-submit-btn');
  btn.disabled = true;
  btn.textContent = 'Sending...';

  const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/auth/forgot-password`, {
    method: 'POST',
    body: JSON.stringify({ email: document.getElementById('forgot-email').value.trim() }),
  });

  if (result.retry_after) {
    // Too many requests: hold the button until the server's window passes.
    const lockoutEl = document.getElementById('forgot-lockout');
    lockoutEl.classList.remove('hidden');
    CivicConnect.countdown(result.retry_after, (left) => {
      const t = CivicConnect.formatCountdown(left);
      btn.textContent = `Try again in ${t}`;
      lockoutEl.textContent = `Too many reset requests. You can request another link in ${t}.`;
    }, () => {
      btn.disabled = false;
      btn.textContent = 'Send Reset Link';
      lockoutEl.classList.add('hidden');
    });
    return;
  }

  btn.disabled = false;
  btn.textContent = 'Send Reset Link';

  if (result.success) {
    document.getElementById('request-step').classList.add('hidden');
    document.getElementById('sent-step').classList.remove('hidden');
    if (result.data && result.data.dev_reset_url) {
      const hint = document.getElementById('dev-reset-hint');
      hint.textContent = `Development mode — reset link: ${result.data.dev_reset_url}`;
      hint.classList.remove('hidden');
    }
  }
});
</script>

@include('partials.footer', ['hideFooter' => true])
