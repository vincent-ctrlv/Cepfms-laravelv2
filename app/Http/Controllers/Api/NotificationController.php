<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Topbar notification bell: recent citizen activity.
 */
class NotificationController extends Controller
{
    public function recent(Request $request)
    {
        $q = $request->query();
        /**
         * Recent citizen-activity notifications for the admin topbar bell.
         *
         * Admins: sourced from moderation_queue, which gets a row every time a
         * citizen submits feedback, a complaint, or a proposal — so "pending" items
         * are exactly "things a citizen submitted that staff haven't looked at yet."
         *
         * Staff can't open the moderation page (admin-only), so for them the feed is
         * approved submissions still sitting at "Received" — new cases nobody has
         * started working on.
         */
                $staff = $this->staff();

        $pdo = db();

        // The bell only alerts on submissions newer than what this user last saw.
        // `since` is the newest created_at they had seen (a DB timestamp the client got
        // from this endpoint), so the comparison uses the DB's own clock and timezone.
        $since = (string) ($q['since'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) $since = null;

        if (role_is_admin($staff['role'])) {
            $rows = $pdo->query("
                SELECT mq.content_type AS type, mq.created_at,
                       s.subject, s.reference_number
                FROM moderation_queue mq
                LEFT JOIN submissions s ON s.id = mq.submission_id
                WHERE mq.status = 'Pending'
                ORDER BY mq.created_at DESC
                LIMIT 8
            ")->fetchAll();
            $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM moderation_queue WHERE status = 'Pending'")->fetchColumn();
            $latestAt = $pdo->query("SELECT MAX(created_at) FROM moderation_queue WHERE status = 'Pending'")->fetchColumn() ?: null;
            $newStmt = $pdo->prepare("SELECT COUNT(*) FROM moderation_queue WHERE status = 'Pending' AND created_at > ?");
            $link = app_href('admin/moderation');
        } else {
            $where = "s.moderation_status = 'Approved' AND s.status = 'Received'";
            $rows = $pdo->query("
                SELECT s.submission_type AS type, s.created_at, s.subject, s.reference_number
                FROM submissions s
                WHERE $where
                ORDER BY s.created_at DESC
                LIMIT 8
            ")->fetchAll();
            $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM submissions s WHERE $where")->fetchColumn();
            $latestAt = $pdo->query("SELECT MAX(s.created_at) FROM submissions s WHERE $where")->fetchColumn() ?: null;
            $newStmt = $pdo->prepare("SELECT COUNT(*) FROM submissions s WHERE $where AND s.created_at > ?");
            $link = app_href('admin/submissions');
        }

        $newCount = 0;
        if ($since !== null) {
            $newStmt->execute([$since]);
            $newCount = (int) $newStmt->fetchColumn();
        }

        return json_response(true, [
            'new_count' => $newCount,          // pending items newer than `since`
            'pending_count' => $pendingCount,  // all pending items (the backlog)
            'latest_at' => $latestAt,          // newest pending created_at — the client's next `since`
            'link' => $link,
            'items' => array_map(function ($r) use ($since) {
                $type = ucfirst(strtolower($r['type']));
                return [
                    'type' => $type,
                    'subject' => $r['subject'] ?: ($type . ' from a citizen'),
                    'reference_number' => $r['reference_number'],
                    'created_at' => time_ago($r['created_at']),
                    'is_new' => $since !== null && $r['created_at'] > $since,
                ];
            }, $rows),
        ]);
    }
}
