{{-- VIEW: Staff Login (password + OTP two-step)
     Expects (set by AuthController): $flashes --}}
@include('partials.header')
<meta name="csrf-token" content="{{ csrf_token() }}">

<main class="auth-page min-h-screen relative flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
  <!-- Background photo -->
  <div class="auth-bg-photo" aria-hidden="true"></div>
  <div class="auth-bg-overlay" aria-hidden="true"></div>

  <!-- Login card -->
  <div class="relative w-full max-w-md rounded-2xl border border-borderc bg-white/90 backdrop-blur-xl shadow-2xl p-8 sm:p-10">

    <div class="w-20 h-20 rounded-full bg-white border border-borderc mx-auto flex items-center justify-center shadow-lg overflow-hidden p-2">
      <img src="{{ brand_logo_url() }}" alt="{{ setting('brand_government') }} logo" class="w-full h-full object-contain">
    </div>

    <h1 class="mt-5 text-center text-navy font-bold text-xl leading-snug">{{ setting('brand_full_name') }}</h1>
    <p class="mt-2 text-center text-[#b45309] text-xs font-semibold tracking-widest uppercase">Authorized Personnel Only</p>

    <div class="border-t border-borderc my-6"></div>

    @foreach ($flashes as $f)
      <div class="mb-4 text-xs rounded-lg px-3 py-2.5 {!! $f['type'] === 'warning' ? 'bg-[#d97706]/10 text-[#b45309] border border-[#d97706]/30' : 'bg-[#10b981]/10 text-[#047857] border border-[#10b981]/30' !!}">{{ $f['message'] }}</div>
    @endforeach

    <!-- Step 1: Email + Password -->
    <div id="step-password">
      <form id="password-form" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate mb-1.5">Email Address</label>
          <div class="relative">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate/50 pointer-events-none"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/></svg>
            <input type="email" name="email" id="login-email" required autocomplete="username" autofocus
              placeholder="Enter your email"
              class="w-full bg-white border border-borderc text-navy placeholder-slate/40 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d97706]/30 focus:border-[#d97706]/60">
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate mb-1.5">Password</label>
          <div class="relative">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate/50 pointer-events-none"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            <input type="password" name="password" id="login-password" required autocomplete="current-password"
              placeholder="Enter your password"
              class="w-full bg-white border border-borderc text-navy placeholder-slate/40 rounded-lg pl-9 pr-14 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d97706]/30 focus:border-[#d97706]/60">
            <button type="button" id="toggle-password" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate text-xs font-medium hover:text-navy">Show</button>
          </div>
          <div class="mt-1.5 text-right">
            <a href="{{ app_href('auth/forgot-password') }}" class="text-xs font-medium text-[#b45309] hover:underline">Forgot Password?</a>
          </div>
        </div>
        <button type="submit" id="password-submit-btn" class="w-full bg-[#d97706] text-white text-sm font-bold py-2.5 rounded-lg hover:bg-[#b45309] transition disabled:opacity-60 disabled:cursor-not-allowed">Log In</button>
        <p id="login-lockout" class="hidden text-xs text-center rounded-lg px-3 py-2 bg-red-50 text-red-700 border border-red-200" role="status" aria-live="polite"></p>
      </form>
    </div>

    <!-- Step 2: OTP -->
    <div id="step-otp" class="hidden">
      <h2 class="text-base font-semibold text-navy mb-1 text-center">Verification Code</h2>
      <p class="text-sm text-slate mb-6 text-center">Enter the 6-digit code sent to your email.</p>
      <div id="dev-otp-hint" class="hidden mb-4 text-xs bg-[#d97706]/10 text-[#b45309] border border-[#d97706]/30 rounded-lg px-3 py-2"></div>
      <form id="otp-form" class="space-y-6">
        <div class="flex justify-center gap-2" id="otp-boxes">
          @for ($i = 0; $i < 6; $i++)
            <input type="text" inputmode="numeric" maxlength="1" data-idx="{!! $i !!}"
              class="otp-digit w-9 min-w-0 h-11 text-center text-base font-mono bg-white border border-borderc text-navy rounded-lg focus:outline-none focus:ring-2 focus:ring-[#d97706]/30 focus:border-[#d97706]/60">
          @endfor
        </div>
        <div class="space-y-1.5">
          <p id="otp-timer" class="text-xs text-center text-slate" role="status" aria-live="off"></p>
          <p class="text-xs text-center text-slate">
            Didn't get it?
            <button type="button" id="otp-resend-btn" class="font-semibold text-[#b45309] hover:underline disabled:text-slate disabled:no-underline disabled:cursor-not-allowed">Resend code</button>
          </p>
        </div>
        <button type="submit" id="otp-submit-btn" class="w-full bg-[#d97706] text-white text-sm font-bold py-2.5 rounded-lg hover:bg-[#b45309] transition disabled:opacity-60 disabled:cursor-not-allowed">Verify &amp; Sign In</button>
        <button type="button" id="back-to-password" class="w-full text-xs text-slate hover:text-navy transition">&larr; Back to sign in</button>
      </form>
    </div>

    <a href="{{ app_href('citizen') }}" class="mt-6 flex items-center justify-center gap-1.5 text-xs text-slate hover:text-navy transition">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Back to Landing Page
    </a>

    <div class="border-t border-borderc mt-6 pt-4">
      <p class="text-center text-[11px] text-slate/60">&copy; {!! date('Y') !!} {{ setting('brand_short_name') }} {{ setting('brand_office') }}. All rights reserved.</p>
    </div>
  </div>
</main>

@include('partials.footer', ['hideFooter' => true])
<script src="{{ asset_v('frontend/js/auth.js') }}"></script>
