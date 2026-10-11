<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Where a submission is on the public 5-stage tracker (Submitted → Received →
 * In Review → Assigned → Resolved), shared by the Track page and the citizen
 * portal so both always say the same thing.
 */
class SubmissionProgress
{
    public const STAGES = ['Submitted', 'Received', 'In Review', 'Assigned', 'Resolved'];

    public const DESCRIPTIONS = [
        'Submitted' => "We've received your report and it's in queue for review.",
        'Received'  => 'Confirmed and logged into our system.',
        'In Review' => 'Our staff are evaluating the details.',
        'Assigned'  => 'A staff member is working on it.',
        'Resolved'  => 'Addressed and closed out.',
    ];

    // The badge follows the public stage, not the raw DB status (e.g. old "Escalated" rows read as Assigned).
    public const BADGES = [
        'Submitted' => 'badge-slate',
        'Received'  => 'badge-blue',
        'In Review' => 'badge-amber',
        'Assigned'  => 'badge-teal',
        'Resolved'  => 'badge-green',
    ];

    private const STATUS_TO_STAGE = [
        'Pending'   => 'Submitted',
        'Received'  => 'Received',
        'In Review' => 'In Review',
        'Assigned'  => 'Assigned',
        'Escalated' => 'Assigned',
        'Resolved'  => 'Resolved',
    ];

    /** Public stage for a DB status. */
    public static function stage(?string $status): string
    {
        return self::STATUS_TO_STAGE[$status] ?? 'Submitted';
    }

    /**
     * Short status for lists and cards: [label, badge class, percent done].
     * Needs `status` and `moderation_status`.
     */
    public static function summary(array $submission): array
    {
        if (($submission['moderation_status'] ?? '') === 'Rejected') {
            return ['Not accepted', 'badge-red', 0];
        }
        $stage = self::stage($submission['status'] ?? null);
        $index = (int) array_search($stage, self::STAGES, true);
        return [$stage, self::BADGES[$stage], (int) round(($index + 1) / count(self::STAGES) * 100)];
    }

    /**
     * Everything the progress timeline needs. $submission must have id, status,
     * moderation_status and created_at; reject_reason / reject_note when rejected.
     */
    public static function timeline(array $submission): array
    {
        $currentStage = self::stage($submission['status'] ?? null);
        $currentIndex = (int) array_search($currentStage, self::STAGES, true);

        // Rejected in moderation: it never enters the workflow, so say so
        // instead of showing a tracker stuck on "Submitted".
        $notAccepted = ($submission['moderation_status'] ?? '') === 'Rejected';
        $rejectReason = $notAccepted ? ($submission['reject_reason'] ?? null) : null;
        $rejectHint = $rejectReason ? (moderation_reject_reasons()[$rejectReason] ?? null) : null;
        $rejectNote = $notAccepted ? ($submission['reject_note'] ?? null) : null;

        // When each stage was reached, from what's already recorded: creation,
        // the moderation approval, and staff status changes in the activity
        // log. If a stage was entered more than once, the latest time wins.
        $stageTimes = [];
        if (!$notAccepted) {
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
                $stage = self::STATUS_TO_STAGE[$newStatus] ?? null;
                if ($stage) $stageTimes[$stage] = $log->created_at;
            }
        }

        return [
            'stageOrder'        => self::STAGES,
            'stageDescriptions' => self::DESCRIPTIONS,
            'stageBadge'        => self::BADGES,
            'currentStage'      => $currentStage,
            'currentIndex'      => $currentIndex,
            'notAccepted'       => $notAccepted,
            'rejectReason'      => $rejectReason,
            'rejectHint'        => $rejectHint,
            'rejectNote'        => $rejectNote,
            'stageTimes'        => $stageTimes,
        ];
    }
}
