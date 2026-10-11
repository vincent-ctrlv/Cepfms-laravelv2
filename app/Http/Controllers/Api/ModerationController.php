<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Throwable;

/**
 * Moderation & Validation queue (admins): list and approve/reject.
 */
class ModerationController extends Controller
{
    public function list(Request $request)
    {
        $q = $request->query();
        $pdo = db();
        $type = trim($q['type'] ?? '');
        $status = trim($q['status'] ?? 'Pending');
        $page = max(1, (int) ($q['page'] ?? 1));
        $perPage = 8;

        $where = [];
        $params = [];
        if ($type !== '') { $where[] = 'm.content_type = ?'; $params[] = $type; }
        if ($status === 'Pending') {
            // Old "Flagged" items (escalated before Escalate was removed) still await a decision.
            $where[] = "m.status IN ('Pending', 'Flagged')";
        } elseif ($status !== '') { $where[] = 'm.status = ?'; $params[] = $status; }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM moderation_queue m $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $page = min($page, max(1, (int) ceil($total / $perPage)));
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT m.id, m.content_type, m.submission_id, m.content_snapshot, m.flag, m.spam_score, m.spam_flags, m.status, m.reject_reason, m.reject_note, m.created_at,
                   c.full_name AS citizen_name, c.email AS citizen_email, c.phone AS citizen_phone,
                   s.reference_number, s.subject, s.description, s.category, s.priority, s.city, s.barangay, s.street
            FROM moderation_queue m
            LEFT JOIN citizens c ON c.id = m.citizen_id
            LEFT JOIN submissions s ON s.id = m.submission_id
            $whereSql
            ORDER BY m.created_at DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);
        return json_response(true, ['rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    public function action(Request $request)
    {
        $staff = $this->staff();

        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        $action = $input['action'] ?? '';
        if (!$id || !$action) json_fail('Missing required fields.', 422);

        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM moderation_queue WHERE id = ?');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) json_fail('Moderation item not found.', 404);

        $statusMap = [
            'approve'  => 'Approved',
            'reject'   => 'Rejected',
        ];

        if (!isset($statusMap[$action])) json_fail('Unknown action.', 422);

        $newStatus = $statusMap[$action];

        // A rejection must say why (one of the fixed reasons); the note is optional.
        $rejectReason = null;
        $rejectNote = null;
        if ($action === 'reject') {
            $rejectReason = is_string($input['reason'] ?? null) ? trim($input['reason']) : '';
            if (!array_key_exists($rejectReason, moderation_reject_reasons())) {
                json_fail('Choose a reason for rejecting this submission.', 422);
            }
            $rejectNote = is_string($input['note'] ?? null) ? trim($input['note']) : '';
            if (mb_strlen($rejectNote) > 255) json_fail('Keep the note under 255 characters.', 422);
            if ($rejectNote === '') $rejectNote = null;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE moderation_queue SET status = ?, reject_reason = ?, reject_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
                ->execute([$newStatus, $rejectReason, $rejectNote, $staff['id'], $id]);

            // Approving/rejecting is what actually gates whether the linked submission
            // becomes visible in the Feedback/Complaints/Proposals modules, and (on
            // approval) advances the citizen-facing tracker from "Submitted" to "Received".
            if ($item['submission_id']) {
                if ($action === 'approve') {
                    $pdo->prepare("
                        UPDATE submissions
                        SET moderation_status = 'Approved',
                            status = IF(status = 'Pending', 'Received', status)
                        WHERE id = ?
                    ")->execute([$item['submission_id']]);
                } elseif ($action === 'reject') {
                    $pdo->prepare("UPDATE submissions SET moderation_status = 'Rejected' WHERE id = ?")
                        ->execute([$item['submission_id']]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            logger()->error('[CEPFMS] Moderation action failed: ' . $e->getMessage());
            json_fail('Unable to process this action. Please try again.', 500);
        }

        $meta = ['content_type' => $item['content_type']];
        if ($rejectReason) $meta['reason'] = $rejectReason;
        log_activity($staff['id'], 'Moderation ' . str_replace('_', ' ', $action), 'moderation_queue', $id, $meta);

        return json_response(true, ['id' => $id, 'status' => $newStatus]);
    }
}
