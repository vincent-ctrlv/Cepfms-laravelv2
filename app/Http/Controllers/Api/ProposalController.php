<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Proposals & Suggestions: list and change proposal status.
 */
class ProposalController extends Controller
{
    public function list(Request $request)
    {
        $q = $request->query();
        $staff = $this->staff();

        $pdo = db();
        $search = trim($q['search'] ?? '');
        $status = trim($q['status'] ?? '');

        $where = ["s.submission_type = 'proposal'", "s.moderation_status = 'Approved'"];
        $params = [];
        if ($search !== '') {
            $where[] = '(s.subject LIKE ? OR s.reference_number LIKE ? OR c.full_name LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like, $like);
        }
        if ($status !== '') { $where[] = 'p.proposal_status = ?'; $params[] = $status; }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $perPage = 8;
        $page = max(1, (int) ($q['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM submissions s
            JOIN proposals p ON p.submission_id = s.id
            LEFT JOIN citizens c ON c.id = s.citizen_id
            $whereSql
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT s.id, s.reference_number, s.subject, s.category, s.sentiment, s.created_at,
                   c.full_name AS citizen_name, p.proposal_status
            FROM submissions s
            JOIN proposals p ON p.submission_id = s.id
            LEFT JOIN citizens c ON c.id = s.citizen_id
            $whereSql
            ORDER BY s.created_at DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);
        return json_response(true, ['rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    public function update(Request $request)
    {
        $staff = $this->staff();

        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        $action = $input['action'] ?? '';
        if (!$id || !$action) json_fail('Missing required fields.', 422);

        $pdo = db();
        $stmt = $pdo->prepare("SELECT s.id FROM submissions s WHERE s.id = ? AND s.submission_type = 'proposal'");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) json_fail('Proposal not found.', 404);

        switch ($action) {
            case 'change_status':
                $newStatus = $input['status'] ?? '';
                if (!in_array($newStatus, ['Under Review','Approved','Rejected','Implemented'], true)) {
                    json_fail('Invalid status.', 422);
                }
                $pdo->prepare('UPDATE proposals SET proposal_status = ? WHERE submission_id = ?')->execute([$newStatus, $id]);
                if ($newStatus === 'Implemented') {
                    $pdo->prepare("UPDATE submissions SET status = 'Resolved' WHERE id = ?")->execute([$id]);
                }
                log_activity($staff['id'], 'Proposal ' . strtolower($newStatus), 'submission', $id, ['new_status' => $newStatus]);
                break;

            default:
                json_fail('Unknown action.', 422);
        }

        return json_response(true, ['id' => $id]);
    }
}
