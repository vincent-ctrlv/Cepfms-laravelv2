{{-- VIEW: Citizen sign-in (Google)
     Expects (set by CitizenAccountController): $flashes, $googleAvailable --}}
@include('partials.header')
@include('partials.navbar')

<main id="main-content" class="sf-page">
  <div class="sf-card" style="max-width:480px">
    <header class="sf-head">
      <span class="sf-eyebrow">San Jose del Monte · Citizen Portal</span>
      <h1 class="sf-title">Sign in to send feedback</h1>
      <p class="sf-sub">To keep reports genuine, everyone who submits signs in with Google and verifies their identity once.</p>
    </header>

    @foreach ($flashes as $f)
      <div class="ca-flash {{ $f['type'] === 'warning' ? 'ca-flash-warning' : 'ca-flash-success' }}" role="status">{{ $f['message'] }}</div>
    @endforeach

    <ol class="ca-steps">
      <li class="ca-step"><span class="ca-step-num">1</span><span><strong>Sign in with your Google account</strong>Your Gmail address is where we send updates.</span></li>
      <li class="ca-step"><span class="ca-step-num">2</span><span><strong>Verify your identity (one time)</strong>A photo of a government ID with your San Jose del Monte address, and a selfie holding it.</span></li>
      <li class="ca-step"><span class="ca-step-num">3</span><span><strong>Submit feedback</strong>Once an admin approves your ID, you can send feedback, complaints, and proposals.</span></li>
    </ol>

    @if ($googleAvailable)
      <a href="{{ app_href('citizen/auth/google') }}" class="ca-google">
        <svg width="20" height="20" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
        Continue with Google
      </a>
    @else
      <p class="ca-unavailable">Signing in with Google isn't available yet. Please check back soon.</p>
    @endif

    <p class="ca-foot">
      Checking on something you already sent? <a href="{{ app_href('citizen/track') }}">Track a submission</a> — no sign-in needed.<br>
      By signing in you agree to our <a href="{{ app_href('citizen/privacy') }}">Privacy Policy</a> and <a href="{{ app_href('citizen/terms') }}">Terms of Service</a>.<br>
      LGU staff? <a href="{{ app_href('auth/login') }}">Staff login</a>
    </p>
  </div>
</main>

@include('partials.citizen-footer')
