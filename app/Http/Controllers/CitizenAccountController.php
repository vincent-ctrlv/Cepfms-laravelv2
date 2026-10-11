<?php

namespace App\Http\Controllers;

use App\Support\CitizenAuth;
use App\Support\SubmissionProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

/**
 * Citizen accounts: sign in with Google, verify identity with a government
 * ID, and see your own submissions.
 */
class CitizenAccountController extends Controller
{
    public function login()
    {
        if (CitizenAuth::check()) {
            return redirect(app_href(CitizenAuth::isVerified() ? 'citizen/dashboard' : 'citizen/verify'));
        }
        return view('citizen.login', [
            'pageTitle'       => 'Sign In',
            'flashes'         => flash_get(),
            'googleAvailable' => self::googleConfigured(),
        ]);
    }

    /** Off to Google's account chooser. */
    public function redirectToGoogle()
    {
        if (!self::googleConfigured()) {
            logger()->warning('[CEPFMS] Citizen Google sign-in attempted, but GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET are not set.');
            flash_set('warning', 'Signing in with Google is not available yet. Please try again later.');
            return redirect(app_href('citizen/login'));
        }
        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /** Back from Google: find or create the citizen's account and sign them in. */
    public function googleCallback(Request $request)
    {
        if ($request->query('error')) {
            flash_set('warning', 'Sign-in was cancelled.');
            return redirect(app_href('citizen/login'));
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            flash_set('warning', 'Your sign-in took too long or was opened in another tab. Please try again.');
            return redirect(app_href('citizen/login'));
        } catch (Throwable $e) {
            logger()->error('[CEPFMS] Google sign-in failed: ' . $e->getMessage());
            flash_set('warning', 'We could not sign you in with Google right now. Please try again shortly.');
            return redirect(app_href('citizen/login'));
        }

        $sub = (string) $google->getId();
        $email = mb_strtolower(trim((string) $google->getEmail()));
        $emailVerified = filter_var($google->user['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($sub === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$emailVerified) {
            flash_set('warning', 'Your Google account needs a verified email address to sign in here.');
            return redirect(app_href('citizen/login'));
        }

        // The Google account itself, or else an existing citizen record with the same
        // (Google-verified) email from before accounts existed.
        $citizen = DB::table('citizens')->where('google_sub', $sub)->first()
            ?? DB::table('citizens')->whereNull('google_sub')->where('email', $email)->orderBy('id')->first();

        if ($citizen && !$citizen->is_active) {
            flash_set('warning', 'This account has been suspended. Please contact ' . setting('contact_email') . ' for help.');
            return redirect(app_href('citizen/login'));
        }

        if ($citizen) {
            DB::table('citizens')->where('id', $citizen->id)->update([
                'google_sub' => $sub, 'email' => $email, 'last_login_at' => now(),
            ]);
            $citizenId = (int) $citizen->id;
        } else {
            $name = trim((string) $google->getName()) ?: strstr($email, '@', true);
            $citizenId = (int) DB::table('citizens')->insertGetId([
                'full_name' => mb_substr($name, 0, 150), 'email' => $email,
                'google_sub' => $sub, 'last_login_at' => now(),
            ]);
        }

        CitizenAuth::login($citizenId);

        $intended = (string) session()->pull('citizen_intended', '');
        $safeIntended = str_starts_with($intended, '/') && !str_starts_with($intended, '//') ? $intended : null;
        if (!CitizenAuth::isVerified()) {
            return redirect(app_href('citizen/verify'));
        }
        return redirect($safeIntended ?? app_href('citizen/dashboard'));
    }

    public function logout()
    {
        CitizenAuth::logout();
        flash_set('success', 'You have been signed out.');
        return redirect(app_href('citizen/login'));
    }

    /** Portal home: verification status, counts, recent submissions and replies. */
    public function dashboard()
    {
        $citizen = CitizenAuth::current();

        $counts = (array) DB::selectOne("
            SELECT COUNT(*) AS total,
                   COALESCE(SUM(moderation_status = 'Pending'), 0) AS reviewing,
                   COALESCE(SUM(moderation_status = 'Approved' AND status <> 'Resolved'), 0) AS in_progress,
                   COALESCE(SUM(moderation_status = 'Approved' AND status = 'Resolved'), 0) AS resolved,
                   COALESCE(SUM(moderation_status = 'Rejected'), 0) AS not_accepted
            FROM submissions WHERE citizen_id = ?
        ", [$citizen['id']]);

        $recent = DB::table('submissions')
            ->select('reference_number', 'submission_type', 'subject', 'status', 'moderation_status', 'created_at', 'updated_at')
            ->where('citizen_id', $citizen['id'])
            ->orderByDesc('created_at')->limit(5)->get()
            ->map(fn ($row) => (array) $row)->all();

        $replies = $this->sentReplies()
            ->where('s.citizen_id', $citizen['id'])
            ->orderByDesc('r.sent_at')->limit(3)->get()
            ->map(fn ($row) => (array) $row)->all();

        return view('citizen.portal.dashboard', [
            'pageTitle'    => 'My Dashboard',
            'portalNav'    => 'dashboard',
            'citizen'      => $citizen,
            'status'       => CitizenAuth::status(),
            'verification' => CitizenAuth::verification(),
            'counts'       => array_map('intval', $counts),
            'recent'       => $recent,
            'replies'      => $replies,
        ]);
    }

    /** Verification status, and the ID form when there's nothing pending or approved. */
    public function verify()
    {
        return view('citizen.verify', [
            'pageTitle'    => 'Verify Your Identity',
            'portalNav'    => 'verify',
            'citizen'      => CitizenAuth::current(),
            'verification' => CitizenAuth::verification(),
            'status'       => CitizenAuth::status(),
            'idTypes'      => config('cepfms.verification.id_types'),
        ]);
    }

    /** All of the citizen's submissions, filterable by where they are. */
    public function mySubmissions(Request $request)
    {
        $citizen = CitizenAuth::current();
        $filters = [
            ''             => 'All',
            'reviewing'    => 'Waiting for review',
            'in_progress'  => 'In progress',
            'resolved'     => 'Resolved',
            'not_accepted' => 'Not accepted',
        ];
        $filter = array_key_exists((string) $request->query('filter', ''), $filters) ? (string) $request->query('filter', '') : '';

        $query = DB::table('submissions')->where('citizen_id', $citizen['id']);
        match ($filter) {
            'reviewing'    => $query->where('moderation_status', 'Pending'),
            'in_progress'  => $query->where('moderation_status', 'Approved')->where('status', '<>', 'Resolved'),
            'resolved'     => $query->where('moderation_status', 'Approved')->where('status', 'Resolved'),
            'not_accepted' => $query->where('moderation_status', 'Rejected'),
            default        => null,
        };

        $perPage = 10;
        $total = (clone $query)->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($totalPages, max(1, (int) $request->query('page', 1)));

        $submissions = $query
            ->select('reference_number', 'submission_type', 'subject', 'status', 'moderation_status', 'created_at')
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $perPage)->limit($perPage)->get()
            ->map(fn ($row) => (array) $row)->all();

        return view('citizen.portal.submissions', [
            'pageTitle'   => 'My Submissions',
            'portalNav'   => 'submissions',
        ] + compact('citizen', 'filters', 'filter', 'submissions', 'total', 'page', 'totalPages'));
    }

    /** One of the citizen's own submissions: details, photos, progress and official replies. */
    public function submission(string $reference)
    {
        $citizen = CitizenAuth::current();
        $row = DB::selectOne("
            SELECT s.id, s.reference_number, s.submission_type, s.subject, s.description, s.category,
                   s.status, s.moderation_status, s.barangay, s.street, s.city, s.created_at, s.updated_at,
                   (SELECT m.reject_reason FROM moderation_queue m WHERE m.submission_id = s.id AND m.status = 'Rejected' ORDER BY m.reviewed_at DESC LIMIT 1) AS reject_reason,
                   (SELECT m.reject_note FROM moderation_queue m WHERE m.submission_id = s.id AND m.status = 'Rejected' ORDER BY m.reviewed_at DESC LIMIT 1) AS reject_note
            FROM submissions s
            WHERE s.reference_number = ? AND s.citizen_id = ?
        ", [strtoupper($reference), $citizen['id']]);

        // Someone else's (or no such) submission: same answer either way.
        abort_if(!$row, 404);
        $submission = (array) $row;

        $photos = DB::table('attachments')->where('submission_id', $submission['id'])
            ->orderBy('id')->pluck('file_path')->all();

        $replies = $this->sentReplies()
            ->where('r.submission_id', $submission['id'])
            ->orderBy('r.sent_at')->get()
            ->map(fn ($r) => (array) $r)->all();

        return view('citizen.portal.submission', [
            'pageTitle'  => $submission['reference_number'],
            'portalNav'  => 'submissions',
        ] + compact('submission', 'photos', 'replies') + SubmissionProgress::timeline($submission));
    }

    public function profile()
    {
        $citizen = CitizenAuth::current();
        $account = (array) DB::table('citizens')->select('created_at', 'last_login_at')->where('id', $citizen['id'])->first();

        return view('citizen.portal.profile', [
            'pageTitle'    => 'Profile',
            'portalNav'    => 'profile',
            'citizen'      => $citizen,
            'account'      => $account,
            'status'       => CitizenAuth::status(),
            'verification' => CitizenAuth::verification(),
        ]);
    }

    /**
     * Replies the office has actually sent (never drafts or internal notes), signed
     * by the office rather than the individual staff member.
     */
    private function sentReplies()
    {
        return DB::table('responses as r')
            ->join('submissions as s', 's.id', '=', 'r.submission_id')
            ->where('r.status', 'Sent')
            ->select('r.id', 'r.subject', 'r.body', DB::raw('COALESCE(r.sent_at, r.updated_at) AS sent_at'), 's.reference_number', 's.subject AS submission_subject');
    }

    private static function googleConfigured(): bool
    {
        return (string) config('services.google.client_id') !== '' && (string) config('services.google.client_secret') !== '';
    }
}
