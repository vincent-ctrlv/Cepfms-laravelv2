{{-- VIEW: Reset Password (from the emailed reset link)
     Expects (set by AuthController): $flashes, $staffId, $token, $expiresIn --}}
@include('partials.header')
<meta name="csrf-token" content="{{ csrf_token() }}">

<main class="auth-page min-h-screen relative flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
  <!-- Background photo -->
  <div class="auth-bg-photo" aria-hidden="true"></div>
  <div class="auth-bg-overlay" aria-hidden="true"></div>

  <!-- Card -->
  <div class="relative w-full max-w-md rounded-2xl border border-borderc bg-white/90 backdrop-blur-xl shadow-2xl p-8 sm:p-10">

    <div class="w-20 h-20 rounded-full bg-white border border-borderc mx-auto flex items-center justify-center shadow-lg overflow-hidden p-2">
      <img src="{{ brand_logo_url() }}" alt="{{ setting('brand_government') }} logo" class="w-full h-full object-contain">
    </div>

    <h1 class="mt-5 text-center text-navy font-bold text-xl leading-snug">Reset Your Password</h1>
    <p class="mt-2 text-center text-slate text-sm">Choose a new password for your staff account.</p>

    <div class="border-t border-borderc my-6"></div>

    @foreach ($flashes as $f)
      <div class="mb-4 text-xs rounded-lg px-3 py-2.5 {!! $f['type'] === 'warning' ? 'bg-[#d97706]/10 text-[#b45309] border border-[#d97706]/30' : 'bg-[#10b981]/10 text-[#047857] border border-[#10b981]/30' !!}">{{ $f['message'] }}</div>
    @endforeach

    <p id="reset-link-timer" class="mb-4 text-xs text-center rounded-lg px-3 py-2 bg-[#d97706]/10 text-[#b45309] border border-[#d97706]/30" role="status" aria-live="off"></p>

    <form id="reset-password-form" class="space-y-4" data-expires-in="{{ (string) $expiresIn }}">
      <input type="hidden" name="id" value="{{ (string) $staffId }}">
      <input type="hidden" name="token" value="{{ $token }}">
      <div>
        <label class="block text-xs font-medium text-slate mb-1.5">New Password</label>
        <input type="password" name="new_password" required minlength="10" autocomplete="new-password"
          class="w-full bg-white border border-borderc text-navy placeholder-slate/40 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d97706]/30 focus:border-[#d97706]/60">
        <p class="text-[11px] text-slate/70 mt-1">At least 10 characters.</p>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate mb-1.5">Confirm New Password</label>
        <input type="password" name="confirm_password" required minlength="10" autocomplete="new-password"
          class="w-full bg-white border border-borderc text-navy placeholder-slate/40 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d97706]/30 focus:border-[#d97706]/60">
      </div>
      <p id="reset-password-error" class="hidden text-[11px] text-red-600"></p>
      <button type="submit" id="reset-submit-btn" class="w-full bg-[#d97706] text-white text-sm font-bold py-2.5 rounded-lg hover:bg-[#b45309] transition disabled:opacity-60 disabled:cursor-not-allowed">Reset Password</button>
    </form>

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
let resetLinkExpired = false;

document.getElementById('reset-password-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  if (resetLinkExpired) return;
  const form = e.target;
  const errorEl = document.getElementById('reset-password-error');
  const btn = document.getElementById('reset-submit-btn');
  errorEl.classList.add('hidden');

  const newPassword = form.new_password.value;
  const confirmPassword = form.confirm_password.value;
  if (newPassword !== confirmPassword) {
    errorEl.textContent = 'New password and confirmation do not match.';
    errorEl.classList.remove('hidden');
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Resetting...';

  const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/auth/reset-password`, {
    method: 'POST',
    body: JSON.stringify({
      id: form.id.value,
      token: form.token.value,
      new_password: newPassword,
      confirm_password: confirmPassword,
    }),
  });

  btn.disabled = resetLinkExpired;
  btn.textContent = 'Reset Password';

  if (result.success) {
    window.location.href = result.data.redirect || `${window.APP_URL}/auth/login`;
  }
});
</script>

@include('partials.footer', ['hideFooter' => true])
<script>
// The emailed link is only good for a limited time — count it down, then lock the form.
(() => {
  const form = document.getElementById('reset-password-form');
  const timerEl = document.getElementById('reset-link-timer');
  const forgotUrl = `${window.APP_URL}/auth/forgot-password`;

  CivicConnect.countdown(parseInt(form.dataset.expiresIn, 10) || 0, (left) => {
    timerEl.textContent = `This reset link expires in ${CivicConnect.formatCountdown(left)}.`;
  }, () => {
    resetLinkExpired = true;
    form.querySelectorAll('input, button').forEach(el => { el.disabled = true; });
    timerEl.className = 'mb-4 text-xs text-center rounded-lg px-3 py-2 bg-red-50 text-red-700 border border-red-200';
    timerEl.innerHTML = `This reset link has expired. <a href="${forgotUrl}" class="font-semibold underline">Request a new one</a>.`;
  });
})();
</script>
