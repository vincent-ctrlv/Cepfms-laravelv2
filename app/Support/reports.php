<?php

/**
 * Report queries shared by several controllers (ported from the original
 * app/models/Submissions.php and app/models/ActivityLog.php).
 *
 * MODEL: Submissions
 *
 * Every public- and staff-facing stat (resolution rate, avg. resolution time,
 * recently-resolved lists, etc.) is scoped to moderation-approved submissions
 * only — items still awaiting moderation haven't been vetted yet and
 * shouldn't count. That rule used to be re-implemented by hand in three
 * different places and had drifted out of sync (see git history / the
 * dashboard vs. analytics resolution-rate mismatch). Centralizing it here
 * means fixing the rule once fixes it everywhere.
 */

/** The one place the "counts toward stats" rule is defined. */
function submissions_approved_where(): string
{
    return "moderation_status = 'Approved'";
}

/**
 * Count approved submissions, optionally narrowed further (by type, date
 * range, etc). $extraWhere is ANDed with the approved-only base filter.
 */
function submissions_count(string $extraWhere = '1=1', array $params = []): int
{
    $sql = 'SELECT COUNT(*) FROM submissions WHERE ' . submissions_approved_where() . " AND ($extraWhere)";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/**
 * Resolution rate over approved submissions, optionally narrowed further.
 * Returns ['total' => int, 'resolved' => int, 'rate' => int (0-100)].
 */
function submissions_resolution_rate(string $extraWhere = '1=1', array $params = []): array
{
    $total = submissions_count($extraWhere, $params);
    $resolved = submissions_count("status = 'Resolved' AND ($extraWhere)", $params);
    return [
        'total' => $total,
        'resolved' => $resolved,
        'rate' => $total > 0 ? (int) round(($resolved / $total) * 100) : 0,
    ];
}

/** Average resolution time, in days, across resolved + approved submissions. Null if none resolved yet. */
function submissions_avg_resolution_days(string $extraWhere = '1=1', array $params = []): ?float
{
    $sql = 'SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) FROM submissions WHERE '
         . submissions_approved_where() . " AND status = 'Resolved' AND ($extraWhere)";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $hours = $stmt->fetchColumn();
    return $hours ? round($hours / 24, 1) : null;
}

/** Most recently resolved approved submissions, newest first — for "recently resolved" widgets. */
function submissions_recent_resolved(int $limit = 5): array
{
    $stmt = db()->prepare('
        SELECT reference_number, subject, submission_type, status, updated_at
        FROM submissions
        WHERE ' . submissions_approved_where() . " AND status = 'Resolved'
        ORDER BY updated_at DESC LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// =====================================================================
// Activity Log
// =====================================================================
/**
 * MODEL: Activity Log
 *
 * activity_log holds two different kinds of rows:
 *   - work events (submissions, moderation, responses), which feed
 *     the role-filtered "Recent Activity" panel on the dashboard, and
 *   - security events (logins/logouts, password changes, account changes,
 *     System Settings changes, CSV exports), which never appear on the dashboard.
 * The super-admin Audit Log shows security events by default and can switch
 * to work events or everything (see activity_audit_where()).
 * The split is defined once here so the two pages can't drift apart.
 */

/** Filters on the Audit Log page, in display order. */
const ACTIVITY_AUDIT_SCOPES = ['security' => 'Security', 'work' => 'Work activity', 'all' => 'All'];

/** SQL condition (alias `a`) matching security/audit events. */
function activity_security_where(): string
{
    return "(a.entity IN ('staff', 'analytics', 'settings', 'citizen_verification') OR a.action = 'export_csv')";
}

/**
 * Work events the given staff member should see, newest first, with the
 * related submission (if any) resolved so the feed can name and link it.
 *
 *   super_admin — every work event
 *   admin       — every work event (security events live on the Audit Log)
 *   staff       — their own actions, new approved submissions, and anything
 *                 that happens on an item they've acted on or responded to
 */
function activity_feed_for(array $viewer, int $limit = 8): array
{
    $params = [];
    $where  = [
        'NOT ' . activity_security_where(),
        // Always logged right after "Submission created" — pure duplicate noise.
        "a.action <> 'Sent to moderation queue'",
    ];

    if (!role_is_admin($viewer['role'] ?? null)) {
        $me = (int) $viewer['id'];
        $where[] = "(
            a.staff_id = ?
            OR (a.action = 'Submission created' AND s.moderation_status = 'Approved')
            OR (s.moderation_status = 'Approved' AND s.id IN (
                SELECT entity_id FROM activity_log WHERE staff_id = ? AND entity = 'submission' AND entity_id IS NOT NULL
                UNION
                SELECT submission_id FROM responses WHERE staff_id = ?
            ))
        )";
        array_push($params, $me, $me, $me);
    }

    $sql = "
        SELECT a.id, a.staff_id, a.action, a.entity, a.entity_id, a.metadata, a.created_at,
               u.full_name AS staff_name, u.role AS staff_role,
               s.reference_number, s.subject, s.submission_type, s.moderation_status
        FROM activity_log a
        LEFT JOIN users u ON u.id = a.staff_id
        LEFT JOIN moderation_queue mq ON a.entity = 'moderation_queue' AND mq.id = a.entity_id
        LEFT JOIN responses r ON a.entity = 'response' AND r.id = a.entity_id
        LEFT JOIN submissions s ON s.id = CASE a.entity
            WHEN 'submission'       THEN a.entity_id
            WHEN 'moderation_queue' THEN mq.submission_id
            WHEN 'response'         THEN r.submission_id
        END
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT " . (int) $limit;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return array_map(fn($row) => activity_feed_item($row, $viewer), $stmt->fetchAll());
}

/**
 * Turns a raw log row into what the feed shows: a readable sentence that names
 * the item, who did it, a tone for the dot color, and where clicking should go.
 * Returns ['text', 'actor', 'tone' (new|good|bad|neutral), 'href' (or null), 'created_at'].
 */
function activity_feed_item(array $row, array $viewer): array
{
    $meta   = json_decode((string) ($row['metadata'] ?? ''), true) ?: [];
    $isMine = $row['staff_id'] !== null && (int) $row['staff_id'] === (int) $viewer['id'];
    $type   = $row['submission_type'] ? ucfirst($row['submission_type']) : 'Submission';
    $ref    = $row['reference_number'] ?? ($meta['reference'] ?? null);
    $item   = $ref ? "$type $ref" : $type;
    $action = $row['action'];
    $tone   = 'neutral';

    switch (true) {
        case $action === 'Submission created':
            // "New" only for the first 2 days; after that it's just a record of the submission.
            $isRecent = strtotime($row['created_at']) > time() - 2 * 86400;
            $text = ($isRecent ? 'New ' . strtolower($type) : $type) . ' submitted' . ($row['subject'] ? ': ' . $row['subject'] : '');
            $tone = 'new';
            break;
        case str_starts_with($action, 'Moderation '):
            $verb = substr($action, strlen('Moderation '));
            $text = match ($verb) {
                'approve'  => "$item approved in moderation",
                'reject'   => "$item rejected in moderation",
                'escalate' => "$item escalated for a second review",
                default    => "$item moderated ($verb)",
            };
            $tone = match ($verb) { 'approve' => 'good', 'reject', 'escalate' => 'bad', default => 'neutral' };
            break;
        case str_starts_with($action, 'Proposal '):
            $status = $meta['new_status'] ?? substr($action, strlen('Proposal '));
            $text = "$item " . strtolower($status);
            $tone = match ($status) { 'Approved', 'Implemented' => 'good', 'Rejected' => 'bad', default => 'neutral' };
            break;
        case $action === 'Complaint escalated':
            $text = "$item escalated";
            $tone = 'bad';
            break;
        case $action === 'Complaint closed':
            $text = "$item closed";
            $tone = 'good';
            break;
        case $action === 'Status changed':
            $status = $meta['new_status'] ?? 'a new status';
            $text = "$item moved to $status";
            $tone = match ($status) { 'Resolved' => 'good', 'Escalated' => 'bad', default => 'neutral' };
            break;
        case $action === 'Case update logged':
            $text = "Case update logged on $item";
            break;
        case $action === 'Response drafted':
            $text = $isMine ? "You drafted a response to $item" : "Response drafted for $item";
            break;
        case $action === 'Response submitted for approval':
            $text = ($isMine ? 'Your response' : 'Response') . " to $item was submitted for approval";
            break;
        case $action === 'Response sent':
            $text = ($isMine ? 'Your response' : 'Response') . " to $item was sent";
            $tone = 'good';
            break;
        case $action === 'Settings updated':
            $text = 'AI classification settings updated';
            break;
        default:
            $text = $ref ? "$action · $item" : $action;
    }

    if ($isMine) {
        $actor = 'You';
    } elseif ($row['staff_id'] !== null) {
        $actor = ($row['staff_name'] ?? 'Staff') . ' (' . role_label($row['staff_role']) . ')';
    } else {
        $actor = $action === 'Submission created' ? 'Citizen portal' : 'System';
    }

    return [
        'text'       => $text,
        'actor'      => $actor,
        'tone'       => $tone,
        'href'       => activity_item_href($row, $viewer),
        'created_at' => $row['created_at'],
    ];
}

/** Where a feed entry should link: the item's module page, pre-searched by reference. */
function activity_item_href(array $row, array $viewer): ?string
{
    if (empty($row['reference_number'])) {
        return null;
    }
    // Not yet approved = only visible in the moderation queue (admins only).
    if ($row['moderation_status'] !== 'Approved') {
        return role_is_admin($viewer['role'] ?? null) ? 'moderation' : null;
    }
    $page = [
        'feedback'  => 'submissions',
        'complaint' => 'complaints',
        'proposal'  => 'proposals',
    ][$row['submission_type']] ?? 'submissions';
    if ($row['entity'] === 'response') {
        $page = 'responses';
    }
    return $page . '?search=' . rawurlencode($row['reference_number']);
}

/** SQL condition (alias `a`) for one Audit Log filter: security, work, or all. */
function activity_audit_where(string $scope): string
{
    // Same duplicate the dashboard feed drops: always logged right after "Submission created".
    $noDuplicate = "a.action <> 'Sent to moderation queue'";
    return match ($scope) {
        'work'  => 'NOT ' . activity_security_where() . " AND $noDuplicate",
        'all'   => $noDuplicate,
        default => activity_security_where(),
    };
}

/** Number of events in one Audit Log filter, for paginating the page. */
function activity_audit_count(string $scope = 'security'): int
{
    return (int) db()->query('SELECT COUNT(*) FROM activity_log a WHERE ' . activity_audit_where($scope))->fetchColumn();
}

/**
 * Events for the super-admin Audit Log page, newest first. Work events get
 * their related submission resolved (as in the dashboard feed) so the page
 * can describe and link them with activity_feed_item().
 */
function activity_audit_log(string $scope = 'security', int $limit = 200, int $offset = 0): array
{
    $sql = "
        SELECT a.id, a.staff_id, a.action, a.entity, a.entity_id, a.metadata, a.created_at,
               u.full_name AS staff_name, u.email AS staff_email, u.role AS staff_role,
               target.full_name AS target_name,
               s.reference_number, s.subject, s.submission_type, s.moderation_status
        FROM activity_log a
        LEFT JOIN users u ON u.id = a.staff_id
        LEFT JOIN users target ON a.entity = 'staff' AND target.id = a.entity_id
        LEFT JOIN moderation_queue mq ON a.entity = 'moderation_queue' AND mq.id = a.entity_id
        LEFT JOIN responses r ON a.entity = 'response' AND r.id = a.entity_id
        LEFT JOIN submissions s ON s.id = CASE a.entity
            WHEN 'submission'       THEN a.entity_id
            WHEN 'moderation_queue' THEN mq.submission_id
            WHEN 'response'         THEN r.submission_id
        END
        WHERE " . activity_audit_where($scope) . "
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
    return db()->query($sql)->fetchAll();
}

/** Whether a log row is a security event (same rule as activity_security_where()). */
function activity_is_security(array $row): bool
{
    return in_array($row['entity'], ['staff', 'analytics', 'settings', 'citizen_verification'], true) || $row['action'] === 'export_csv';
}
