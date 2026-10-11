<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * CEPFMS — Email Service
 *
 * With no mail server configured (local development), this runs in "dev
 * mode": emails/OTPs are written to the log instead of being sent, and the
 * login screen shows the code on-screen. Production sends through Laravel's
 * mailer (MAIL_MAILER=smtp + MAIL_HOST/MAIL_USERNAME/MAIL_PASSWORD in .env).
 */
class EmailService
{
    public static function smtpIsConfigured(): bool
    {
        return config('mail.default') === 'smtp'
            && (string) config('mail.mailers.smtp.host') !== ''
            && (string) config('mail.mailers.smtp.username') !== ''
            && str_replace(' ', '', (string) config('mail.mailers.smtp.password')) !== '';
    }

    public static function isDevMode(): bool
    {
        // Real SMTP credentials mean send the OTP (do not echo it in the UI).
        if (self::smtpIsConfigured()) {
            return false;
        }
        // Hard safety rail: never treat this as dev mode in production,
        // regardless of the mutable `settings` table below. This is what
        // gates whether OTP codes get echoed back in API responses, so it
        // must not be controllable by DB state alone once deployed.
        if (!app()->environment('local')) {
            return false;
        }
        try {
            $val = DB::table('settings')->where('setting_key', 'otp_dev_mode')->value('setting_value');
            return $val === null ? true : ((string) $val === '1');
        } catch (Throwable $e) {
            return true;
        }
    }

    public static function sendOtp(string $toEmail, string $code): bool
    {
        $subject = 'Your ' . setting('brand_short_name') . ' Login Verification Code';
        $body = "Your one-time verification code is: {$code}\nThis code expires in 5 minutes.\nIf you did not request this login, please contact your administrator immediately.";
        $htmlBody = self::buildOtpEmailHtml($code);

        if (self::isDevMode()) {
            logger()->info("[CEPFMS][DEV OTP] To: {$toEmail} | Code: {$code}");
            return true;
        }

        return self::sendViaSmtp($toEmail, $subject, $body, $htmlBody);
    }

    /** Password for a protected file this user just downloaded (e.g. the analytics PDF). */
    public static function sendFilePassword(string $toEmail, string $fileName, string $password): bool
    {
        $subject = 'Password for your ' . setting('brand_short_name') . ' download';
        $body = "You exported {$fileName} from " . setting('brand_short_name') . " (to download or print).\n\n"
              . "Password to open it: {$password}\n\n"
              . "Each download has its own password. If you did not download this file, please contact your administrator immediately.";

        if (self::isDevMode()) {
            logger()->info("[CEPFMS][DEV EMAIL] File password for {$toEmail} ({$fileName}): {$password}");
            return true;
        }

        return self::sendViaSmtp($toEmail, $subject, $body);
    }

    public static function sendPasswordReset(string $toEmail, string $resetUrl): bool
    {
        $subject = 'Reset Your ' . setting('brand_short_name') . ' Password';
        $body = "We received a request to reset your " . setting('brand_short_name') . " staff password.\n\n"
              . "Reset your password here: {$resetUrl}\n\n"
              . "This link expires in 30 minutes. If you did not request this, you can safely ignore this email — your password will not be changed.";
        $htmlBody = self::buildPasswordResetEmailHtml($resetUrl);

        if (self::isDevMode()) {
            logger()->info("[CEPFMS][DEV EMAIL] Password reset for {$toEmail}: {$resetUrl}");
            return true;
        }

        return self::sendViaSmtp($toEmail, $subject, $body, $htmlBody);
    }

    /**
     * Login details for a staff account a super admin just created, or new ones
     * when the invite is resent ($resend) — the earlier password stops working.
     */
    public static function sendAccountInvite(string $toEmail, string $fullName, string $tempPassword, bool $resend = false): bool
    {
        $appName  = setting('brand_short_name');
        $loginUrl = app_url('auth/login');
        $subject  = $resend ? "Your new {$appName} login details" : "Your {$appName} staff account";
        $body = "Hi {$fullName},\n\n"
              . ($resend
                  ? "Here are new login details for your {$appName} staff account. The temporary password in any earlier email no longer works.\n\n"
                  : "A staff account has been created for you on " . setting('brand_full_name') . ".\n\n")
              . "Email: {$toEmail}\n"
              . "Temporary password: {$tempPassword}\n\n"
              . "Log in here: {$loginUrl}\n\n"
              . "You'll be asked to choose your own password the first time you log in. If you weren't expecting this email, please contact your administrator.";
        $htmlBody = self::buildAccountInviteEmailHtml($toEmail, $fullName, $tempPassword, $loginUrl, $resend);

        if (self::isDevMode()) {
            logger()->info("[CEPFMS][DEV EMAIL] Account invite for {$toEmail}: {$tempPassword}");
            return true;
        }

        return self::sendViaSmtp($toEmail, $subject, $body, $htmlBody);
    }

    private static function buildAccountInviteEmailHtml(string $toEmail, string $fullName, string $tempPassword, string $loginUrl, bool $resend): string
    {
        $esc      = fn(string $v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $appHtml  = $esc(setting('brand_short_name'));
        $fullHtml = $esc(setting('brand_full_name'));
        $nameHtml = $esc($fullName);
        $mailHtml = $esc($toEmail);
        $passHtml = $esc($tempPassword);
        $urlHtml  = $esc($loginUrl);
        $year     = date('Y');
        $label    = $resend ? 'New Login Details' : 'Your New Account';
        $intro    = $resend
            ? "Here are new login details for your {$appHtml} staff account. The temporary password in any earlier email no longer works."
            : "A staff account has been created for you on {$fullHtml}. Use the details below to log in.";

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="color-scheme" content="light dark">
  <meta name="supported-color-schemes" content="light dark">
  <title>{$label} — {$appHtml}</title>
  <style type="text/css">
    :root { color-scheme: light dark; }
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
    @media only screen and (max-width: 520px) {
      .email-wrapper { padding: 12px 6px !important; }
      .email-card { width: 100% !important; border-radius: 10px !important; }
      .email-header { padding: 20px 16px !important; }
      .email-body { padding: 24px 16px !important; }
      .btn-link { display: block !important; width: 100% !important; text-align: center !important; box-sizing: border-box !important; padding: 14px 20px !important; }
    }
    @media (prefers-color-scheme: dark) {
      body, .email-wrapper { background-color: #0b1120 !important; }
      .email-card { background-color: #1e293b !important; border-color: #334155 !important; box-shadow: 0 4px 20px rgba(0,0,0,0.5) !important; }
      .email-header { background-color: #030712 !important; border-bottom-color: #3b82f6 !important; }
      .email-body { background-color: #1e293b !important; color: #e2e8f0 !important; }
      .email-footer { background-color: #0f172a !important; border-top-color: #334155 !important; }
      .invite-title, .invite-value { color: #f8fafc !important; }
      .invite-sub, .invite-label { color: #94a3b8 !important; }
      .invite-box { background-color: #0f172a !important; border-color: #3b82f6 !important; }
      .invite-pass { color: #60a5fa !important; }
      .invite-note { color: #e2e8f0 !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;color:#334155;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" class="email-wrapper" style="background-color:#f1f5f9;padding:32px 12px;width:100% !important;">
    <tr>
      <td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" class="email-card" style="max-width:500px;width:100%;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(15,30,61,0.08);border:1px solid #e2e8f0;">
          <tr>
            <td class="email-header" style="background:#0f1e3d;padding:24px 32px;text-align:center;border-bottom:3px solid #2563eb;">
              <div style="font-size:20px;font-weight:800;letter-spacing:0.04em;color:#ffffff;">
                {$appHtml}
              </div>
              <div style="font-size:11px;font-weight:600;letter-spacing:0.08em;color:#93c5fd;text-transform:uppercase;margin-top:4px;">
                Staff Portal &middot; {$label}
              </div>
            </td>
          </tr>
          <tr>
            <td class="email-body" style="padding:32px 32px 28px;text-align:center;background-color:#ffffff;">
              <h2 class="invite-title" style="margin:0 0 10px;font-size:18px;font-weight:700;color:#0f1e3d;">
                Hi {$nameHtml},
              </h2>
              <p class="invite-sub" style="margin:0 0 22px;font-size:13px;color:#64748b;line-height:1.6;">
                {$intro}
              </p>
              <table cellpadding="0" cellspacing="0" border="0" class="invite-box" style="margin:0 auto 24px;background-color:#f8fafc;border:2px dashed #93c5fd;border-radius:10px;">
                <tr>
                  <td style="padding:14px 22px;text-align:left;">
                    <div class="invite-label" style="font-size:11px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:#64748b;">Email</div>
                    <div class="invite-value" style="font-size:14px;font-weight:600;color:#0f1e3d;margin:2px 0 12px;word-break:break-all;">{$mailHtml}</div>
                    <div class="invite-label" style="font-size:11px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:#64748b;">Temporary password</div>
                    <div class="invite-pass" style="font-family:'Roboto Mono','Courier New',monospace;font-size:20px;font-weight:700;letter-spacing:0.08em;color:#0f1e3d;margin-top:2px;">{$passHtml}</div>
                  </td>
                </tr>
              </table>
              <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 22px;">
                <tr>
                  <td align="center" style="background-color:#2563eb;border-radius:8px;box-shadow:0 2px 8px rgba(37,99,235,0.3);">
                    <a href="{$urlHtml}" target="_blank" class="btn-link"
                       style="display:inline-block;padding:13px 30px;color:#ffffff;font-size:13px;font-weight:700;text-decoration:none;letter-spacing:0.04em;text-transform:uppercase;">
                      Log In
                    </a>
                  </td>
                </tr>
              </table>
              <p class="invite-note" style="margin:0 0 8px;font-size:12px;color:#0f1e3d;font-weight:600;">
                You'll be asked to choose your own password the first time you log in.
              </p>
              <p style="margin:0;font-size:11px;color:#94a3b8;">
                If you weren't expecting this email, please contact your administrator.
              </p>
            </td>
          </tr>
          <tr>
            <td class="email-footer" style="background-color:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 32px;text-align:center;">
              <p style="margin:0;font-size:11px;color:#94a3b8;">
                &copy; {$year} {$appHtml}. Automated account notification.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    private static function buildPasswordResetEmailHtml(string $resetUrl): string
    {
        $appName = setting('brand_short_name');
        $year    = date('Y');
        $urlHtml = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $appHtml = htmlspecialchars($appName, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="color-scheme" content="light dark">
  <meta name="supported-color-schemes" content="light dark">
  <title>Reset Your {$appHtml} Password</title>
  <style type="text/css">
    :root { color-scheme: light dark; }
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
    @media only screen and (max-width: 520px) {
      .email-wrapper { padding: 12px 6px !important; }
      .email-card { width: 100% !important; border-radius: 10px !important; }
      .email-header { padding: 20px 16px !important; }
      .email-body { padding: 24px 16px !important; }
      .btn-link { display: block !important; width: 100% !important; text-align: center !important; box-sizing: border-box !important; padding: 14px 20px !important; }
    }
    @media (prefers-color-scheme: dark) {
      body, .email-wrapper { background-color: #0b1120 !important; }
      .email-card { background-color: #1e293b !important; border-color: #334155 !important; box-shadow: 0 4px 20px rgba(0,0,0,0.5) !important; }
      .email-header { background-color: #030712 !important; border-bottom-color: #3b82f6 !important; }
      .email-body { background-color: #1e293b !important; color: #e2e8f0 !important; }
      .email-footer { background-color: #0f172a !important; border-top-color: #334155 !important; }
      .reset-title { color: #f8fafc !important; }
      .reset-sub { color: #94a3b8 !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;color:#334155;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" class="email-wrapper" style="background-color:#f1f5f9;padding:32px 12px;width:100% !important;">
    <tr>
      <td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" class="email-card" style="max-width:500px;width:100%;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(15,30,61,0.08);border:1px solid #e2e8f0;">
          <tr>
            <td class="email-header" style="background:#0f1e3d;padding:24px 32px;text-align:center;border-bottom:3px solid #2563eb;">
              <div style="font-size:20px;font-weight:800;letter-spacing:0.04em;color:#ffffff;">
                {$appHtml}
              </div>
              <div style="font-size:11px;font-weight:600;letter-spacing:0.08em;color:#93c5fd;text-transform:uppercase;margin-top:4px;">
                Staff Portal &middot; Password Reset
              </div>
            </td>
          </tr>
          <tr>
            <td class="email-body" style="padding:32px 32px 28px;text-align:center;background-color:#ffffff;">
              <h2 class="reset-title" style="margin:0 0 10px;font-size:18px;font-weight:700;color:#0f1e3d;">
                Reset Your Password
              </h2>
              <p class="reset-sub" style="margin:0 0 24px;font-size:13px;color:#64748b;line-height:1.6;">
                We received a request to reset your {$appHtml} staff password. Click the button below to choose a new one.
              </p>
              <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 24px;">
                <tr>
                  <td align="center" style="background-color:#d97706;border-radius:8px;box-shadow:0 2px 8px rgba(217,119,6,0.3);">
                    <a href="{$urlHtml}" target="_blank" class="btn-link"
                       style="display:inline-block;padding:13px 30px;color:#ffffff;font-size:13px;font-weight:700;text-decoration:none;letter-spacing:0.04em;text-transform:uppercase;">
                      Reset Password
                    </a>
                  </td>
                </tr>
              </table>
              <p style="margin:0 0 8px;font-size:12px;color:#dc2626;font-weight:600;">
                This link will expire in 30 minutes.
              </p>
              <p style="margin:0;font-size:11px;color:#94a3b8;">
                If you did not request this, you can safely ignore this email — your password will not be changed.
              </p>
            </td>
          </tr>
          <tr>
            <td class="email-footer" style="background-color:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 32px;text-align:center;">
              <p style="margin:0;font-size:11px;color:#94a3b8;">
                &copy; {$year} {$appHtml}. Automated security notification.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    public static function notifyResponseSent(
        string $toEmail,
        string $subject,
        string $bodyText,
        string $citizenName    = 'Citizen',
        string $referenceNumber = ''
    ): bool {
        $htmlBody = self::buildResponseEmailHtml($subject, $bodyText, $citizenName, $referenceNumber);

        if (self::isDevMode()) {
            logger()->info("[CEPFMS][DEV EMAIL] To: {$toEmail} | Subject: {$subject}");
            return true;
        }
        return self::sendViaSmtp($toEmail, $subject, $bodyText, $htmlBody);
    }    /**
     * Build the HTML email body for an official response to a citizen     */
    private static function buildResponseEmailHtml(
        string $subject,
        string $bodyText,
        string $citizenName,
        string $referenceNumber
    ): string {
        $appName  = setting('brand_short_name');
        $fullHtml = htmlspecialchars(setting('brand_full_name'), ENT_QUOTES, 'UTF-8');
        $appUrl   = app_url();
        $trackUrl = $appUrl ? rtrim($appUrl, '/') . '/citizen/track' . ($referenceNumber ? '?ref=' . urlencode($referenceNumber) : '') : '#';
        $year     = date('Y');

        // Normalise subject
        $cleanSubject = preg_replace('/^Re:\s*/i', 'Regarding: ', trim($subject));
        if (!preg_match('/^Regarding:\s*/i', $cleanSubject)) {
            $cleanSubject = 'Regarding: ' . $cleanSubject;
        }

        // Strip any leading "Dear …," already in the body
        $cleanBody = preg_replace('/^\s*Dear\s+[^,\n]+,\s*/i', '', $bodyText);
        $bodyHtml  = nl2br(htmlspecialchars($cleanBody, ENT_QUOTES, 'UTF-8'));
        $nameHtml  = htmlspecialchars($citizenName ?: 'Citizen', ENT_QUOTES, 'UTF-8');
        $subjHtml  = htmlspecialchars($cleanSubject, ENT_QUOTES, 'UTF-8');
        $appHtml   = htmlspecialchars($appName,      ENT_QUOTES, 'UTF-8');
        $refHtml   = htmlspecialchars($referenceNumber, ENT_QUOTES, 'UTF-8');

        $refBadgeHtml = $refHtml ? <<<BADGE
        <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px;">
          <tr>
            <td class="ref-cell" style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:6px;padding:5px 14px;font-family:'Roboto Mono','Courier New',monospace;font-size:12px;color:#334155;white-space:nowrap;">
              <span style="color:#64748b;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-right:6px;">Reference:</span>
              <strong class="ref-num" style="color:#0f1e3d;font-size:13px;letter-spacing:0.04em;">{$refHtml}</strong>
            </td>
          </tr>
        </table>
BADGE : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="color-scheme" content="light dark">
  <meta name="supported-color-schemes" content="light dark">
  <title>{$subjHtml}</title>
  <style type="text/css">
    :root { color-scheme: light dark; }
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }

    @media only screen and (max-width: 620px) {
      .email-wrapper { padding: 8px 0 !important; }
      .email-card    { border-radius: 0 !important; }
      .email-header  { padding: 24px 20px !important; }
      .email-body    { padding: 24px 18px !important; }
      .email-footer  { padding: 18px !important; }
      .subj-title    { font-size: 17px !important; }
      .resp-box      { padding: 16px 14px !important; }
      .btn-wrap      { width: 100% !important; }
      .btn-link      { display: block !important; width: 100% !important; text-align: center !important; box-sizing: border-box !important; padding: 14px 20px !important; }
    }

    @media (prefers-color-scheme: dark) {
      body, .email-wrapper { background-color: #111827 !important; }
      .email-card          { background-color: #1f2937 !important; border-color: #374151 !important; }
      .email-header        { background-color: #0d1b2e !important; border-bottom-color: #2563eb !important; }
      .email-body          { background-color: #1f2937 !important; }
      .email-footer        { background-color: #111827 !important; border-top-color: #374151 !important; }
      .footer-text         { color: #6b7280 !important; }
      .subj-title          { color: #f9fafb !important; }
      .subj-sub            { color: #9ca3af !important; }
      .divider-line        { border-top-color: #374151 !important; }
      .citizen-greet       { color: #e5e7eb !important; }
      .citizen-name        { color: #93c5fd !important; }
      .resp-box            { background-color: #111827 !important; border-color: #374151 !important; }
      .resp-content        { color: #e5e7eb !important; }
      .ref-cell            { background-color: #374151 !important; border-color: #4b5563 !important; color: #d1d5db !important; }
      .ref-num             { color: #93c5fd !important; }
      .notice-box          { background-color: #1e3a5f !important; border-color: #2563eb !important; color: #bfdbfe !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;color:#334155;">

  <table width="100%" cellpadding="0" cellspacing="0" border="0" class="email-wrapper" style="background-color:#f1f5f9;padding:32px 12px;">
    <tr>
      <td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" class="email-card" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 4px 20px rgba(0,0,0,0.07);">

          <!-- HEADER: centered, matching original screenshot -->
          <tr>
            <td class="email-header" style="background:#1e3a5f;padding:28px 36px;text-align:center;border-bottom:3px solid #2563eb;">
              <div style="font-size:22px;font-weight:800;letter-spacing:0.08em;color:#ffffff;text-transform:uppercase;margin:0 0 10px;">
                {$appHtml}
              </div>
              <span style="display:inline-block;background:rgba(37,99,235,0.25);border:1px solid rgba(96,165,250,0.45);border-radius:20px;padding:4px 16px;font-size:10px;font-weight:700;color:#93c5fd;letter-spacing:0.1em;text-transform:uppercase;">
                Official Response
              </span>
            </td>
          </tr>

          <!-- BODY -->
          <tr>
            <td class="email-body" style="padding:32px 36px 28px;background-color:#ffffff;">

              {$refBadgeHtml}

              <h2 class="subj-title" style="margin:0 0 6px;font-size:20px;font-weight:700;color:#0f1e3d;line-height:1.35;word-break:break-word;">
                {$subjHtml}
              </h2>
              <p class="subj-sub" style="margin:0 0 20px;font-size:13px;color:#64748b;">
                An official response has been issued regarding your submission.
              </p>

              <hr class="divider-line" style="border:none;border-top:1px solid #e2e8f0;margin:0 0 22px;">

              <p class="citizen-greet" style="margin:0 0 16px;font-size:15px;color:#1e293b;line-height:1.6;">
                Dear <strong class="citizen-name" style="color:#0f1e3d;">{$nameHtml}</strong>,
              </p>

              <div class="resp-box" style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px 22px;margin:0 0 24px;">
                <div class="resp-content" style="margin:0;font-size:14px;line-height:1.8;color:#334155;white-space:pre-wrap;word-break:break-word;">{$bodyHtml}</div>
              </div>


              <!-- CTA -->
              <table cellpadding="0" cellspacing="0" border="0" class="btn-wrap" style="margin:0 0 26px;">
                <tr>
                  <td align="center" style="background-color:#2563eb;border-radius:8px;box-shadow:0 2px 8px rgba(37,99,235,0.3);">
                    <a href="{$trackUrl}" target="_blank" class="btn-link"
                       style="display:inline-block;padding:13px 30px;color:#ffffff;font-size:13px;font-weight:700;text-decoration:none;letter-spacing:0.04em;text-transform:uppercase;">
                      Track Your Submission &rarr;
                    </a>
                  </td>
                </tr>
              </table>

              <div class="notice-box" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:12px 16px;font-size:12px;color:#1e40af;line-height:1.5;">
                <strong>Need help?</strong> Log in to the Citizen Portal to track progress, submit follow-ups, or view resolution records.
              </div>

            </td>
          </tr>

          <!-- FOOTER -->
          <tr>
            <td class="email-footer" style="background-color:#f8fafc;border-top:1px solid #e2e8f0;padding:20px 36px;text-align:center;">
              <p class="footer-text" style="margin:0 0 4px;font-size:11px;color:#94a3b8;line-height:1.6;">
                {$fullHtml}<br>
                City Government of San Jose del Monte &middot; Bulacan, Philippines
              </p>
              <p class="footer-text" style="margin:0;font-size:10px;color:#cbd5e1;">
                &copy; {$year} {$appHtml}. Automated notification &mdash; do not reply.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * Build the HTML email body for citizen submission confirmation.
     */

    private static function buildSubmissionReceivedEmailHtml(
        string $referenceNumber,
        string $subject,
        string $citizenName = 'Citizen'
    ): string {
        $appName   = setting('brand_short_name');
        $fullHtml  = htmlspecialchars(setting('brand_full_name'), ENT_QUOTES, 'UTF-8');
        $appUrl    = app_url();
        $trackUrl  = $appUrl ? rtrim($appUrl, '/') . '/citizen/track?ref=' . urlencode($referenceNumber) : '#';
        $year      = date('Y');
        $nameHtml  = htmlspecialchars($citizenName ?: 'Citizen', ENT_QUOTES, 'UTF-8');
        $subjHtml  = htmlspecialchars($subject,     ENT_QUOTES, 'UTF-8');
        $appHtml   = htmlspecialchars($appName,     ENT_QUOTES, 'UTF-8');
        $refHtml   = htmlspecialchars($referenceNumber, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="color-scheme" content="light dark">
  <meta name="supported-color-schemes" content="light dark">
  <title>Submission Received — {$refHtml}</title>
  <style type="text/css">
    :root {
      color-scheme: light dark;
      supported-color-schemes: light dark;
    }
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
    
    @media only screen and (max-width: 620px) {
      .email-wrapper { padding: 12px 6px !important; }
      .email-card { width: 100% !important; border-radius: 10px !important; }
      .email-header { padding: 22px 20px !important; }
      .email-body { padding: 24px 18px !important; }
      .email-footer { padding: 20px 18px !important; }
      .header-title { font-size: 18px !important; }
      .header-badge-cell { display: block !important; width: 100% !important; text-align: left !important; margin-top: 10px !important; }
      .subj-title { font-size: 18px !important; line-height: 1.35 !important; }
      .btn-container { width: 100% !important; }
      .btn-link { display: block !important; width: 100% !important; text-align: center !important; box-sizing: border-box !important; padding: 14px 20px !important; }
    }

    @media (prefers-color-scheme: dark) {
      body, .email-wrapper { background-color: #0b1120 !important; }
      .email-card { background-color: #1e293b !important; border-color: #334155 !important; box-shadow: 0 4px 20px rgba(0,0,0,0.5) !important; }
      .email-header { background-color: #030712 !important; border-bottom-color: #3b82f6 !important; }
      .email-body { background-color: #1e293b !important; color: #e2e8f0 !important; }
      .email-footer { background-color: #0f172a !important; border-top-color: #334155 !important; }
      .footer-title { color: #cbd5e1 !important; }
      .footer-text { color: #94a3b8 !important; }
      .subj-title { color: #f8fafc !important; }
      .subj-sub { color: #94a3b8 !important; }
      .ref-box { background-color: #0f172a !important; border-color: #334155 !important; }
      .ref-code { color: #60a5fa !important; }
      .detail-table { background-color: #0f172a !important; border-color: #334155 !important; }
      .detail-td-label { color: #94a3b8 !important; border-bottom-color: #1e293b !important; }
      .detail-td-val { color: #f8fafc !important; border-bottom-color: #1e293b !important; }
      .steps-box { background-color: #0f172a !important; border-color: #334155 !important; color: #cbd5e1 !important; }
      .steps-title { color: #f8fafc !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;color:#334155;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" class="email-wrapper" style="background-color:#f1f5f9;padding:36px 12px;width:100% !important;">
    <tr>
      <td align="center">
        <!-- Container Card -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" class="email-card" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(15,30,61,0.08);border:1px solid #e2e8f0;">
          
          <!-- Header -->
          <tr>
            <td class="email-header" style="background:#0f1e3d;padding:28px 36px;text-align:left;border-bottom:3px solid #2563eb;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td valign="middle">
                    <div class="header-title" style="font-size:20px;font-weight:800;letter-spacing:0.04em;color:#ffffff;line-height:1.2;">
                      {$appHtml}
                    </div>
                    <div style="font-size:11px;font-weight:600;letter-spacing:0.08em;color:#93c5fd;text-transform:uppercase;margin-top:4px;">
                      City of San Jose del Monte &middot; Citizen Portal
                    </div>
                  </td>
                  <td class="header-badge-cell" align="right" valign="middle">
                    <span style="display:inline-block;background:rgba(5,150,105,0.2);border:1px solid rgba(52,211,153,0.4);border-radius:20px;padding:4px 12px;font-size:11px;font-weight:600;color:#6ee7b7;letter-spacing:0.04em;text-transform:uppercase;white-space:nowrap;">
                      Received
                    </span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Main Content -->
          <tr>
            <td class="email-body" style="padding:36px 36px 28px;background-color:#ffffff;">
              
              <h2 class="subj-title" style="margin:0 0 8px;font-size:20px;font-weight:700;color:#0f1e3d;line-height:1.35;">
                We've received your submission!
              </h2>
              <p class="subj-sub" style="margin:0 0 24px;font-size:14px;color:#64748b;">
                Thank you for reaching out to the City Government of San Jose del Monte.
              </p>

              <!-- Reference Number Card -->
              <div class="ref-box" style="background-color:#f8fafc;border:1px solid #cbd5e1;border-radius:10px;padding:18px 20px;margin:0 0 24px;text-align:center;">
                <p style="margin:0 0 6px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">
                  Your Tracking Reference Number
                </p>
                <div class="ref-code" style="font-family:'Roboto Mono','Courier New',monospace;font-size:22px;font-weight:700;color:#2563eb;letter-spacing:0.08em;margin:0 0 6px;">
                  {$refHtml}
                </div>
                <p style="margin:0;font-size:12px;color:#64748b;">
                  Please keep this reference number to check real-time progress and updates.
                </p>
              </div>

              <!-- Submission Info Details -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0" class="detail-table" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;margin:0 0 28px;">
                <tr>
                  <td class="detail-td-label" style="padding:12px 16px;border-bottom:1px solid #f1f5f9;font-size:12px;font-weight:600;color:#64748b;width:120px;">
                    Citizen Name:
                  </td>
                  <td class="detail-td-val" style="padding:12px 16px;border-bottom:1px solid #f1f5f9;font-size:13px;font-weight:600;color:#0f1e3d;">
                    {$nameHtml}
                  </td>
                </tr>
                <tr>
                  <td class="detail-td-label" style="padding:12px 16px;font-size:12px;font-weight:600;color:#64748b;">
                    Subject:
                  </td>
                  <td class="detail-td-val" style="padding:12px 16px;font-size:13px;font-weight:500;color:#334155;">
                    {$subjHtml}
                  </td>
                </tr>
              </table>

              <!-- CTA Button (Touch Friendly) -->
              <table cellpadding="0" cellspacing="0" border="0" class="btn-container" style="margin:0 0 28px;">
                <tr>
                  <td align="center" style="background-color:#2563eb;border-radius:8px;box-shadow:0 2px 6px rgba(37,99,235,0.3);">
                    <a href="{$trackUrl}" target="_blank" class="btn-link"
                       style="display:inline-block;padding:13px 28px;color:#ffffff;font-size:13px;font-weight:700;text-decoration:none;letter-spacing:0.04em;text-transform:uppercase;">
                      Track Submission Status &rarr;
                    </a>
                  </td>
                </tr>
              </table>

              <!-- What's next note -->
              <div class="steps-box" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;">
                <p class="steps-title" style="margin:0 0 8px;font-size:12px;font-weight:700;color:#0f1e3d;text-transform:uppercase;letter-spacing:0.04em;">
                  What Happens Next?
                </p>
                <ol style="margin:0;padding-left:18px;font-size:12px;color:#475569;line-height:1.6;">
                  <li>Our team reviews and validates your submission.</li>
                  <li>Our staff review it and take action.</li>
                  <li>You will receive an email update once official response or resolution is posted.</li>
                </ol>
              </div>

            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td class="email-footer" style="background-color:#f8fafc;border-top:1px solid #e2e8f0;padding:24px 36px;text-align:center;">
              <p class="footer-title" style="margin:0 0 6px;font-size:12px;font-weight:600;color:#475569;">
                {$fullHtml}
              </p>
              <p class="footer-text" style="margin:0 0 10px;font-size:11px;color:#94a3b8;">
                City Government of San Jose del Monte &middot; Bulacan, Philippines
              </p>
              <p class="footer-text" style="margin:0;font-size:11px;color:#94a3b8;line-height:1.5;">
                &copy; {$year} {$appHtml}. All rights reserved.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * Build the HTML email body for 2FA login OTP.
     */
    private static function buildOtpEmailHtml(string $code): string
    {
        $appName = setting('brand_short_name');
        $year    = date('Y');
        $codeHtml = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $appHtml  = htmlspecialchars($appName, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="color-scheme" content="light dark">
  <meta name="supported-color-schemes" content="light dark">
  <title>Your {$appHtml} Verification Code</title>
  <style type="text/css">
    :root {
      color-scheme: light dark;
      supported-color-schemes: light dark;
    }
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
    @media only screen and (max-width: 520px) {
      .email-wrapper { padding: 12px 6px !important; }
      .email-card { width: 100% !important; border-radius: 10px !important; }
      .email-header { padding: 20px 16px !important; }
      .email-body { padding: 24px 16px !important; }
      .otp-code { font-size: 26px !important; letter-spacing: 0.18em !important; }
    }
    @media (prefers-color-scheme: dark) {
      body, .email-wrapper { background-color: #0b1120 !important; }
      .email-card { background-color: #1e293b !important; border-color: #334155 !important; box-shadow: 0 4px 20px rgba(0,0,0,0.5) !important; }
      .email-header { background-color: #030712 !important; border-bottom-color: #3b82f6 !important; }
      .email-body { background-color: #1e293b !important; color: #e2e8f0 !important; }
      .email-footer { background-color: #0f172a !important; border-top-color: #334155 !important; }
      .otp-title { color: #f8fafc !important; }
      .otp-sub { color: #94a3b8 !important; }
      .otp-box { background-color: #0f172a !important; border-color: #3b82f6 !important; }
      .otp-code { color: #60a5fa !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;color:#334155;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" class="email-wrapper" style="background-color:#f1f5f9;padding:32px 12px;width:100% !important;">
    <tr>
      <td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" class="email-card" style="max-width:500px;width:100%;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(15,30,61,0.08);border:1px solid #e2e8f0;">
          <tr>
            <td class="email-header" style="background:#0f1e3d;padding:24px 32px;text-align:center;border-bottom:3px solid #2563eb;">
              <div style="font-size:20px;font-weight:800;letter-spacing:0.04em;color:#ffffff;">
                {$appHtml}
              </div>
              <div style="font-size:11px;font-weight:600;letter-spacing:0.08em;color:#93c5fd;text-transform:uppercase;margin-top:4px;">
                Two-Factor Authentication
              </div>
            </td>
          </tr>
          <tr>
            <td class="email-body" style="padding:32px 32px 24px;text-align:center;background-color:#ffffff;">
              <h2 class="otp-title" style="margin:0 0 10px;font-size:18px;font-weight:700;color:#0f1e3d;">
                Your Verification Code
              </h2>
              <p class="otp-sub" style="margin:0 0 24px;font-size:13px;color:#64748b;">
                Use the one-time password below to complete your staff login.
              </p>
              <div class="otp-box" style="background-color:#f8fafc;border:2px dashed #93c5fd;border-radius:10px;padding:16px 24px;display:inline-block;margin:0 0 24px;">
                <span class="otp-code" style="font-family:'Roboto Mono','Courier New',monospace;font-size:32px;font-weight:700;letter-spacing:0.25em;color:#0f1e3d;">
                  {$codeHtml}
                </span>
              </div>
              <p style="margin:0 0 8px;font-size:12px;color:#dc2626;font-weight:600;">
                This code will expire in 5 minutes.
              </p>
              <p style="margin:0;font-size:11px;color:#94a3b8;">
                If you did not request this login, please notify your system administrator immediately.
              </p>
            </td>
          </tr>
          <tr>
            <td class="email-footer" style="background-color:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 32px;text-align:center;">
              <p style="margin:0;font-size:11px;color:#94a3b8;">
                &copy; {$year} {$appHtml}. Automated security notification.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    public static function notifySubmissionReceived(
        string $toEmail,
        string $referenceNumber,
        string $subject,
        string $citizenName = 'Citizen'
    ): bool {
        $emailSubject = "We've received your submission — {$referenceNumber}";
        $body = "Thank you for contacting " . (setting('brand_short_name')) . ".\n\n"
              . "Reference Number: {$referenceNumber}\n"
              . "Subject: {$subject}\n\n"
              . "Your submission is currently being reviewed by our team. You can check its status "
              . "anytime using your reference number at the Track a Submission page.\n\n"
              . "Please keep this reference number for your records — you'll need it to track progress.";
        $htmlBody = self::buildSubmissionReceivedEmailHtml($referenceNumber, $subject, $citizenName);

        if (self::isDevMode()) {
            logger()->info("[CEPFMS][DEV EMAIL] To: {$toEmail} | Subject: {$emailSubject}");
            return true;
        }
        return self::sendViaSmtp($toEmail, $emailSubject, $body, $htmlBody);
    }

    private static function sendViaSmtp(string $to, string $subject, string $body, string $htmlBody = ''): bool
    {
        try {
            if ($htmlBody !== '') {
                Mail::html($htmlBody, function ($message) use ($to, $subject) {
                    $message->to($to)->subject($subject);
                });
            } else {
                Mail::raw($body, function ($message) use ($to, $subject) {
                    $message->to($to)->subject($subject);
                });
            }
            return true;
        } catch (Throwable $e) {
            logger()->error('[CEPFMS][EMAIL] Sending to ' . $to . ' ("' . $subject . '") failed: ' . $e->getMessage());
            return false;
        }
    }
}
