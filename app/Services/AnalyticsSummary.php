<?php

namespace App\Services;

/**
 * Engagement Analytics numbers, shared by the Analytics page and its PDF
 * export so both always agree. Scoped to moderation-approved submissions.
 */
class AnalyticsSummary
{

    /**
     * Engagement Analytics numbers, shared by the Analytics page (api/analytics/summary.php)
     * and its table export (api/analytics/pdf.php), so both always agree.
     *
     * Scoped to moderation-approved submissions only (see app/models/Submissions.php
     * for the shared rule — items still awaiting moderation haven't been vetted).
     *
     * Filters (the $q keys), all optional:
     *   range       = today | 7d | 30d | 6m | custom   (default 30d)
     *   from, to    = Y-m-d, only used when range=custom
     *   type        = feedback | complaint | proposal  (default: all)
     *   category    = <exact category string>           (default: all)
     *   sentiment   = Positive | Neutral | Negative     (default: all)
     *
     * Design rule: a chart that breaks a dimension down (Submission Distribution
     * by type, Citizen Sentiment, Top Concerns
     * by Category) ignores THAT SAME dimension's own filter — otherwise picking
     * e.g. Sentiment = Positive would collapse the sentiment chart into a single
     * trivial 100% bar. Every other chart/KPI applies all active filters.
     *
     * Note on statuses: "Pending / Under Review" means anything not yet Resolved
     * (Received, In Review, Assigned, Escalated) — this system has no separate
     * "In Progress"/"Validated" status; "Assigned" already means staff are
     * actively on it (see submissions.status ENUM in database/civicconnect.sql).
     */

    // ---- Resolve date range -----------------------------------------------------
    public static function analytics_resolve_range(string $range, ?string $from, ?string $to): array
    {
        $today = new \DateTime('today');
        $tomorrow = (clone $today)->modify('+1 day');

        switch ($range) {
            case 'today':
                $start = $today;
                $end   = $tomorrow;
                $label = 'yesterday';
                break;
            case '7d':
                $start = (clone $today)->modify('-6 days');
                $end   = $tomorrow;
                $label = 'previous 7 days';
                break;
            case '6m':
                $start = (clone $today)->modify('-6 months');
                $end   = $tomorrow;
                $label = 'previous 6 months';
                break;
            case 'custom':
                try {
                    $start = $from ? new \DateTime($from) : (clone $today)->modify('-29 days');
                    $end   = $to ? (new \DateTime($to))->modify('+1 day') : $tomorrow;
                    if ($end <= $start) {
                        throw new \Exception('invalid range');
                    }
                } catch (\Throwable $e) {
                    $range = '30d';
                    $start = (clone $today)->modify('-29 days');
                    $end   = $tomorrow;
                }
                $label = 'previous period';
                break;
            case '30d':
            default:
                $range = '30d';
                $start = (clone $today)->modify('-29 days');
                $end   = $tomorrow;
                $label = 'previous 30 days';
        }

        $days = max(1, (int) $start->diff($end)->days);
        $prevEnd   = clone $start;
        $prevStart = (clone $start)->modify("-{$days} days");

        return [$range, $start, $end, $prevStart, $prevEnd, $label, $days];
    }

    /** Builds a parameterized WHERE clause from the active filters, optionally excluding one or more dimensions. */
    public static function filters_where(array $active, array $exclude = []): array
    {
        $where  = ["s.moderation_status = 'Approved'"];
        $params = [];
        if (!in_array('type', $exclude, true) && $active['type'] !== 'all') {
            $where[] = 's.submission_type = ?';
            $params[] = $active['type'];
        }
        if (!in_array('category', $exclude, true) && $active['category'] !== 'all') {
            $where[] = 's.category = ?';
            $params[] = $active['category'];
        }
        if (!in_array('sentiment', $exclude, true) && $active['sentiment'] !== 'all') {
            $where[] = 's.sentiment = ?';
            $params[] = $active['sentiment'];
        }
        return [implode(' AND ', $where), $params];
    }

    /** Appends a created_at window onto a self::filters_where() result. */
    public static function with_window(string $where, array $params, \DateTime $start, \DateTime $end): array
    {
        return [
            "$where AND s.created_at >= ? AND s.created_at < ?",
            array_merge($params, [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]),
        ];
    }

    public static function scalar(\PDO $pdo, string $sql, array $params)
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public static function trend_pct_of(int $current, int $previous): int
    {
        if ($previous > 0) {
            return (int) round((($current - $previous) / $previous) * 100);
        }
        return $current > 0 ? 100 : 0;
    }

    // Avg. Response Time — first response actually SENT (responses.sent_at), not
    // resolution time; this is what "how quickly the LGU responds" measures.
    public static function avg_response_hours(\PDO $pdo, string $where, array $params): ?float
    {
        $sql = "
            SELECT AVG(TIMESTAMPDIFF(HOUR, s.created_at, fr.sent_at)) AS avg_hours
            FROM submissions s
            JOIN (
                SELECT submission_id, MIN(sent_at) AS sent_at
                FROM responses
                WHERE status = 'Sent' AND sent_at IS NOT NULL
                GROUP BY submission_id
            ) fr ON fr.submission_id = s.id
            WHERE $where
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        return $v !== null ? (float) $v : null;
    }

    public static function format_hours(?float $hours): string
    {
        if ($hours === null) return 'N/A';
        return $hours < 24 ? round($hours, 1) . ' hrs' : round($hours / 24, 1) . ' days';
    }

    public static function bucket_key(\DateTime $d, string $unit): string
    {
        if ($unit === 'week') {
            $c = clone $d;
            $c->modify('-' . ((int) $c->format('N') - 1) . ' days');
            return $c->format('Y-m-d');
        }
        return $d->format('Y-m-d');
    }

    // Mini stat cards above the chart (Total / Feedback / Complaints / Proposals),
    // each with a period-over-period trend — same filter scope as the chart above
    // so the four numbers always add up consistently with what's plotted.
    public static function type_count(\PDO $pdo, string $where, array $params, ?string $type = null): int
    {
        $sql = "SELECT COUNT(*) FROM submissions s WHERE $where" . ($type !== null ? ' AND s.submission_type = ?' : '');
        return (int) self::scalar($pdo, $sql, $type !== null ? array_merge($params, [$type]) : $params);
    }

    public static function fold_other(array $topRows, array $restRows): array
    {
        $other = ['category' => 'Other', 'total' => 0];
        $rows = [];
        $sawOther = false;
        foreach ($topRows as $r) {
            if ($r['category'] === 'Other') {
                $sawOther = true;
                $other['total'] += (int) $r['total'];
                continue;
            }
            $rows[] = $r;
        }
        foreach ($restRows as $r) {
            $sawOther = true;
            $other['total'] += (int) $r['total'];
        }
        if ($sawOther) {
            $rows[] = $other;
        }
        return $rows;
    }

    /**
     * All Analytics numbers for one set of filters ($q: range, from, to, type,
     * category, sentiment — the same keys as the page's query string).
     */
    public static function analytics_summary(array $q): array
    {
        $pdo = db();


        [$rangeKey, $rangeStart, $rangeEnd, $prevStart, $prevEnd, $trendLabel, $rangeDays] =
            self::analytics_resolve_range($q['range'] ?? '30d', $q['from'] ?? null, $q['to'] ?? null);

        // ---- Resolve dimension filters ----------------------------------------------
        $typeFilter = $q['type'] ?? 'all';
        if (!in_array($typeFilter, ['feedback', 'complaint', 'proposal'], true)) {
            $typeFilter = 'all';
        }
        $categoryFilter = (string) ($q['category'] ?? 'all');
        $sentimentFilter = $q['sentiment'] ?? 'all';
        if (!in_array($sentimentFilter, ['Positive', 'Neutral', 'Negative'], true)) {
            $sentimentFilter = 'all';
        }
        $active = ['type' => $typeFilter, 'category' => $categoryFilter, 'sentiment' => $sentimentFilter];





        // ---- KPIs -------------------------------------------------------------------
        [$baseWhere, $baseParams] = self::filters_where($active);

        // Total Submissions — full active-filter set, current vs. previous equal period.
        [$w, $p] = self::with_window($baseWhere, $baseParams, $rangeStart, $rangeEnd);
        $totalCur = (int) self::scalar($pdo, "SELECT COUNT(*) FROM submissions s WHERE $w", $p);
        [$w, $p] = self::with_window($baseWhere, $baseParams, $prevStart, $prevEnd);
        $totalPrev = (int) self::scalar($pdo, "SELECT COUNT(*) FROM submissions s WHERE $w", $p);

        // New Submissions — always a fixed rolling 7-day window ("recently"),
        // independent of the date-range picker, but still respects the other filters.
        $new7Start = (new \DateTime('now'))->modify('-7 days');
        $new7End   = new \DateTime('now');
        $new7PrevStart = (clone $new7Start)->modify('-7 days');
        [$w, $p] = self::with_window($baseWhere, $baseParams, $new7Start, $new7End);
        $newCur = (int) self::scalar($pdo, "SELECT COUNT(*) FROM submissions s WHERE $w", $p);
        [$w, $p] = self::with_window($baseWhere, $baseParams, $new7PrevStart, $new7Start);
        $newPrev = (int) self::scalar($pdo, "SELECT COUNT(*) FROM submissions s WHERE $w", $p);

        // Resolved Issues — complaints successfully resolved; fixed to complaints by
        // definition, so it ignores the Type filter (like a self-referential chart
        // would) but still respects category/sentiment + the date window.
        [$resolvedWhere, $resolvedParams] = self::filters_where($active, ['type']);
        [$w, $p] = self::with_window($resolvedWhere, $resolvedParams, $rangeStart, $rangeEnd);
        $resolvedCur = (int) self::scalar($pdo, "SELECT COUNT(*) FROM submissions s WHERE $w AND s.submission_type='complaint' AND s.status='Resolved'", $p);
        [$w, $p] = self::with_window($resolvedWhere, $resolvedParams, $prevStart, $prevEnd);
        $resolvedPrev = (int) self::scalar($pdo, "SELECT COUNT(*) FROM submissions s WHERE $w AND s.submission_type='complaint' AND s.status='Resolved'", $p);

        // Pending / Under Review — anything not yet Resolved.
        [$w, $p] = self::with_window($baseWhere, $baseParams, $rangeStart, $rangeEnd);
        $pendingCur = (int) self::scalar($pdo, "SELECT COUNT(*) FROM submissions s WHERE $w AND s.status != 'Resolved'", $p);
        [$w, $p] = self::with_window($baseWhere, $baseParams, $prevStart, $prevEnd);
        $pendingPrev = (int) self::scalar($pdo, "SELECT COUNT(*) FROM submissions s WHERE $w AND s.status != 'Resolved'", $p);

        [$w, $p] = self::with_window($baseWhere, $baseParams, $rangeStart, $rangeEnd);
        $avgResponseCur = self::avg_response_hours($pdo, $w, $p);
        [$w, $p] = self::with_window($baseWhere, $baseParams, $prevStart, $prevEnd);
        $avgResponsePrev = self::avg_response_hours($pdo, $w, $p);
        // Lower is better, so the trend arrow direction is inverted (a negative % is
        // an improvement) — handled client-side via trend_positive_is_good.
        $avgResponseTrend = ($avgResponsePrev !== null && $avgResponsePrev > 0 && $avgResponseCur !== null)
            ? (int) round((($avgResponseCur - $avgResponsePrev) / $avgResponsePrev) * 100)
            : 0;

        // Positive Sentiment % — ignores the Sentiment filter itself (see file header).
        [$sentWhere, $sentParams] = self::filters_where($active, ['sentiment']);
        [$w, $p] = self::with_window($sentWhere, $sentParams, $rangeStart, $rangeEnd);
        $sentRowsCur = $pdo->prepare("SELECT sentiment, COUNT(*) AS total FROM submissions s WHERE $w GROUP BY sentiment");
        $sentRowsCur->execute($p);
        $sentCurRaw = $sentRowsCur->fetchAll(\PDO::FETCH_KEY_PAIR);
        $sentCurTotal = array_sum($sentCurRaw);
        $positivePctCur = $sentCurTotal > 0 ? round((($sentCurRaw['Positive'] ?? 0) / $sentCurTotal) * 100) : 0;

        [$w, $p] = self::with_window($sentWhere, $sentParams, $prevStart, $prevEnd);
        $sentRowsPrev = $pdo->prepare("SELECT sentiment, COUNT(*) AS total FROM submissions s WHERE $w GROUP BY sentiment");
        $sentRowsPrev->execute($p);
        $sentPrevRaw = $sentRowsPrev->fetchAll(\PDO::FETCH_KEY_PAIR);
        $sentPrevTotal = array_sum($sentPrevRaw);
        $positivePctPrev = $sentPrevTotal > 0 ? round((($sentPrevRaw['Positive'] ?? 0) / $sentPrevTotal) * 100) : 0;

        $kpis = [
            [
                'key' => 'total_submissions', 'label' => 'Total Submissions', 'value' => $totalCur,
                'icon' => 'inbox', 'color' => 'blue', 'trend' => self::trend_pct_of($totalCur, $totalPrev),
                'trend_positive_is_good' => true, 'trend_label' => $trendLabel,
            ],
            [
                'key' => 'new_submissions', 'label' => 'New Submissions', 'value' => $newCur,
                'icon' => 'plus', 'color' => 'green', 'trend' => self::trend_pct_of($newCur, $newPrev),
                'trend_positive_is_good' => true, 'trend_label' => 'previous 7 days',
            ],
            [
                'key' => 'resolved_issues', 'label' => 'Resolved Issues', 'value' => $resolvedCur,
                'icon' => 'check', 'color' => 'green', 'trend' => self::trend_pct_of($resolvedCur, $resolvedPrev),
                'trend_positive_is_good' => true, 'trend_label' => $trendLabel,
            ],
            [
                'key' => 'pending_review', 'label' => 'Pending / Under Review', 'value' => $pendingCur,
                'icon' => 'clock', 'color' => 'amber', 'trend' => self::trend_pct_of($pendingCur, $pendingPrev),
                'trend_positive_is_good' => false, 'trend_label' => $trendLabel,
            ],
            [
                'key' => 'avg_response', 'label' => 'Avg. Response Time', 'value' => self::format_hours($avgResponseCur),
                'icon' => 'watch', 'color' => 'amber', 'trend' => $avgResponseTrend,
                'trend_positive_is_good' => false, 'trend_label' => $trendLabel,
            ],
            [
                'key' => 'positive_sentiment', 'label' => 'Positive Sentiment', 'value' => $positivePctCur . '%',
                'icon' => 'heart', 'color' => 'green', 'trend' => self::trend_pct_of((int) $positivePctCur, (int) $positivePctPrev),
                'trend_positive_is_good' => true, 'trend_label' => $trendLabel,
            ],
        ];

        // ---- Submissions Over Time by type — ignores the Type filter, same as -----
        // Submission Distribution below (both are "by type" breakdowns; a Type
        // filter narrowing to one type would otherwise flatten this chart to a
        // single line and disagree with the mini stat cards above it).
        [$typeWhere, $typeParams] = self::filters_where($active, ['type']);

        $bucketUnit = $rangeDays > 45 ? 'week' : 'day';
        $bucketExpr = $bucketUnit === 'week'
            ? "DATE(DATE_SUB(s.created_at, INTERVAL WEEKDAY(s.created_at) DAY))"
            : "DATE(s.created_at)";

        [$w, $p] = self::with_window($typeWhere, $typeParams, $rangeStart, $rangeEnd);
        $otStmt = $pdo->prepare("
            SELECT
                $bucketExpr AS bucket,
                SUM(s.submission_type = 'feedback')  AS feedback,
                SUM(s.submission_type = 'complaint') AS complaint,
                SUM(s.submission_type = 'proposal')  AS proposal
            FROM submissions s
            WHERE $w
            GROUP BY bucket
            ORDER BY bucket ASC
        ");
        $otStmt->execute($p);
        $otMap = [];
        foreach ($otStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $otMap[$row['bucket']] = $row;
        }


        $cursor = new \DateTime(self::bucket_key($rangeStart, $bucketUnit));
        $boundary = clone $rangeEnd;
        $submissionsOverTime = [];
        while ($cursor < $boundary) {
            $key = $cursor->format('Y-m-d');
            $row = $otMap[$key] ?? null;
            $submissionsOverTime[] = [
                'day'       => $key,
                'feedback'  => (int) ($row['feedback']  ?? 0),
                'complaint' => (int) ($row['complaint'] ?? 0),
                'proposal'  => (int) ($row['proposal']  ?? 0),
            ];
            $cursor->modify('+1 ' . $bucketUnit);
        }

        [$curW, $curP] = self::with_window($typeWhere, $typeParams, $rangeStart, $rangeEnd);
        [$prevW, $prevP] = self::with_window($typeWhere, $typeParams, $prevStart, $prevEnd);
        $overTimeSummary = [];
        foreach ([
            ['key' => 'total',      'label' => 'Total',      'color' => '#3b82f6', 'dbType' => null],
            ['key' => 'feedback',   'label' => 'Feedback',   'color' => '#06b6d4', 'dbType' => 'feedback'],
            ['key' => 'complaint',  'label' => 'Complaints', 'color' => '#f43f5e', 'dbType' => 'complaint'],
            ['key' => 'proposal',   'label' => 'Proposals',  'color' => '#a78bfa', 'dbType' => 'proposal'],
        ] as $row) {
            $cur  = self::type_count($pdo, $curW, $curP, $row['dbType']);
            $prev = self::type_count($pdo, $prevW, $prevP, $row['dbType']);
            $overTimeSummary[] = [
                'key' => $row['key'], 'label' => $row['label'], 'color' => $row['color'],
                'value' => $cur, 'trend' => self::trend_pct_of($cur, $prev),
            ];
        }

        // ---- Submission Distribution (by type) — ignores the Type filter -----------
        [$w, $p] = self::with_window($typeWhere, $typeParams, $rangeStart, $rangeEnd);
        $typeStmt = $pdo->prepare("SELECT submission_type, COUNT(*) AS total FROM submissions s WHERE $w GROUP BY submission_type");
        $typeStmt->execute($p);
        $typeRaw = $typeStmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        // submission_type is a lowercase ENUM ('feedback','complaint','proposal') —
        // map to Title Case labels so the lookup below doesn't miss on case.
        $typeMap = ['feedback' => 'Feedback', 'complaint' => 'Complaint', 'proposal' => 'Proposal'];
        $byType = [];
        foreach ($typeMap as $dbValue => $label) {
            $byType[] = ['type' => $label, 'total' => (int) ($typeRaw[$dbValue] ?? 0)];
        }

        // ---- Citizen Sentiment — ignores the Sentiment filter -----------------------
        [$w, $p] = self::with_window($sentWhere, $sentParams, $rangeStart, $rangeEnd);
        $sentStmt = $pdo->prepare("SELECT sentiment, COUNT(*) AS total FROM submissions s WHERE $w GROUP BY sentiment");
        $sentStmt->execute($p);
        $sentRaw = $sentStmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $sentTotalAll = array_sum($sentRaw);
        $sentimentDistribution = [];
        foreach (['Positive', 'Neutral', 'Negative'] as $s) {
            $c = (int) ($sentRaw[$s] ?? 0);
            $sentimentDistribution[] = ['sentiment' => $s, 'total' => $c, 'pct' => $sentTotalAll > 0 ? round($c / $sentTotalAll * 100, 1) : 0];
        }

        // ---- Top Citizen Concerns (top 5 + Other) ----------------------------------
        // Ignores the Category filter itself, so the chart still shows the spread.
        [$catWhere, $catParams] = self::filters_where($active, ['category']);
        [$w, $p] = self::with_window($catWhere, $catParams, $rangeStart, $rangeEnd);
        $catStmt = $pdo->prepare("
            SELECT
                COALESCE(NULLIF(s.category, ''), 'Other') AS category,
                COUNT(*) AS total
            FROM submissions s
            WHERE $w
            GROUP BY category
            ORDER BY total DESC
        ");
        $catStmt->execute($p);
        $catRows = $catStmt->fetchAll(\PDO::FETCH_ASSOC);

        $topCategories = array_slice($catRows, 0, 5);
        $restCategories = array_slice($catRows, 5);


        $categoryRows = self::fold_other($topCategories, $restCategories);
        $byCategory = array_map(fn($r) => ['category' => $r['category'], 'total' => (int) $r['total']], $categoryRows);

        // ---- Proposal & Suggestion Outcomes -----------------------------------------
        [$w, $p] = self::with_window($baseWhere, $baseParams, $rangeStart, $rangeEnd);
        $propStmt = $pdo->prepare("
            SELECT p.proposal_status, COUNT(*) AS total
            FROM proposals p
            JOIN submissions s ON s.id = p.submission_id
            WHERE $w
            GROUP BY p.proposal_status
        ");
        $propStmt->execute($p);
        $proposalRaw = $propStmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $proposalOutcomes = [
            'total'        => array_sum($proposalRaw),
            'under_review' => (int) ($proposalRaw['Under Review'] ?? 0),
            'approved'     => (int) ($proposalRaw['Approved']     ?? 0),
            'implemented'  => (int) ($proposalRaw['Implemented']  ?? 0),
            'rejected'     => (int) ($proposalRaw['Rejected']     ?? 0),
        ];

        // ---- Filter dropdown option lists (global universe, not filter-scoped) -----
        $allCategories = $pdo->query("
            SELECT DISTINCT category FROM submissions WHERE moderation_status = 'Approved' AND category IS NOT NULL AND category != '' ORDER BY category
        ")->fetchAll(\PDO::FETCH_COLUMN);

        return [
            'kpis'                    => $kpis,
            'submissions_over_time'   => $submissionsOverTime,
            'over_time_summary'       => $overTimeSummary,
            'over_time_unit'          => $bucketUnit,
            'by_type'                 => $byType,
            'sentiment'               => $sentimentDistribution,
            'by_category'             => $byCategory,
            'proposal_outcomes'       => $proposalOutcomes,
            'filters'                 => [
                'range'      => $rangeKey,
                'range_from' => $rangeStart->format('Y-m-d'),
                'range_to'   => (clone $rangeEnd)->modify('-1 day')->format('Y-m-d'),
                'type'       => $typeFilter,
                'category'   => $categoryFilter,
                'sentiment'  => $sentimentFilter,
            ],
            'filter_options' => [
                'categories'  => $allCategories,
            ],
        ];
    }
}
