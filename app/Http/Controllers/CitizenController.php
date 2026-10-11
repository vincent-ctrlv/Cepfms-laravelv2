<?php

namespace App\Http\Controllers;

use App\Services\AIClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public citizen portal: home, submission form, success page, tracker, and
 * the legal pages. No login.
 */
class CitizenController extends Controller
{
    public function home()
    {
        return view('citizen.home', ['pageTitle' => 'Home']);
    }

    public function submit(Request $request)
    {
        $type = $request->query('type', '');
        return view('citizen.submit', [
            'pageTitle'  => 'Submit Feedback',
            'prefType'   => in_array($type, ['feedback', 'complaint', 'proposal'], true) ? $type : '',
            // Optional on the form; left empty, the AI decides.
            'categories' => AIClassifier::CATEGORIES,
            'priorities' => [
                'Low'      => 'Low — can wait',
                'Medium'   => 'Medium — needs attention soon',
                'High'     => 'High — urgent',
                'Critical' => 'Critical — danger to people or property',
            ],
        ]);
    }

    public function success(Request $request)
    {
        $ref = preg_replace('/[^A-Z0-9\-]/', '', strtoupper((string) $request->query('ref', '')));
        $submission = $ref ? DB::table('submissions')
            ->select('reference_number', 'submission_type', 'subject', 'created_at')
            ->where('reference_number', $ref)->first() : null;

        if (!$submission) {
            return redirect(app_href('citizen'));
        }

        return view('citizen.success', [
            'pageTitle'  => 'Submission Received',
            'submission' => (array) $submission,
        ]);
    }

    public function track(Request $request)
    {
        $ref = strtoupper(trim((string) $request->query('ref', '')));
        $submission = null;
        $notFound = false;

        if ($ref !== '') {
            $row = DB::selectOne("
                SELECT s.id, s.reference_number, s.submission_type, s.subject, s.status, s.moderation_status, s.created_at, s.updated_at,
                       (SELECT MAX(COALESCE(r.sent_at, r.updated_at)) FROM responses r WHERE r.submission_id = s.id AND r.status = 'Sent') AS responded_at,
                       (SELECT m.reject_reason FROM moderation_queue m WHERE m.submission_id = s.id AND m.status = 'Rejected' ORDER BY m.reviewed_at DESC LIMIT 1) AS reject_reason,
                       (SELECT m.reject_note FROM moderation_queue m WHERE m.submission_id = s.id AND m.status = 'Rejected' ORDER BY m.reviewed_at DESC LIMIT 1) AS reject_note
                FROM submissions s
                WHERE s.reference_number = ?
            ", [$ref]);
            $submission = $row ? (array) $row : null;
            $notFound = !$submission;
        }

        // Map DB statuses onto the public 5-stage tracker.
        $stageOrder = ['Submitted', 'Received', 'In Review', 'Assigned', 'Resolved'];
        $statusToStage = [
            'Pending'   => 'Submitted',
            'Received'  => 'Received',
            'In Review' => 'In Review',
            'Assigned'  => 'Assigned',
            'Escalated' => 'Assigned',
            'Resolved'  => 'Resolved',
        ];
        $currentStage = $submission ? ($statusToStage[$submission['status']] ?? 'Submitted') : null;
        $currentIndex = $currentStage ? array_search($currentStage, $stageOrder, true) : -1;
        // Rejected in moderation: it never enters the workflow, so say so
        // instead of showing a tracker stuck on "Submitted".
        $notAccepted = $submission && ($submission['moderation_status'] ?? '') === 'Rejected';
        $rejectReason = $notAccepted ? ($submission['reject_reason'] ?? null) : null;
        $rejectHint = $rejectReason ? (moderation_reject_reasons()[$rejectReason] ?? null) : null;
        $rejectNote = $notAccepted ? ($submission['reject_note'] ?? null) : null;

        // When each stage was reached, from what's already recorded: creation,
        // the moderation approval, and staff status changes in the activity
        // log. If a stage was entered more than once, the latest time wins.
        $stageTimes = [];
        if ($submission && !$notAccepted) {
            $stageTimes['Submitted'] = $submission['created_at'];

            $approvedAt = DB::scalar(
                "SELECT MAX(reviewed_at) FROM moderation_queue WHERE submission_id = ? AND status = 'Approved'",
                [$submission['id']]
            );
            if ($approvedAt) $stageTimes['Received'] = $approvedAt;

            $logs = DB::select("
                SELECT action, metadata, created_at FROM activity_log
                WHERE entity = 'submission' AND entity_id = ?
                  AND action IN ('Status changed', 'Complaint closed', 'Proposal implemented', 'Submission assigned', 'Complaint escalated')
                ORDER BY created_at, id
            ", [$submission['id']]);
            // The last two actions only appear on older records.
            $actionStatus = ['Complaint closed' => 'Resolved', 'Proposal implemented' => 'Resolved', 'Submission assigned' => 'Assigned', 'Complaint escalated' => 'Escalated'];
            foreach ($logs as $log) {
                $newStatus = $log->action === 'Status changed'
                    ? (json_decode($log->metadata ?? '', true)['new_status'] ?? null)
                    : $actionStatus[$log->action];
                $stage = $statusToStage[$newStatus] ?? null;
                if ($stage) $stageTimes[$stage] = $log->created_at;
            }
        }

        return view('citizen.track', [
            'pageTitle' => 'Track Submission',
        ] + compact(
            'ref', 'submission', 'notFound', 'stageOrder', 'statusToStage', 'currentStage', 'currentIndex',
            'notAccepted', 'rejectReason', 'rejectHint', 'rejectNote', 'stageTimes'
        ));
    }

    public function privacy()
    {
        return view('citizen.privacy', ['pageTitle' => 'Privacy Policy']);
    }

    public function terms()
    {
        return view('citizen.terms', ['pageTitle' => 'Terms of Service']);
    }
}
