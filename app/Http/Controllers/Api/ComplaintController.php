<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Complaints & Issues: list, close, and log case updates.
 */
class ComplaintController extends Controller
{
    public function list(Request $request)
    {
        $q = $request->query();
        $staff = $this->staff();

        $pdo = db();
        $search   = trim($q['search']   ?? '');
        $priority = trim($q['priority'] ?? '');
        $perPage  = 8;
        $page     = max(1, (int) ($q['page'] ?? 1));
        $offset   = ($page - 1) * $perPage;

        $where  = ["s.submission_type = 'complaint'", "s.moderation_status = 'Approved'"];
        $params = [];
        if ($search !== '') {
            $where[] = '(s.subject LIKE ? OR s.reference_number LIKE ? OR cz.full_name LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like, $like);
        }
        if ($priority !== '') { $where[] = 's.priority = ?'; $params[] = $priority; }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM submissions s
            JOIN complaints c ON c.submission_id = s.id
            LEFT JOIN citizens cz ON cz.id = s.citizen_id
            $whereSql
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Paginated rows
        $stmt = $pdo->prepare("
            SELECT s.id, s.reference_number, s.subject, s.category, s.priority, s.sentiment, s.status, s.created_at,
                   cz.full_name AS citizen_name, c.escalated
            FROM submissions s
            JOIN complaints c ON c.submission_id = s.id
            LEFT JOIN citizens cz ON cz.id = s.citizen_id
            $whereSql
            ORDER BY s.created_at DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return json_response(true, [
            'rows'          => $rows,
            'total'         => $total,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_pages'   => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    public function update(Request $request)
    {
        $staff = $this->staff();

        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        $action = $input['action'] ?? '';
        if (!$id || !$action) json_fail('Missing required fields.', 422);

        $pdo = db();
        $stmt = $pdo->prepare("SELECT s.id FROM submissions s WHERE s.id = ? AND s.submission_type = 'complaint'");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) json_fail('Complaint not found.', 404);

        switch ($action) {
            case 'close':
                $pdo->prepare("UPDATE submissions SET status = 'Resolved' WHERE id = ?")->execute([$id]);
                $pdo->prepare("UPDATE complaints SET closed_at = NOW(), sla_status = 'Met' WHERE submission_id = ?")->execute([$id]);
                log_activity($staff['id'], 'Complaint closed', 'submission', $id, []);
                break;

            default:
                json_fail('Unknown action.', 422);
        }

        return json_response(true, ['id' => $id]);
    }

    public function logUpdate(Request $request)
    {
        $staff = $this->staff();

        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        $note = clean_str($input['note'] ?? '');
        if (!$id || $note === '') json_fail('Please enter an update note.', 422);

        $stmt = db()->prepare("SELECT s.id FROM submissions s WHERE s.id = ? AND s.submission_type = 'complaint'");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) json_fail('Complaint not found.', 404);

        log_activity($staff['id'], 'Case update logged', 'submission', $id, ['note' => $note]);

        return json_response(true, ['id' => $id]);
    }
}
