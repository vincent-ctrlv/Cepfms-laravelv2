<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ProtectedPdf;

/**
 * Dashboard: live KPI refresh and the protected PDF export.
 */
class DashboardController extends Controller
{
    public function kpis(Request $request)
    {
        /**
         * API: Live KPI stats for Admin Dashboard auto-refresh.
         * Returns current counts and trends for all 6 KPI cards.
         */
                $staff = $this->staff();

        $pdo = db();

        $isAdmin = role_is_admin($staff['role']);

        $totalSubmissions = (int) $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
        $totalProposals = submissions_count("submission_type = 'proposal'");
        $openComplaints = submissions_count("submission_type = 'complaint' AND status != 'Resolved'");
        $pendingResponses = (int) $pdo->query("SELECT COUNT(*) FROM responses WHERE status IN ('Draft','Pending Approval')")->fetchColumn();
        $resolutionRateData = submissions_resolution_rate();
        $resolutionRate     = $resolutionRateData['rate'];

        $submissionsTrend = trend_pct($pdo, 'submissions');
        $proposalsTrend   = trend_pct($pdo, 'submissions', "submission_type='proposal' AND moderation_status='Approved'");
        $complaintsTrend  = trend_pct($pdo, 'submissions', "submission_type='complaint' AND moderation_status='Approved' AND status!='Resolved'");
        $responsesTrend   = trend_pct(
            $pdo,
            'responses r JOIN submissions s ON s.id = r.submission_id',
            "r.status IN ('Draft','Pending Approval')",
            'r.created_at'
        );

        $thisWeekResolved = submissions_count("status='Resolved' AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        $lastWeekResolved = submissions_count("status='Resolved' AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND updated_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        $resolutionTrend  = $lastWeekResolved > 0
            ? (int) round((($thisWeekResolved - $lastWeekResolved) / $lastWeekResolved) * 100)
            : ($thisWeekResolved > 0 ? 100 : 0);

        $kpis = [
            [
                'key' => 'total_submissions',
                'value' => $totalSubmissions,
                'trend' => $submissionsTrend,
                'trend_positive_is_good' => true,
            ],
            [
                'key' => 'proposals',
                'value' => $totalProposals,
                'trend' => $proposalsTrend,
                'trend_positive_is_good' => true,
            ],
            [
                'key' => 'open_complaints',
                'value' => $openComplaints,
                'trend' => $complaintsTrend,
                'trend_positive_is_good' => false,
            ],
        ];

        // Moderation is admin-only, so this KPI is only shown to (and refreshed for) admins.
        if ($isAdmin) {
            $awaitingModeration = (int) $pdo->query("SELECT COUNT(*) FROM moderation_queue WHERE status='Pending'")->fetchColumn();
            $moderationTrend    = trend_pct($pdo, 'moderation_queue', "status='Pending'");
            $kpis[] = [
                'key' => 'awaiting_moderation',
                'value' => $awaitingModeration,
                'trend' => $moderationTrend,
                'trend_positive_is_good' => false,
            ];
        }

        $kpis[] = [
            'key' => 'pending_responses',
            'value' => $pendingResponses,
            'trend' => $responsesTrend,
            'trend_positive_is_good' => false,
        ];
        $kpis[] = [
            'key' => 'resolution_rate',
            'value' => $resolutionRate . '%',
            'trend' => $resolutionTrend,
            'trend_positive_is_good' => true,
        ];

        return json_response(true, [
            'kpis' => $kpis,
            'updated_at' => date('h:i:s A'),
        ]);
    }

    public function pdf(Request $request)
    {
        /**
         * Password-protected PDF of the Dashboard, as it looks on screen
         * (see app/services/ProtectedPdf.php for how the file is built and protected).
         *
         * POST JSON: { purpose: 'export' | 'print', images: [dataURL, ...] }
         */
                $staff = $this->staff();

        $input = $this->input();
        $forPrint = ($input['purpose'] ?? '') === 'print';
        $sections = ProtectedPdf::sections($input);
        $fileName = 'dashboard-' . date('Y-m-d') . '.pdf';

        // Logged under 'analytics' so it shows with the other exports in the Audit Log's security view.
        return ProtectedPdf::send($staff, 'Dashboard', $fileName, $sections, $forPrint, function () use ($staff, $forPrint) {
            log_activity((int) $staff['id'], $forPrint ? 'Dashboard printed' : 'Dashboard exported', 'analytics', null, []);
        });
    }
}
