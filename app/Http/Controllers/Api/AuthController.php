<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmailService;
use App\Support\JsonAbort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Staff sign-in (password, then emailed code), password reset and change.
 */
class AuthController extends Controller
{
    private const OTP_TTL = 5 * 60;              // a code is valid for 5 minutes
    private const OTP_RESEND_COOLDOWN = 60;      // seconds between "Resend code" requests
    private const OTP_MAX_RESENDS = 3;           // new codes per sign-in before the password is needed again
    private const OTP_PENDING_WINDOW = 15 * 60;  // resending only within 15 minutes of the password step

    public function login(Request $request)
    {
        $input = $this->input();
        $step = $input['step'] ?? 'password';
        $session = $request->session();

        try {
            // -------------------------------------------------------------
            // Step 1: verify email + password, then issue a one-time code.
            // -------------------------------------------------------------
            if ($step === 'password') {
                $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_SANITIZE_EMAIL);
                $password = (string) ($input['password'] ?? '');

                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
                    json_fail('Please enter a valid email and password.', 422);
                }

                if (login_rate_limited($email, 'password')) {
                    json_rate_limited('Too many login attempts. Please wait for the timer to finish before trying again.', login_retry_after($email, 'password'));
                }

                $staff = (array) (DB::table('users')
                    ->select('id', 'full_name', 'email', 'role', 'password_hash', 'is_active', 'two_factor_enabled')
                    ->where('email', $email)->first() ?? []);

                if (!$staff || !$staff['is_active'] || !password_verify($password, $staff['password_hash'])) {
                    record_login_attempt($email, 'password');
                    json_fail('Invalid email or password.', 401);
                }

                clear_login_attempts($email, 'password');

                // Two-factor turned off in Profile Settings: the password alone signs them in.
                if (!two_factor_required($staff)) {
                    return $this->completeLogin($request, $staff, false);
                }

                $otp = $this->issueOtp((int) $staff['id'], $staff['email']);

                $session->put([
                    'pending_staff_id' => $staff['id'],
                    'pending_since'    => time(),
                    'otp_sent_at'      => time(),
                    'otp_resends'      => 0,
                ]);

                return json_response(true, $this->otpResponseData($staff['email'], $otp));
            }

            // -------------------------------------------------------------
            // Resend: a new code for the sign-in in progress, without the
            // password again. Limits live in the session: starting over
            // needs the (rate-limited) password, so they can't be reset free.
            // -------------------------------------------------------------
            if ($step === 'resend') {
                $pendingId = $session->get('pending_staff_id');
                $pendingSince = (int) $session->get('pending_since', 0);

                if (!$pendingId || time() - $pendingSince > self::OTP_PENDING_WINDOW) {
                    return $this->otpRestart('Your sign-in has expired. Please enter your password again.', 401);
                }

                $wait = self::OTP_RESEND_COOLDOWN - (time() - (int) $session->get('otp_sent_at', 0));
                if ($wait > 0) {
                    json_rate_limited('Please wait before requesting another code.', $wait);
                }
                if ((int) $session->get('otp_resends', 0) >= self::OTP_MAX_RESENDS) {
                    return $this->otpRestart('Too many new codes requested. Please enter your password again.', 429);
                }

                $staff = DB::table('users')->select('id', 'email', 'is_active')->where('id', $pendingId)->first();
                if (!$staff || !$staff->is_active) {
                    return $this->otpRestart('Please enter your password again.', 401);
                }

                // Replaces the previous code, so only the newest email's code works.
                $otp = $this->issueOtp((int) $staff->id, $staff->email);
                $session->put('otp_sent_at', time());
                $session->put('otp_resends', (int) $session->get('otp_resends', 0) + 1);

                return json_response(true, $this->otpResponseData($staff->email, $otp));
            }

            // -------------------------------------------------------------
            // Step 2: verify the code and establish the signed-in session.
            // -------------------------------------------------------------
            if ($step === 'otp') {
                $code = preg_replace('/\D/', '', (string) ($input['code'] ?? ''));
                $pendingId = $session->get('pending_staff_id');

                if (!$pendingId || strlen($code) !== 6) {
                    json_fail('Please enter the 6-digit code.', 422);
                }

                $staff = (array) (DB::table('users')
                    ->select('id', 'full_name', 'email', 'role', 'otp_code', 'otp_expires_at', 'is_active')
                    ->where('id', $pendingId)->first() ?? []);

                $expiresTs = !empty($staff['otp_expires_at']) ? strtotime($staff['otp_expires_at']) : 0;
                if (!$staff || !$staff['is_active'] || empty($staff['otp_code']) || $expiresTs < time()) {
                    json_fail('This code has expired. Use "Resend code" to get a new one.', 401);
                }

                if (login_rate_limited($staff['email'], 'otp')) {
                    json_fail('Too many attempts. Please sign in again.', 429);
                }

                if (!password_verify($code, $staff['otp_code'])) {
                    record_login_attempt($staff['email'], 'otp');
                    json_fail('Incorrect code. Please try again.', 401);
                }

                clear_login_attempts($staff['email'], 'otp');
                return $this->completeLogin($request, $staff, true);
            }

            json_fail('Unknown login step.', 400);
        } catch (JsonAbort $e) {
            throw $e;
        } catch (Throwable $e) {
            logger()->error('[CEPFMS] Login failed: ' . $e->getMessage());
            json_fail('Unable to sign in right now. Please try again shortly.', 500);
        }
    }

    /** Called by app.js while staff type or click, so writing a long response doesn't count as idle. */
    public function keepalive()
    {
        return json_response(true);
    }

    public function forgotPassword(Request $request)
    {
        $input = $this->input();
        $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_fail('Please enter a valid email address.', 422);
        }

        if (login_rate_limited($email, 'password_reset', 5, 15)) {
            json_rate_limited('Too many requests. Please wait for the timer to finish before trying again.', login_retry_after($email, 'password_reset', 5, 15));
        }
        record_login_attempt($email, 'password_reset');

        // Same generic message whether or not the account exists, so this
        // endpoint can't be used to enumerate staff email addresses.
        $genericMessage = 'If an account exists for that email, a password reset link has been sent to it.';

        try {
            $staff = DB::table('users')->select('id', 'email')->where('email', $email)->where('is_active', 1)->first();
            $responseData = [];

            if ($staff) {
                $rawToken = bin2hex(random_bytes(32));
                DB::table('users')->where('id', $staff->id)->update([
                    'reset_token_hash'       => hash('sha256', $rawToken),
                    'reset_token_expires_at' => date('Y-m-d H:i:s', time() + 30 * 60),
                ]);

                $resetUrl = app_url('auth/reset-password') . '?id=' . $staff->id . '&token=' . $rawToken;

                try {
                    EmailService::sendPasswordReset($staff->email, $resetUrl);
                } catch (Throwable $e) {
                    logger()->error('[CEPFMS] Password reset email failed: ' . $e->getMessage());
                }

                log_activity((int) $staff->id, 'Password reset requested', 'staff', (int) $staff->id, []);

                if (EmailService::isDevMode()) {
                    // Local development convenience only, same as the login code.
                    $responseData['dev_reset_url'] = $resetUrl;
                }
            }

            return json_response(true, $responseData, $genericMessage);
        } catch (Throwable $e) {
            logger()->error('[CEPFMS] Forgot password failed: ' . $e->getMessage());
            json_fail('Unable to process your request right now. Please try again shortly.', 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $input = $this->input();
        $staffId = (int) ($input['id'] ?? 0);
        $token = (string) ($input['token'] ?? '');
        $newPassword = (string) ($input['new_password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');

        if ($staffId <= 0 || $token === '' || $newPassword === '' || $confirmPassword === '') {
            json_fail('Please fill out all fields.', 422);
        }
        if ($newPassword !== $confirmPassword) {
            json_fail('New password and confirmation do not match.', 422);
        }
        if (mb_strlen($newPassword) < 10) {
            json_fail('New password must be at least 10 characters.', 422);
        }

        try {
            $staff = (array) (DB::table('users')
                ->select('id', 'email', 'password_hash', 'reset_token_hash', 'reset_token_expires_at')
                ->where('id', $staffId)->where('is_active', 1)->first() ?? []);

            $expiresTs = !empty($staff['reset_token_expires_at']) ? strtotime($staff['reset_token_expires_at']) : 0;
            if (!$staff || empty($staff['reset_token_hash']) || $expiresTs < time()
                || !hash_equals($staff['reset_token_hash'], hash('sha256', $token))
            ) {
                json_fail('This password reset link is invalid or has expired. Please request a new one.', 401);
            }

            if (password_verify($newPassword, $staff['password_hash'])) {
                json_fail('New password must be different from your current password.', 422);
            }

            DB::table('users')->where('id', $staff['id'])->update([
                'password_hash'          => password_hash($newPassword, PASSWORD_DEFAULT),
                'must_change_password'   => 0,
                'reset_token_hash'       => null,
                'reset_token_expires_at' => null,
            ]);

            clear_login_attempts($staff['email'], 'password_reset');
            log_activity((int) $staff['id'], 'Password reset via emailed link', 'staff', (int) $staff['id'], []);

            flash_set('success', 'Your password has been reset. Please log in with your new password.');
            return json_response(true, ['redirect' => app_href('auth/login')]);
        } catch (JsonAbort $e) {
            throw $e;
        } catch (Throwable $e) {
            logger()->error('[CEPFMS] Reset password failed: ' . $e->getMessage());
            json_fail('Unable to reset your password right now. Please try again shortly.', 500);
        }
    }

    public function changePassword(Request $request)
    {
        $staff = $this->staff();
        $input = $this->input();
        $currentPassword = (string) ($input['current_password'] ?? '');
        $newPassword = (string) ($input['new_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '') {
            json_fail('Please fill out all fields.', 422);
        }
        if (mb_strlen($newPassword) < 10) {
            json_fail('New password must be at least 10 characters.', 422);
        }

        $hash = DB::table('users')->where('id', $staff['id'])->value('password_hash');

        if (!$hash || !password_verify($currentPassword, $hash)) {
            json_fail('Current password is incorrect.', 401);
        }
        if (password_verify($newPassword, $hash)) {
            json_fail('New password must be different from your current password.', 422);
        }

        DB::table('users')->where('id', $staff['id'])->update([
            'password_hash'        => password_hash($newPassword, PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ]);

        log_activity((int) $staff['id'], 'Password changed', 'staff', (int) $staff['id'], []);

        return json_response(true, ['redirect' => app_href('admin/dashboard')]);
    }

    // ---------------------------------------------------------------------

    /** Store a fresh hashed code for the user (replacing any previous one) and email it. */
    private function issueOtp(int $userId, string $email): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('users')->where('id', $userId)->update([
            'otp_code'       => password_hash($otp, PASSWORD_DEFAULT),
            'otp_expires_at' => date('Y-m-d H:i:s', time() + self::OTP_TTL),
        ]);

        try {
            EmailService::sendOtp($email, $otp);
        } catch (Throwable $e) {
            logger()->error('[CEPFMS] OTP email failed: ' . $e->getMessage());
        }
        return $otp;
    }

    /** Resend refused for good: tell the page to go back to the password step. */
    private function otpRestart(string $message, int $httpCode): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'restart' => true], $httpCode);
    }

    /** Signed in: clear any code, start a fresh session, log it, and send them to the dashboard. */
    private function completeLogin(Request $request, array $staff, bool $usedCode): JsonResponse
    {
        DB::table('users')->where('id', $staff['id'])->update([
            'otp_code'       => null,
            'otp_expires_at' => null,
            'last_login_at'  => now(),
        ]);

        $session = $request->session();
        $session->migrate(true); // new session ID: no fixation
        $session->forget(['pending_staff_id', 'pending_since', 'otp_sent_at', 'otp_resends']);
        $session->put('staff_id', (int) $staff['id']);
        $session->put('_last_activity', time());
        forget_current_staff();

        $roleLabel = role_label($staff['role']);
        log_activity((int) $staff['id'], "{$roleLabel} logged in", 'staff', (int) $staff['id'], $usedCode ? [] : ['two_factor' => false]);

        return json_response(true, ['redirect' => app_href('admin/dashboard')]);
    }

    private function otpResponseData(string $email, string $otp): array
    {
        $data = ['email' => $email, 'otp_expires_in' => self::OTP_TTL, 'resend_after' => self::OTP_RESEND_COOLDOWN];
        if (EmailService::isDevMode()) {
            // Local development convenience only: show the code instead of needing log access.
            $data['dev_otp'] = $otp;
        }
        return $data;
    }
}
