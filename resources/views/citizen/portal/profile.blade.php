{{-- VIEW: Citizen portal — profile
     Expects (set by CitizenAccountController::profile): $citizen, $account, $status, $verification --}}
@extends('layouts.citizen-portal')

@section('content')
<p class="cp-sub cp-intro">Your account details and identity verification.</p>

<div class="cp-grid">
  <section class="cp-card">
    <div class="cp-card-head"><h2>Account</h2></div>
    <dl class="cp-facts cp-facts-stacked">
      <div><dt>Name</dt><dd>{{ $citizen['full_name'] }}
        <span class="cp-hint">{{ $status === 'Approved' ? 'As printed on your verified ID.' : 'From your Google account; replaced by the name on your ID once it is verified.' }}</span></dd></div>
      <div><dt>Email</dt><dd>{{ $citizen['email'] }}<span class="cp-hint">Your Google account. Updates about your reports go here.</span></dd></div>
      <div><dt>Member since</dt><dd>{{ !empty($account['created_at']) ? date('F j, Y', strtotime($account['created_at'])) : '—' }}</dd></div>
    </dl>

    <form id="phone-form" class="cp-form" novalidate>
      <label class="sf-label" for="phone">Mobile number</label>
      <div class="cp-inline">
        <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" class="sf-input" placeholder="09XX XXX XXXX" value="{{ $citizen['phone'] ?? '' }}">
        <button type="submit" class="cp-btn cp-btn-primary">Save</button>
      </div>
      <p class="sf-help">The office may call or text this number about your reports.</p>
      <p id="phone-msg" class="sf-help hidden" role="status"></p>
    </form>
  </section>

  <div class="cp-stack">
    <section class="cp-card">
      <div class="cp-card-head"><h2>Identity verification</h2></div>
      @if ($verification)
        <dl class="cp-facts cp-facts-stacked">
          <div><dt>Status</dt><dd>
            <span class="badge {{ ['Approved' => 'badge-green', 'Pending' => 'badge-amber', 'Rejected' => 'badge-red'][$status] ?? 'badge-slate' }}">{{ ['Approved' => 'Verified', 'Pending' => 'Under review', 'Rejected' => 'Not verified'][$status] ?? $status }}</span>
          </dd></div>
          <div><dt>ID</dt><dd>{{ $verification['id_type'] }} ending in {{ $verification['id_number_last4'] }}</dd></div>
          <div><dt>Barangay</dt><dd>{{ $verification['barangay'] }}</dd></div>
          <div><dt>{{ $status === 'Approved' ? 'Verified on' : 'Sent on' }}</dt><dd>{{ date('F j, Y', strtotime($verification[$status === 'Approved' ? 'reviewed_at' : 'created_at'] ?? $verification['created_at'])) }}</dd></div>
        </dl>
        @if ($status === 'Rejected')<a href="{{ app_href('citizen/verify') }}" class="cp-btn cp-btn-primary cp-mt">Send your ID again</a>@endif
      @else
        <p class="cp-muted">You haven't verified your identity yet.</p>
        <a href="{{ app_href('citizen/verify') }}" class="cp-btn cp-btn-primary cp-mt">Verify my ID</a>
      @endif
    </section>

    <section class="cp-card">
      <div class="cp-card-head"><h2>Your data</h2></div>
      <p class="cp-muted">To get a copy of your data, correct it, or close your account, contact
        <a href="mailto:{{ setting('contact_email') }}">{{ setting('contact_email') }}</a>. See the
        <a href="{{ app_href('citizen/privacy') }}">Privacy Policy</a> for your rights.</p>
      <form method="POST" action="{{ app_href('citizen/logout') }}" class="cp-mt">
        @csrf
        <button type="submit" class="cp-btn cp-btn-ghost">Sign out</button>
      </form>
    </section>
  </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const form = document.getElementById('phone-form');
  const msg = document.getElementById('phone-msg');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const phone = form.elements.phone.value.trim();
    msg.classList.add('hidden');
    if (!/^(09|\+639)\d{9}$/.test(phone.replace(/[\s\-()]/g, ''))) {
      msg.textContent = 'Enter a valid mobile number (e.g. 09171234567).';
      msg.classList.remove('hidden');
      return;
    }
    const r = await CivicConnect.apiFetch(`${window.APP_URL || ''}/api/citizen/profile`, {
      method: 'POST', body: JSON.stringify({ phone }),
    });
    if (r.success) {
      form.elements.phone.value = r.data.phone;
      CivicConnect.toast('Your mobile number has been saved.', 'success');
    }
  });
})();
</script>
@endpush
