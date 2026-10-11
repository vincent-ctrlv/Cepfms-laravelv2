<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PDO;
use App\Services\EmailService;

/**
 * Response Management: drafts, the approval step, and sending replies to citizens.
 */
class ResponseController extends Controller
{
    public function list(Request $request)
    {
        $q = $request->query();
        $pdo = db();
        $status = trim($q['status'] ?? '');

        $where = [];
        $params = [];
        if ($status !== '') { $where[] = 'r.status = ?'; $params[] = $status; }
        // Optional: one submission's reply thread (Response Management composer).
        $submissionId = (int) ($q['submission_id'] ?? 0);
        if ($submissionId > 0) { $where[] = 'r.submission_id = ?'; $params[] = $submissionId; }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmt = $pdo->prepare("
            SELECT r.id, r.subject, r.body, r.status, r.due_at, r.sent_at, r.created_at,
                   s.reference_number, s.subject AS submission_subject,
                   c.full_name AS citizen_name, st.full_name AS staff_name
            FROM responses r
            JOIN submissions s ON s.id = r.submission_id
            LEFT JOIN citizens c ON c.id = s.citizen_id
            LEFT JOIN users st ON st.id = r.staff_id
            $whereSql
            ORDER BY r.created_at DESC
        ");
        $stmt->execute($params);
        return json_response(true, ['rows' => $stmt->fetchAll()]);
    }

    public function submissionsForResponse(Request $request)
    {
        $q = $request->query();
        /**
         * API: List approved submissions with their response counts
         * Used by the Response Composer split-panel UI
         */

        $pdo     = db();
        $search  = trim($q['search'] ?? '');
        $filter  = trim($q['filter'] ?? 'all');
        $page    = max(1, (int) ($q['page'] ?? 1));
        $perPage = 8;

        $where  = ["s.moderation_status = 'Approved'"];
        $params = [];

        if ($search !== '') {
            $where[]  = '(s.subject LIKE ? OR s.reference_number LIKE ? OR c.full_name LIKE ?)';
            $like     = "%$search%";
            array_push($params, $like, $like, $like);
        }

        if ($filter === 'needs_response') {
            $where[] = 'NOT EXISTS (SELECT 1 FROM responses r WHERE r.submission_id = s.id)';
        } elseif ($filter === 'responded') {
            $where[] = 'EXISTS (SELECT 1 FROM responses r WHERE r.submission_id = s.id)';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM submissions s LEFT JOIN citizens c ON c.id = s.citizen_id $whereSql"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT s.id, s.reference_number, s.subject, s.description, s.status, s.submission_type, s.created_at,
                   c.full_name AS citizen_name, c.email AS citizen_email,
                   COUNT(r.id) AS response_count
            FROM submissions s
            LEFT JOIN citizens c ON c.id = s.citizen_id
            LEFT JOIN responses r ON r.submission_id = s.id
            $whereSql
            GROUP BY s.id, s.reference_number, s.subject, s.description, s.status, s.submission_type, s.created_at,
                     c.full_name, c.email
            ORDER BY s.created_at DESC
            LIMIT $perPage OFFSET $offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Live counts for tabs
        $statsStmt = $pdo->query("
            SELECT 
                COUNT(*) AS total_all,
                COALESCE(SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM responses r WHERE r.submission_id = s.id) THEN 1 ELSE 0 END), 0) AS total_needs_response
            FROM submissions s
            WHERE s.moderation_status = 'Approved'
        ");
        $statsRow = $statsStmt ? $statsStmt->fetch(PDO::FETCH_ASSOC) : null;
        $counts = [
            'all'            => (int) ($statsRow['total_all'] ?? 0),
            'needs_response' => (int) ($statsRow['total_needs_response'] ?? 0),
        ];

        return json_response(true, [
            'rows'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
            'filter'      => $filter,
            'counts'      => $counts,
        ]);
    }

    public function create(Request $request)
    {
        $staff = $this->staff();

        $input = $this->input();
        $referenceNumber = strtoupper(trim($input['reference_number'] ?? ''));
        $subject = clean_str($input['subject'] ?? '');
        $body = clean_str($input['body'] ?? '');
        $internalNotes = clean_str($input['internal_notes'] ?? '');
        $saveAction = $input['save_action'] ?? 'draft'; // draft | pending_approval
        // Optional: a status template was used, so the submission moves to that status too.
        $newStatus = (string) ($input['status'] ?? '');

        if ($referenceNumber === '' || $subject === '' || $body === '') {
            json_fail('Reference number, subject, and response body are required.', 422);
        }
        if ($newStatus !== '' && !in_array($newStatus, ['Received', 'In Review', 'Assigned', 'Resolved'], true)) {
            json_fail('Invalid status.', 422);
        }

        $pdo = db();
        $sub = $pdo->prepare('SELECT id, status FROM submissions WHERE reference_number = ?');
        $sub->execute([$referenceNumber]);
        $submission = $sub->fetch();
        if (!$submission) json_fail('No submission found with that reference number.', 404);
        $submissionId = (int) $submission['id'];

        $status = $saveAction === 'pending_approval' ? 'Pending Approval' : 'Draft';

        $ins = $pdo->prepare('INSERT INTO responses (submission_id, staff_id, subject, body, internal_notes, status, due_at) VALUES (?,?,?,?,?,?,?)');
        $ins->execute([$submissionId, $staff['id'], $subject, $body, $internalNotes ?: null, $status, date('Y-m-d H:i:s', strtotime('+2 days'))]);
        $responseId = (int) $pdo->lastInsertId();

        log_activity($staff['id'], $status === 'Draft' ? 'Response drafted' : 'Response submitted for approval', 'response', $responseId, ['reference' => $referenceNumber]);

        // Same as "Update status" on the Submissions page, so the Track page timeline
        // and the Audit Log pick it up the same way.
        $statusChanged = $newStatus !== '' && $newStatus !== $submission['status'];
        if ($statusChanged) {
            $pdo->prepare('UPDATE submissions SET status = ? WHERE id = ?')->execute([$newStatus, $submissionId]);
            if ($newStatus === 'Resolved') {
                $pdo->prepare("UPDATE complaints SET closed_at = NOW(), sla_status = 'Met' WHERE submission_id = ?")->execute([$submissionId]);
            }
            log_activity($staff['id'], 'Status changed', 'submission', $submissionId, ['new_status' => $newStatus, 'via' => 'response']);
        }

        return json_response(true, [
            'id' => $responseId,
            'status' => $status,
            'submission_status' => $statusChanged ? $newStatus : $submission['status'],
            'status_changed' => $statusChanged,
        ]);
    }

    public function send(Request $request)
    {
        $staff = $this->staff();

        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        if (!$id) json_fail('Missing response ID.', 422);

        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT r.*, c.email AS citizen_email, c.full_name AS citizen_name, s.reference_number
            FROM responses r
            JOIN submissions s ON s.id = r.submission_id
            LEFT JOIN citizens c ON c.id = s.citizen_id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $response = $stmt->fetch();
        if (!$response) json_fail('Response not found.', 404);

        $pdo->prepare("UPDATE responses SET status = 'Sent', sent_at = NOW() WHERE id = ?")->execute([$id]);

        $emailSent = false;
        if (!empty($response['citizen_email'])) {
            $emailSent = EmailService::notifyResponseSent(
                $response['citizen_email'],
                $response['subject'],
                $response['body'],
                $response['citizen_name'] ?? 'Citizen',
                $response['reference_number'] ?? ''
            );
        }

        log_activity($staff['id'], 'Response sent', 'response', $id, ['reference' => $response['reference_number']]);

        return json_response(true, [
            'id'            => $id,
            'email_sent'    => $emailSent,
            'citizen_email' => $response['citizen_email'] ?? null,
        ]);
    }
}
