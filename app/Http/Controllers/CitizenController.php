<?php

namespace App\Http\Controllers;

use App\Services\AIClassifier;
use App\Support\CitizenAuth;
use App\Support\SubmissionProgress;
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
            'portalNav'  => 'submit',
            'citizen'    => CitizenAuth::current(),
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

        // Stages, times and the rejection notice for the public tracker (shared with the citizen portal).
        $progress = $submission ? SubmissionProgress::timeline($submission) : [
            'stageOrder' => SubmissionProgress::STAGES, 'stageDescriptions' => SubmissionProgress::DESCRIPTIONS,
            'stageBadge' => SubmissionProgress::BADGES, 'currentStage' => null, 'currentIndex' => -1,
            'notAccepted' => false, 'rejectReason' => null, 'rejectHint' => null, 'rejectNote' => null, 'stageTimes' => [],
        ];

        return view('citizen.track', ['pageTitle' => 'Track Submission'] + compact('ref', 'submission', 'notFound') + $progress);
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
