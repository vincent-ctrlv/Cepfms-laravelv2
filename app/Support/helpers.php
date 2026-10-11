<?php

/**
 * CEPFMS — shared helper functions.
 *
 * Ported from the original app's app/helpers/functions.php onto Laravel:
 * sessions, CSRF, routing and the database connection come from the
 * framework; the business rules (rate limits, role checks, formatting) are
 * kept exactly as they were.
 */

use App\Support\JsonAbort;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------
// Database / URLs
// ---------------------------------------------------------------------

/**
 * The shared PDO connection (Laravel's own). Default fetch mode is
 * associative arrays (see config/database.php), matching the original app,
 * so raw report queries read the same way they always did.
 */
function db(): PDO
{
    return DB::connection()->getPdo();
}

/**
 * Same-origin path for CSS, JS, images and in-app links, honouring a
 * subdirectory install. Example: app_href('admin/dashboard') → /admin/dashboard
 */
function app_href(string $path = ''): string
{
    $base = rtrim(request()->getBasePath(), '/');
    $path = ltrim($path, '/');
    return $path === '' ? ($base === '' ? '/' : $base . '/') : $base . '/' . $path;
}

/** Public asset URL with a file-modified-time cache buster. */
function asset_v(string $path): string
{
    $file = public_path(ltrim($path, '/'));
    return app_href($path) . '?v=' . (is_file($file) ? filemtime($file) : '0');
}

/** Absolute URL (scheme + host + base path), for links that leave the browser, e.g. emails. */
function app_url(string $path = ''): string
{
    $root = rtrim(config('app.url') ?: '', '/');
    if (app()->runningInConsole() === false) {
        $root = rtrim(request()->getSchemeAndHttpHost() . request()->getBasePath(), '/');
    }
    return $root . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function clean_str($value): string
{
    return trim(strip_tags((string) $value));
}

// ---------------------------------------------------------------------
// JSON API responses
// ---------------------------------------------------------------------

/** The {success, data|message} envelope every JSON endpoint uses. */
function json_response(bool $success, $data = null, ?string $message = null, int $httpCode = 200, array $headers = []): JsonResponse
{
    $payload = ['success' => $success];
    if ($success) {
        $payload['data'] = $data ?? new stdClass();
        if ($message !== null) {
            $payload['message'] = $message;
        }
    } else {
        $payload['message'] = $message ?? 'Unable to process request.';
    }
    return response()->json($payload, $httpCode, $headers);
}

/** Stop the request right here with a JSON error (thrown, so it works from helpers too). */
function json_fail(string $message, int $httpCode = 422, array $extra = [], array $headers = []): never
{
    throw new JsonAbort(response()->json(['success' => false, 'message' => $message] + $extra, $httpCode, $headers));
}

/** 429 carrying how long to wait, so the page can show a live countdown. */
function json_rate_limited(string $message, int $retryAfter): never
{
    json_fail($message, 429, ['retry_after' => $retryAfter], ['Retry-After' => (string) $retryAfter]);
}

// ---------------------------------------------------------------------
// Flash messages (one-time session messages, e.g. after a redirect)
// ---------------------------------------------------------------------
function flash_set(string $type, string $message): void
{
    $messages = session('_cepfms_flash', []);
    $messages[] = ['type' => $type, 'message' => $message];
    session(['_cepfms_flash' => $messages]);
}

function flash_get(): array
{
    return session()->pull('_cepfms_flash', []);
}

// ---------------------------------------------------------------------
// Auth helpers
// ---------------------------------------------------------------------
function is_staff_logged_in(): bool
{
    return session()->has('staff_id');
}

/** The signed-in staff row (array), or null. Cached for the request. */
function current_staff(): ?array
{
    if (!is_staff_logged_in()) {
        return null;
    }
    $cache = app()->bound('cepfms.staff') ? app('cepfms.staff') : false;
    if ($cache === false) {
        $row = DB::table('users')
            ->select('id', 'full_name', 'email', 'role', 'title', 'must_change_password')
            ->where('id', session('staff_id'))->where('is_active', 1)->first();
        $cache = $row ? (array) $row : null;
        if (!$cache) {
            // Account deactivated mid-session: clear it, but keep the session
            // itself so the redirect's flash message still shows.
            session()->forget(['staff_id', '_last_activity']);
            session()->migrate(true);
        }
        app()->instance('cepfms.staff', $cache);
    }
    return $cache;
}

/** Drop the per-request staff cache (after login/logout or a profile edit). */
function forget_current_staff(): void
{
    app()->forgetInstance('cepfms.staff');
}

/** Roles with admin privileges. A super admin can do everything an admin can. */
function role_is_admin(?string $role): bool
{
    return in_array($role, ['admin', 'super_admin'], true);
}

function is_admin(): bool
{
    $staff = current_staff();
    return !empty($staff) && role_is_admin($staff['role']);
}

function is_super_admin(): bool
{
    $staff = current_staff();
    return !empty($staff) && $staff['role'] === 'super_admin';
}

/** Human label for a role value, e.g. for badges and activity log entries. */
function role_label(?string $role): string
{
    return ['super_admin' => 'Super Admin', 'admin' => 'Admin'][$role] ?? 'Admin';
}

/**
 * Whether signing in needs the emailed code too. Super admins always do;
 * everyone else unless they turned it off.
 */
function two_factor_required(array $user): bool
{
    return ($user['role'] ?? '') === 'super_admin' || (int) ($user['two_factor_enabled'] ?? 1) === 1;
}

/** Temporary password: no 0/O/1/l/I, to avoid mix-ups when read from an email. */
function temp_password(int $length = 12): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $password;
}

// ---------------------------------------------------------------------
// Dashboard stats
// ---------------------------------------------------------------------

/** Week-over-week percentage change in row count for any WHERE clause. */
function trend_pct(PDO $pdo, string $from, string $where = '1=1', string $dateCol = 'created_at'): int
{
    $baseWhere = $where === '1=1' ? '' : "AND ($where)";
    $thisWeek = (int) $pdo->query(
        "SELECT COUNT(*) FROM $from WHERE $dateCol >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) $baseWhere"
    )->fetchColumn();
    $lastWeek = (int) $pdo->query(
        "SELECT COUNT(*) FROM $from WHERE $dateCol >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND $dateCol < DATE_SUB(CURDATE(), INTERVAL 7 DAY) $baseWhere"
    )->fetchColumn();
    if ($lastWeek > 0) return (int) round((($thisWeek - $lastWeek) / $lastWeek) * 100);
    return $thisWeek > 0 ? 100 : 0;
}

// ---------------------------------------------------------------------
// Reference numbers: FB-#### / CM-#### / PS-####
// ---------------------------------------------------------------------
function generate_reference_number(string $submissionType): string
{
    $prefix = ['feedback' => 'FB', 'complaint' => 'CM', 'proposal' => 'PS'][$submissionType] ?? 'FB';

    // Random (not sequential) so reference numbers can't be enumerated — the
    // public tracker looks submissions up by reference number alone.
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $candidate = sprintf('%s-%d', $prefix, random_int(1000, 9999));
        if (!DB::table('submissions')->where('reference_number', $candidate)->exists()) {
            return $candidate;
        }
        usleep(50000);
    }

    // Last-resort fallback: timestamp-based suffix, still unique.
    return sprintf('%s-%s', $prefix, substr((string) time(), -6));
}

// ---------------------------------------------------------------------
// Activity logging
// ---------------------------------------------------------------------
function log_activity(?int $staffId, string $action, string $entity, ?int $entityId, array $metadata = []): void
{
    DB::table('activity_log')->insert([
        'staff_id'  => $staffId,
        'action'    => $action,
        'entity'    => $entity,
        'entity_id' => $entityId,
        'metadata'  => json_encode($metadata),
    ]);
}

// ---------------------------------------------------------------------
// Formatting helpers
// ---------------------------------------------------------------------
function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

function status_badge_class(string $status): string
{
    $map = [
        'Pending'          => 'badge-slate',
        'Received'         => 'badge-blue',
        'In Review'        => 'badge-amber',
        'Assigned'         => 'badge-teal',
        'Escalated'        => 'badge-red',
        'Resolved'         => 'badge-green',
        'On Track'         => 'badge-green',
        'At Risk'          => 'badge-amber',
        'Breached'         => 'badge-red',
        'Met'              => 'badge-green',
        'Approved'         => 'badge-green',
        'Rejected'         => 'badge-red',
        'Flagged'          => 'badge-amber',
        'Under Review'     => 'badge-amber',
        'Implemented'      => 'badge-teal',
        'Draft'            => 'badge-slate',
        'Pending Approval' => 'badge-amber',
        'Sent'             => 'badge-green',
        'Overdue'          => 'badge-red',
    ];
    return $map[$status] ?? 'badge-slate';
}

/** Why an admin may reject a submission in moderation (key stored in moderation_queue.reject_reason). */
function moderation_reject_reasons(): array
{
    return [
        'Spam'             => 'Meaningless text, advertisements, or test entries.',
        'Duplicate'        => 'The same concern was already sent.',
        'Incomplete'       => 'Important details are missing or the concern is not clear.',
        'Invalid'          => 'The information is false or the photos are not related to the concern.',
        'Inappropriate'    => 'Contains offensive, abusive, or vulgar words.',
        'Outside coverage' => 'Not within San Jose del Monte or not for the Legislative Services Office, such as emergencies.',
    ];
}

// ---------------------------------------------------------------------
// Login brute-force rate limiting
// ---------------------------------------------------------------------

/** True if $identifier has made too many attempts of $type within the window. Check BEFORE verifying. */
function login_rate_limited(string $identifier, string $type, int $maxAttempts = 5, int $windowMinutes = 15): bool
{
    try {
        $count = DB::scalar(
            'SELECT COUNT(*) FROM login_attempts
             WHERE identifier = ? AND attempt_type = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$identifier, $type, $windowMinutes]
        );
        return (int) $count >= $maxAttempts;
    } catch (Throwable $e) {
        logger()->error('[CEPFMS] login_rate_limited failed: ' . $e->getMessage());
        return false;
    }
}

/** Seconds until a rate-limited $identifier drops back under $maxAttempts. */
function login_retry_after(string $identifier, string $type, int $maxAttempts = 5, int $windowMinutes = 15): int
{
    try {
        $seconds = DB::scalar(
            'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(created_at, INTERVAL ? MINUTE)))
             FROM login_attempts
             WHERE identifier = ? AND attempt_type = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
             ORDER BY created_at DESC
             LIMIT 1 OFFSET ' . max(0, $maxAttempts - 1),
            [$windowMinutes, $identifier, $type, $windowMinutes]
        );
        return $seconds === null ? $windowMinutes * 60 : max(1, (int) $seconds);
    } catch (Throwable $e) {
        logger()->error('[CEPFMS] login_retry_after failed: ' . $e->getMessage());
        return $windowMinutes * 60;
    }
}

/** Record a failed attempt so it counts toward the rate limit above. */
function record_login_attempt(string $identifier, string $type): void
{
    try {
        DB::table('login_attempts')->insert(['identifier' => $identifier, 'attempt_type' => $type]);
    } catch (Throwable $e) {
        logger()->error('[CEPFMS] record_login_attempt failed: ' . $e->getMessage());
    }
}

/** Clear an identifier's attempt history for $type — call on a successful step. */
function clear_login_attempts(string $identifier, string $type): void
{
    try {
        DB::table('login_attempts')->where('identifier', $identifier)->where('attempt_type', $type)->delete();
    } catch (Throwable $e) {
        logger()->error('[CEPFMS] clear_login_attempts failed: ' . $e->getMessage());
    }
}

/**
 * Best-effort caller IP for rate limiting. Checks X-Forwarded-For first since
 * most hosts sit behind a reverse proxy where REMOTE_ADDR is the proxy.
 */
function client_ip(): string
{
    $forwarded = (string) request()->server('HTTP_X_FORWARDED_FOR', '');
    if ($forwarded !== '') {
        return trim(explode(',', $forwarded)[0]);
    }
    return (string) (request()->server('REMOTE_ADDR') ?? 'unknown');
}

// ---------------------------------------------------------------------
// Citizen submission rate limiting (5 / 15min, 20 / 24h per IP)
// ---------------------------------------------------------------------
function submission_rate_limited(string $ip): bool
{
    try {
        $last15 = (int) DB::scalar(
            'SELECT COUNT(*) FROM submission_rate_limits WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
            [$ip]
        );
        if ($last15 >= 5) {
            return true;
        }
        $last24h = (int) DB::scalar(
            'SELECT COUNT(*) FROM submission_rate_limits WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)',
            [$ip]
        );
        return $last24h >= 20;
    } catch (Throwable $e) {
        logger()->error('[CEPFMS] submission_rate_limited check failed: ' . $e->getMessage());
        return false;
    }
}

function record_submission_attempt(string $ip): void
{
    try {
        DB::table('submission_rate_limits')->insert(['ip_address' => $ip]);
        // Prune entries older than 48 hours, probabilistically.
        if (random_int(1, 100) === 1) {
            DB::delete('DELETE FROM submission_rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)');
        }
    } catch (Throwable $e) {
        logger()->error('[CEPFMS] record_submission_attempt failed: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// System settings (key/value `settings` table) and branding
// ---------------------------------------------------------------------

/** Branding defaults: every key falls back to the value the site shipped with. */
const BRANDING_DEFAULTS = [
    'brand_short_name' => 'CEPFMS',
    'brand_full_name'  => 'Citizen Engagement & Public Feedback Management System',
    'brand_government' => 'City Government of San Jose del Monte',
    'brand_office'     => 'Legislative Services Office',
    'brand_logo'       => '', // empty = the bundled city seal
    'contact_address'  => 'City Hall, San Jose del Monte',
    'contact_phone'    => '(044) 815-1000',
    'contact_email'    => 'legislative.services@sjdm.gov.ph',
    'contact_hours'    => 'Mon–Fri, 8:00 AM – 5:00 PM',
    'email_signoff'    => 'CEPFMS Team',
];

const BRAND_DEFAULT_LOGO = 'frontend/images/site/city-seal.png';
const BRAND_LOGO_DIR = 'uploads/branding/';

/** All stored settings, read once per request. Empty if the table can't be read. */
function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        try {
            foreach (DB::table('settings')->get() as $row) {
                $cache[$row->setting_key] = (string) $row->setting_value;
            }
        } catch (Throwable $e) {
            logger()->error('[CEPFMS] Reading settings failed: ' . $e->getMessage());
        }
    }
    return $cache;
}

/** One setting: the stored value if set, otherwise its default. */
function setting(string $key): string
{
    $stored = settings_all()[$key] ?? '';
    return $stored !== '' ? $stored : (BRANDING_DEFAULTS[$key] ?? '');
}

/** Save several settings at once (insert or update). */
function settings_save(array $values): void
{
    foreach ($values as $key => $value) {
        DB::table('settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => $value]);
    }
    settings_all(true);
}

/** URL of the current logo, versioned so browsers pick up a new upload. */
function brand_logo_url(): string
{
    $path = setting('brand_logo');
    if ($path === '' || !is_file(public_path($path))) {
        $path = BRAND_DEFAULT_LOGO;
    }
    return asset_v($path);
}
