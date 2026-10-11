<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AnalyticsSummary;
use App\Services\ProtectedPdf;

/**
 * Engagement Analytics: summary data and the protected PDF export.
 */
class AnalyticsController extends Controller
{
    public function summary(Request $request)
    {
        /**
         * Engagement Analytics data for the Analytics page (JSON). The numbers are
         * computed in app/services/AnalyticsSummary.php; filters come from the query
         * string: range, from, to, type, category, sentiment.
         */

        return json_response(true, AnalyticsSummary::analytics_summary($request->query()));
    }

    public function pdf(Request $request)
    {
        /**
         * Password-protected PDFs of Engagement Analytics (see app/services/ProtectedPdf.php
         * for how the file is protected and the password emailed).
         *
         *   export → a table report (Key Indicators, distribution, sentiment, concerns,
         *            proposal outcomes, submissions over time), downloaded. The numbers
         *            are computed here from the filters, not taken from the browser.
         *   print  → the page as it looks (charts), opened in the tab to print from.
         *
         * POST JSON: { purpose: 'export' | 'print', filters: {...}, images: [dataURL, ...] (print only) }
         */
                $staff = $this->staff();

        $input = $this->input();
        $forPrint = ($input['purpose'] ?? '') === 'print';

        // ---- Filters, same keys as the page's query string --------------------------
        $filters = is_array($input['filters'] ?? null) ? $input['filters'] : [];
        $f = fn(string $k, string $default = 'all') => isset($filters[$k]) && is_scalar($filters[$k]) ? mb_substr((string) $filters[$k], 0, 100) : $default;
        $data = AnalyticsSummary::analytics_summary([
            'range'     => $f('range', '30d'),
            'from'      => $f('range_from', ''),
            'to'        => $f('range_to', ''),
            'type'      => $f('type'),
            'category'  => $f('category'),
            'sentiment' => $f('sentiment'),
        ]);
        // What analytics_summary() actually used (invalid values fall back to defaults).
        $used = $data['filters'];
        $rangeFrom = $used['range_from'];
        $rangeTo   = $used['range_to'];
        $fileName  = "analytics-$rangeFrom-to-$rangeTo.pdf";

        $log = function () use ($staff, $forPrint, $used) {
            log_activity((int) $staff['id'], $forPrint ? 'Analytics printed' : 'Analytics exported', 'analytics', null, [
                'range_from' => $used['range_from'], 'range_to' => $used['range_to'],
                'type' => $used['type'], 'category' => $used['category'], 'sentiment' => $used['sentiment'],
            ]);
        };

        if ($forPrint) {
            $sections = ProtectedPdf::sections($input);
            return ProtectedPdf::send($staff, 'Engagement Analytics', $fileName, $sections, true, $log);
        }

        // ---- Export: the table report -----------------------------------------------
        $label = fn(string $v) => $v === 'all' ? 'All' : ucfirst($v);
        $pct = fn(int $part, int $whole) => $whole > 0 ? round($part / $whole * 100, 1) . '%' : '0%';
        $typeTotal = array_sum(array_column($data['by_type'], 'total'));
        $catTotal = array_sum(array_column($data['by_category'], 'total'));
        $po = $data['proposal_outcomes'];
        $periodLabel = fn(string $d) => ($data['over_time_unit'] === 'week' ? 'Week of ' : '') . date('M j, Y', strtotime($d));

        return ProtectedPdf::sendTables($staff, 'Engagement Analytics', $fileName,
            [
                'Period: ' . date('M j, Y', strtotime($rangeFrom)) . ' to ' . date('M j, Y', strtotime($rangeTo)),
                'Type: ' . $label($used['type']) . ' · Category: ' . $label($used['category']) . ' · Sentiment: ' . $label($used['sentiment']),
            ],
            [
                ['Key Indicators', ['Indicator', 'Value', 'Change', 'Compared with'], array_map(
                    fn($k) => [$k['label'], $k['value'], ($k['trend'] > 0 ? '+' : '') . $k['trend'] . '%', $k['trend_label']],
                    $data['kpis'])],
                ['Submission Distribution', ['Type', 'Total', 'Percent'], array_map(
                    fn($r) => [$r['type'], $r['total'], $pct($r['total'], $typeTotal)], $data['by_type'])],
                ['Citizen Sentiment', ['Sentiment', 'Total', 'Percent'], array_map(
                    fn($r) => [$r['sentiment'], $r['total'], $r['pct'] . '%'], $data['sentiment'])],
                ['Top Citizen Concerns', ['Category', 'Total', 'Percent'], $data['by_category']
                    ? array_map(fn($r) => [$r['category'], $r['total'], $pct($r['total'], $catTotal)], $data['by_category'])
                    : [['No submissions in this period', '', '']]],
                ['Proposal & Suggestion Outcomes', ['Outcome', 'Total', 'Percent'], [
                    ['Under Review', $po['under_review'], $pct($po['under_review'], $po['total'])],
                    ['Approved',     $po['approved'],     $pct($po['approved'], $po['total'])],
                    ['Implemented',  $po['implemented'],  $pct($po['implemented'], $po['total'])],
                    ['Rejected',     $po['rejected'],     $pct($po['rejected'], $po['total'])],
                    ['Total',        $po['total'],        $po['total'] > 0 ? '100%' : '0%'],
                ]],
                ['Submissions Over Time', [$data['over_time_unit'] === 'week' ? 'Week' : 'Date', 'Total', 'Feedback', 'Complaints', 'Proposals'], array_map(
                    fn($r) => [$periodLabel($r['day']), $r['feedback'] + $r['complaint'] + $r['proposal'], $r['feedback'], $r['complaint'], $r['proposal']],
                    $data['submissions_over_time'])],
            ],
            false, $log);
    }
}
