{{-- VIEW: Staff Password Change
     Expects: $staff, $flashes --}}
@include('partials.header')
<meta name="csrf-token" content="{{ csrf_token() }}">

<main class="min-h-screen flex items-center justify-center bg-pagebg p-6">
  <div class="w-full max-w-sm bg-white border border-borderc rounded-lg p-8">
    <div class="mb-6 flex items-center gap-2.5">
      <div class="w-10 h-10 rounded-full flex-shrink-0 overflow-hidden bg-white flex items-center justify-center border border-borderc">
        <img src="{{ brand_logo_url() }}" alt="{{ setting('brand_government') }} logo" class="w-full h-full object-contain">
      </div>
      <p class="font-semibold text-navy">{{ setting('brand_short_name') }}</p>
    </div>

    @if (!empty($staff['must_change_password']))
      <div class="mb-4 text-xs rounded px-3 py-2 bg-amber-50 text-amber-700 border border-amber-200">
        For security, you must set a new password before continuing — this account still has a default/seeded password.
      </div>
    @endif

    @foreach ($flashes as $f)
      <div class="mb-4 text-xs rounded px-3 py-2 {!! $f['type'] === 'warning' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-teal/10 text-teal border border-teal/20' !!}">{{ $f['message'] }}</div>
    @endforeach

    <h2 class="text-lg font-semibold text-navy mb-1">Change Password</h2>
    <p class="text-sm text-slate mb-6">Signed in as {{ $staff['email'] }}.</p>

    <form id="change-password-form" class="space-y-4">
      <div>
        <label class="block text-xs font-medium text-slate mb-1.5">Current Password</label>
        <input type="password" name="current_password" required autocomplete="current-password"
          class="w-full border border-borderc rounded px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate mb-1.5">New Password</label>
        <input type="password" name="new_password" required minlength="10" autocomplete="new-password"
          class="w-full border border-borderc rounded px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal">
        <p class="text-[11px] text-slate mt-1">At least 10 characters.</p>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate mb-1.5">Confirm New Password</label>
        <input type="password" name="confirm_password" required minlength="10" autocomplete="new-password"
          class="w-full border border-borderc rounded px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal">
      </div>
      <p id="change-password-error" class="hidden text-[11px] text-red-600"></p>
      <button type="submit" id="change-password-submit-btn" class="w-full bg-teal text-white text-sm font-semibold py-2.5 rounded hover:bg-teal/90 transition">Update Password</button>
    </form>
  </div>
</main>

<script>
document.getElementById('change-password-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const errorEl = document.getElementById('change-password-error');
  const btn = document.getElementById('change-password-submit-btn');
  errorEl.classList.add('hidden');

  const newPassword = form.new_password.value;
  const confirmPassword = form.confirm_password.value;
  if (newPassword !== confirmPassword) {
    errorEl.textContent = 'New password and confirmation do not match.';
    errorEl.classList.remove('hidden');
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Updating…';

  const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/auth/change-password`, {
    method: 'POST',
    body: JSON.stringify({
      current_password: form.current_password.value,
      new_password: newPassword,
    }),
  });

  btn.disabled = false;
  btn.textContent = 'Update Password';

  if (result.success) {
    window.location.href = result.data.redirect || `${window.APP_URL}/admin/dashboard`;
  }
  // On failure, CivicConnect.apiFetch already shows a toast with the server's message.
});
</script>

@include('partials.footer')
