/**
 * CEPFMS — Login page behavior: password step, OTP step, show/hide password.
 */
(() => {
  const stepPassword = document.getElementById('step-password');
  const stepOtp = document.getElementById('step-otp');
  const passwordForm = document.getElementById('password-form');
  const otpForm = document.getElementById('otp-form');
  if (!passwordForm) return;

  // Show/hide password
  const toggleBtn = document.getElementById('toggle-password');
  const pwInput = document.getElementById('login-password');
  toggleBtn?.addEventListener('click', () => {
    const isPw = pwInput.type === 'password';
    pwInput.type = isPw ? 'text' : 'password';
    toggleBtn.textContent = isPw ? 'Hide' : 'Show';
  });

  const passwordBtn = document.getElementById('password-submit-btn');
  const lockoutEl = document.getElementById('login-lockout');
  const otpBtn = document.getElementById('otp-submit-btn');
  const otpTimerEl = document.getElementById('otp-timer');
  const resendBtn = document.getElementById('otp-resend-btn');
  let lockedOut = false;
  let stopOtpTimer = null;
  let stopResendTimer = null;
  let otpExpired = false;

  // Too many failed attempts: hold the Log In button until the server's window passes.
  function startLockout(seconds) {
    lockedOut = true;
    passwordBtn.disabled = true;
    lockoutEl.classList.remove('hidden');
    CivicConnect.countdown(seconds, (left) => {
      const t = CivicConnect.formatCountdown(left);
      passwordBtn.textContent = `Try again in ${t}`;
      lockoutEl.textContent = `Too many failed attempts. You can try again in ${t}.`;
    }, () => {
      lockedOut = false;
      passwordBtn.disabled = false;
      passwordBtn.textContent = 'Log In';
      lockoutEl.classList.add('hidden');
    });
  }

  // OTP validity: show the time left, and lock the form once the code expires.
  function startOtpTimer(seconds) {
    stopOtpTimer?.();
    otpExpired = false;
    otpBtn.disabled = false;
    otpTimerEl.className = 'text-xs text-center text-slate';
    stopOtpTimer = CivicConnect.countdown(seconds, (left) => {
      otpTimerEl.textContent = `Code expires in ${CivicConnect.formatCountdown(left)}`;
    }, () => {
      otpExpired = true;
      otpBtn.disabled = true;
      otpTimerEl.className = 'text-xs text-center rounded-lg px-3 py-2 bg-red-50 text-red-700 border border-red-200';
      otpTimerEl.textContent = 'This code has expired. Use "Resend code" below to get a new one.';
    });
  }

  // "Resend code" waits out the server's cooldown between codes.
  function startResendCooldown(seconds) {
    stopResendTimer?.();
    resendBtn.disabled = true;
    stopResendTimer = CivicConnect.countdown(seconds, (left) => {
      resendBtn.textContent = `Resend in ${CivicConnect.formatCountdown(left)}`;
    }, () => {
      resendBtn.disabled = false;
      resendBtn.textContent = 'Resend code';
    });
  }

  function showDevOtp(code) {
    const hint = document.getElementById('dev-otp-hint');
    if (!code) return;
    hint.textContent = `Development mode — your OTP is: ${code}`;
    hint.classList.remove('hidden');
  }

  function showPasswordStep() {
    stopOtpTimer?.();
    stopOtpTimer = null;
    stopResendTimer?.();
    stopResendTimer = null;
    stepOtp.classList.add('hidden');
    stepPassword.classList.remove('hidden');
    otpDigits.forEach(i => i.value = '');
  }

  // Step 1: password submit
  passwordForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (lockedOut) return;
    const btn = passwordBtn;
    btn.disabled = true;
    btn.textContent = 'Verifying...';

    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/auth/login`, {
      method: 'POST',
      body: JSON.stringify({
        step: 'password',
        email: document.getElementById('login-email').value.trim(),
        password: pwInput.value,
      }),
    });

    if (result.retry_after) {
      startLockout(result.retry_after);
      return;
    }

    btn.disabled = false;
    btn.textContent = 'Log In';

    if (result.success && result.data.redirect) {
      // Two-factor is off for this account: signed in with the password alone.
      btn.disabled = true;
      btn.textContent = 'Signing in...';
      window.location.href = result.data.redirect;
      return;
    }
    if (result.success) {
      stepPassword.classList.add('hidden');
      stepOtp.classList.remove('hidden');
      showDevOtp(result.data.dev_otp);
      startOtpTimer(result.data.otp_expires_in || 300);
      startResendCooldown(result.data.resend_after || 60);
      document.querySelector('.otp-digit')?.focus();
    }
  });

  // OTP digit navigation
  const otpDigits = Array.from(document.querySelectorAll('.otp-digit'));
  otpDigits.forEach((input, idx) => {
    input.addEventListener('input', () => {
      input.value = input.value.replace(/\D/g, '').slice(0, 1);
      if (input.value && idx < otpDigits.length - 1) otpDigits[idx + 1].focus();
    });
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !input.value && idx > 0) otpDigits[idx - 1].focus();
    });
    input.addEventListener('paste', (e) => {
      e.preventDefault();
      const text = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
      text.split('').forEach((char, i) => { if (otpDigits[i]) otpDigits[i].value = char; });
      otpDigits[Math.min(text.length, otpDigits.length - 1)]?.focus();
    });
  });

  document.getElementById('back-to-password')?.addEventListener('click', showPasswordStep);

  // New code for the sign-in in progress; the previous code stops working.
  resendBtn?.addEventListener('click', async () => {
    resendBtn.disabled = true;
    resendBtn.textContent = 'Sending...';
    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/auth/login`, {
      method: 'POST',
      body: JSON.stringify({ step: 'resend' }),
    });

    if (result.success) {
      otpDigits.forEach(i => i.value = '');
      showDevOtp(result.data.dev_otp);
      startOtpTimer(result.data.otp_expires_in || 300);
      startResendCooldown(result.data.resend_after || 60);
      CivicConnect.toast('A new code was sent to your email.', 'success');
      otpDigits[0]?.focus();
    } else if (result.restart) {
      showPasswordStep();
    } else if (result.retry_after) {
      startResendCooldown(result.retry_after);
    } else {
      resendBtn.disabled = false;
      resendBtn.textContent = 'Resend code';
    }
  });

  // Step 2: OTP submit
  otpForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const code = otpDigits.map(i => i.value).join('');
    if (code.length !== 6) {
      CivicConnect.toast('Please enter the full 6-digit code.', 'warning');
      return;
    }
    const btn = otpBtn;
    btn.disabled = true;
    btn.textContent = 'Verifying...';

    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/auth/login`, {
      method: 'POST',
      body: JSON.stringify({ step: 'otp', code }),
    });

    if (result.success) {
      stopOtpTimer?.();
      window.location.href = result.data.redirect;
    } else {
      btn.disabled = otpExpired;
      btn.textContent = 'Verify & Sign In';
    }
  });
})();
