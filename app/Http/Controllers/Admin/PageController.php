<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AIClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Staff/admin portal pages. Role checks happen in the `staff` route
 * middleware; most pages are shells whose tables load from the JSON API.
 */
class PageController extends Controller
{
    public function dashboard()
    {
        $staff = $this->staff();
        $pdo = db();

        $isAdmin = role_is_admin($staff['role']);

        // ---- KPIs ---------------------------------------------------------------
        // "Total Submissions" counts everything received (including items still
        // awaiting moderation) — it's the overall pipeline volume. The module-specific
        // counts below only reflect approved (moderation-cleared) items, since those
        // are the only ones staff can actually see and act on in those modules.
        $totalSubmissions  = (int) $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
        $totalProposals    = submissions_count("submission_type = 'proposal'");
        $openComplaints    = submissions_count("submission_type = 'complaint' AND status != 'Resolved'");
        // Moderation is an admin-only action (see require_admin() on the moderation
        // page/API), so the KPI is only meaningful — and only shown — to admins.
        $awaitingModeration= $isAdmin ? (int) $pdo->query("SELECT COUNT(*) FROM moderation_queue WHERE status='Pending'")->fetchColumn() : 0;
        $pendingResponses  = (int) $pdo->query("SELECT COUNT(*) FROM responses WHERE status IN ('Draft','Pending Approval')")->fetchColumn();
        // Resolution Rate is computed over moderation-approved submissions only, same
        // as the Analytics page and the public homepage (see app/models/Submissions.php).
        $resolutionRateData = submissions_resolution_rate();
        $resolutionRate     = $resolutionRateData['rate'];

        // ---- Week-over-week trends for ALL KPIs ----------------------------------
        $submissionsTrend   = trend_pct($pdo, 'submissions');
        $proposalsTrend     = trend_pct($pdo, 'submissions', "submission_type='proposal' AND moderation_status='Approved'");
        $complaintsTrend    = trend_pct($pdo, 'submissions', "submission_type='complaint' AND moderation_status='Approved' AND status!='Resolved'");
        $moderationTrend    = $isAdmin ? trend_pct($pdo, 'moderation_queue', "status='Pending'") : 0;
        $responsesTrend     = trend_pct(
            $pdo,
            'responses r JOIN submissions s ON s.id = r.submission_id',
            "r.status IN ('Draft','Pending Approval')",
            'r.created_at'
        );

        // Resolution rate trend: compare this week vs last week (over approved submissions).
        $thisWeekResolved   = submissions_count("status='Resolved' AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        $lastWeekResolved   = submissions_count("status='Resolved' AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND updated_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        $resolutionTrend    = $lastWeekResolved > 0
            ? (int) round((($thisWeekResolved - $lastWeekResolved) / $lastWeekResolved) * 100)
            : ($thisWeekResolved > 0 ? 100 : 0);

        $kpis = [
            [
                'key'    => 'total_submissions',
                'label'  => 'Total Submissions',
                'value'  => $totalSubmissions,
                'color'  => 'navy',
                'trend'  => $submissionsTrend,
                'href'   => 'submissions',
                'icon'   => '<path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
                // For total submissions, up = neutral; for complaints, up = bad
                'trend_positive_is_good' => true,
            ],
            [
                'key'    => 'proposals',
                'label'  => 'Proposals',
                'value'  => $totalProposals,
                'color'  => 'teal',
                'trend'  => $proposalsTrend,
                'href'   => 'proposals',
                'icon'   => '<path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>',
                'trend_positive_is_good' => true,
            ],
            [
                'key'    => 'open_complaints',
                'label'  => 'Open Complaints',
                'value'  => $openComplaints,
                'color'  => 'red',
                'trend'  => $complaintsTrend,
                'href'   => 'complaints',
                'icon'   => '<path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
                // Fewer open complaints = better
                'trend_positive_is_good' => false,
            ],
            [
                'key'    => 'awaiting_moderation',
                'label'  => 'Awaiting Moderation',
                'value'  => $awaitingModeration,
                'color'  => 'amber',
                'trend'  => $moderationTrend,
                'href'   => 'moderation',
                'icon'   => '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
                // Fewer awaiting = better
                'trend_positive_is_good' => false,
            ],
            [
                'key'    => 'pending_responses',
                'label'  => 'Pending Responses',
                'value'  => $pendingResponses,
                'color'  => 'blue',
                'trend'  => $responsesTrend,
                'href'   => 'responses',
                'icon'   => '<path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>',
                // Fewer pending = better
                'trend_positive_is_good' => false,
            ],
            [
                'key'    => 'resolution_rate',
                'label'  => 'Resolution Rate',
                'value'  => $resolutionRate . '%',
                'color'  => 'teal',
                'trend'  => $resolutionTrend,
                'href'   => 'analytics',
                'icon'   => '<path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
                // Higher resolution rate = better
                'trend_positive_is_good' => true,
            ],
        ];

        // Non-admin staff can't act on moderation, so drop the card entirely rather
        // than show a KPI that links to a page they'll be bounced from.
        if (!$isAdmin) {
            $kpis = array_values(array_filter($kpis, fn($k) => $k['key'] !== 'awaiting_moderation'));
        }

        // ---- Submission type breakdown for donut chart --------------------------
        $feedbackCount  = submissions_count("submission_type = 'feedback'");
        $complaintCount = submissions_count("submission_type = 'complaint'");
        $proposalCount  = submissions_count("submission_type = 'proposal'");
        $donutTotal     = max(1, $feedbackCount + $complaintCount + $proposalCount);

        // ---- Submissions This Week: daily counts for the last 7 days, by type -----
        // Counts everything received (like the Total Submissions KPI). Days come from
        // MySQL's CURDATE() so they line up with how created_at was stamped.
        $today = $pdo->query("SELECT CURDATE()")->fetchColumn();
        $weekRows = $pdo->query("
            SELECT DATE(created_at) AS day,
                   SUM(submission_type = 'feedback')  AS feedback,
                   SUM(submission_type = 'complaint') AS complaint,
                   SUM(submission_type = 'proposal')  AS proposal
            FROM submissions
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(created_at)
        ")->fetchAll(PDO::FETCH_UNIQUE);
        $weekDays = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("$today -$i days"));
            $r = $weekRows[$day] ?? [];
            $weekDays[] = [
                'day'       => $day,
                'feedback'  => (int) ($r['feedback'] ?? 0),
                'complaint' => (int) ($r['complaint'] ?? 0),
                'proposal'  => (int) ($r['proposal'] ?? 0),
            ];
        }
        $weekTotal = array_sum(array_map(fn($d) => $d['feedback'] + $d['complaint'] + $d['proposal'], $weekDays));
        $lastWeekTotal = (int) $pdo->query("
            SELECT COUNT(*) FROM submissions
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        ")->fetchColumn();

        // ---- Needs Attention: things that actually require staff action today ----
        // (Replaces the old "Module Status" panel which just repeated KPI numbers.)
        $needsAttention = [];

        // Unresolved urgent complaints: Critical first, then High, oldest first within each.
        $urgentComplaints = $pdo->query("
            SELECT s.reference_number, s.subject, s.priority, s.created_at
            FROM submissions s
            JOIN complaints c ON c.submission_id = s.id
            WHERE s.priority IN ('Critical', 'High') AND s.status != 'Resolved' AND s.moderation_status = 'Approved'
            ORDER BY FIELD(s.priority, 'Critical', 'High'), s.created_at ASC LIMIT 6
        ")->fetchAll();
        foreach ($urgentComplaints as $c) {
            $isCritical = $c['priority'] === 'Critical';
            $needsAttention[] = [
                'type'     => $isCritical ? 'Critical Complaint' : 'High Priority',
                'severity' => $isCritical ? 'red' : 'amber',
                'text'     => e($c['reference_number']) . ' — ' . e($c['subject']),
                'sub'      => 'Opened ' . time_ago($c['created_at']) . ' · not yet resolved',
                'href'     => 'complaints',
            ];
        }

        $overdueResponses = $pdo->query("
            SELECT r.subject, s.reference_number, r.created_at
            FROM responses r
            JOIN submissions s ON s.id = r.submission_id
            WHERE r.status = 'Overdue'
            ORDER BY r.created_at ASC LIMIT 4
        ")->fetchAll();
        foreach ($overdueResponses as $r) {
            $needsAttention[] = [
                'type'     => 'Response Overdue',
                'severity' => 'amber',
                'text'     => e($r['reference_number']) . ' — ' . e($r['subject']),
                'sub'      => 'Awaiting response · Due ' . time_ago($r['created_at']),
                'href'     => 'responses',
            ];
        }

        $staleModeration = $isAdmin ? (int) $pdo->query("
            SELECT COUNT(*) FROM moderation_queue WHERE status = 'Pending' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ")->fetchColumn() : 0;
        if ($staleModeration > 0) {
            $needsAttention[] = [
                'type'     => 'Moderation Backlog',
                'severity' => 'amber',
                'text'     => "$staleModeration item(s) pending review for over 24 hours",
                'sub'      => 'Citizens are waiting on these',
                'href'     => 'moderation',
            ];
        }

        $needsAttentionCount = count($needsAttention);

        // ---- Recent activity ------------------------------------------------------
        // Filtered by role (see app/models/ActivityLog.php). Logins and other security
        // events are not shown here; super admins see them on the Audit Log page.
        $recentActivity = activity_feed_for($staff);

        $data = get_defined_vars();
        unset($data['pdo']);
        return view('admin.dashboard', ['pageTitle' => 'Dashboard', 'activeNav' => 'dashboard'] + $data);
    }

    public function submissions()
    {
        return $this->page('admin.submissions', 'Feedback Submission', 'submissions', ['categories' => AIClassifier::CATEGORIES]);
    }

    public function complaints()
    {
        return $this->page('admin.complaints', 'Complaints & Issues', 'complaints');
    }

    public function proposals()
    {
        return $this->page('admin.proposals', 'Proposals & Suggestions', 'proposals');
    }

    public function moderation()
    {
        return $this->page('admin.moderation', 'Moderation & Validation', 'moderation');
    }

    public function responses()
    {
        return $this->page('admin.responses', 'Response Management', 'responses');
    }

    public function analytics()
    {
        return $this->page('admin.analytics', 'Engagement Analytics', 'analytics');
    }

    /** Any staff member edits their own name; only a super admin also edits their position. */
    public function profile()
    {
        $staff = $this->staff();
        $account = (array) (DB::table('users')
            ->select('role', 'last_login_at', 'created_at', 'two_factor_enabled')
            ->where('id', $staff['id'])->first() ?? []);

        return $this->page('admin.profile', 'Profile Settings', 'profile', [
            'canEditPosition' => $staff['role'] === 'super_admin',
            'account'         => $account,
            'twoFactorOn'     => two_factor_required($account + ['role' => $staff['role']]),
            'twoFactorLocked' => $staff['role'] === 'super_admin',
        ]);
    }

    /** Super admin: list user accounts and create new ones. */
    public function accounts()
    {
        $accounts = array_map(fn ($row) => (array) $row, DB::select(
            'SELECT id, full_name, email, role, title, is_active, must_change_password, last_login_at, created_at, two_factor_enabled
             FROM users ORDER BY is_active DESC, created_at DESC'
        ));
        return $this->page('admin.staff', 'User Accounts', 'staff', compact('accounts'));
    }

    /**
     * Super admin: logins, logouts, password events, account changes and data
     * exports, plus an optional view of all work activity (?scope=work|all).
     */
    public function auditLog(Request $request)
    {
        $scope = (string) $request->query('scope', 'security');
        if (!array_key_exists($scope, ACTIVITY_AUDIT_SCOPES)) {
            $scope = 'security';
        }

        $perPage = 8;
        $total = activity_audit_count($scope);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($totalPages, max(1, (int) $request->query('page', 1)));
        $events = activity_audit_log($scope, $perPage, ($page - 1) * $perPage);

        return $this->page('admin.audit-log', 'Audit Log', 'audit', compact('scope', 'perPage', 'total', 'totalPages', 'page', 'events'));
    }

    /** Super admin: branding (names, logo, contact details, reply sign-off). */
    public function settings()
    {
        $brand = [];
        foreach (array_keys(BRANDING_DEFAULTS) as $key) {
            $brand[$key] = setting($key);
        }
        $hasCustomLogo = $brand['brand_logo'] !== '' && is_file(public_path($brand['brand_logo']));

        return $this->page('admin.settings', 'System Settings', 'settings', compact('brand', 'hasCustomLogo'));
    }

    private function page(string $view, string $title, string $nav, array $data = [])
    {
        return view($view, ['pageTitle' => $title, 'activeNav' => $nav, 'staff' => $this->staff()] + $data);
    }
}
