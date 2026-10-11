<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AIClassifier;
use App\Services\EmailService;
use App\Services\FileUploadService;
use App\Services\SpamService;
use App\Support\CitizenAuth;
use RuntimeException;
use function Illuminate\Support\defer;
use Throwable;
use DateTime;
use PDO;

/**
 * Submissions: the citizen submit endpoint plus the staff list/detail/update endpoints.
 */
class SubmissionController extends Controller
{
    public function create(Request $request)
    {
        $post = $request->post();
        // Photos arrive as photos[] (one or more files); accept a single file too.
        $photos = $request->file('photos', []);
        $photos = $photos instanceof \Illuminate\Http\UploadedFile ? [$photos] : array_values(array_filter((array) $photos));
        // Public, unauthenticated endpoint — enforce IP rate limits (5 / 15min, 20 / 24h)
        $callerIp = client_ip();
        if (submission_rate_limited($callerIp)) {
            json_fail('Too many submissions have been received. Please try again later.', 429);
        }


        // ---- Validate input (server-side; never trust client-only validation) ----
        // Only verified citizens get here (citizen:verified middleware): the name is the
        // one on their approved ID, and the email is their Google account's.
        $citizen = CitizenAuth::current();
        $citizenId = (int) $citizen['id'];
        $fullName = $citizen['full_name'];
        $email = $citizen['email'];

        $subject = clean_str($post['subject'] ?? '');
        $description = clean_str($post['description'] ?? '');
        $phone = clean_str($post['phone'] ?? '');
        $city = clean_str($post['city'] ?? '');
        $barangay = clean_str($post['barangay'] ?? '');
        $street = clean_str($post['street'] ?? '');
        $latitude = is_numeric($post['latitude'] ?? null) ? (float) $post['latitude'] : null;
        $longitude = is_numeric($post['longitude'] ?? null) ? (float) $post['longitude'] : null;
        $preferredType = in_array($post['preferred_type'] ?? '', ['feedback', 'complaint', 'proposal'], true) ? $post['preferred_type'] : null;
        // Optional, and only on the form when a type was chosen from a home page card;
        // anything not in the lists counts as "let us decide".
        $preferredCategory = $preferredType && in_array($post['category'] ?? '', AIClassifier::CATEGORIES, true) ? $post['category'] : null;
        $preferredPriority = $preferredType && in_array($post['priority'] ?? '', AIClassifier::PRIORITIES, true) ? $post['priority'] : null;

        $honeypot = $post['website_url'] ?? '';
        $formLoadedAt = (int) ($post['form_loaded_at'] ?? 0);

        $errors = [];
        if ($subject === '' || mb_strlen($subject) > 200) $errors[] = 'A valid subject is required.';
        elseif (SpamService::unreadableReason($subject) !== null) $errors[] = "The subject doesn't look readable. Please describe the issue in a few words.";
        if (mb_strlen($description) < 10) $errors[] = 'Please provide a more detailed description.';
        elseif (SpamService::unreadableReason($description, 3) !== null) $errors[] = "The description doesn't look readable. Please describe the issue in a few real words (at least 3).";
        $phone = preg_replace('/[\s\-()]/', '', $phone);
        if (!preg_match('/^(09|\+639)\d{9}$/', $phone)) $errors[] = 'A valid mobile number is required (e.g. 09171234567).';
        if (($photoError = FileUploadService::validate($photos)) !== null) $errors[] = $photoError;
        if (empty($post['consent'])) $errors[] = 'You must agree to the Privacy Policy and Terms of Service to submit.';

        // Service area: only locations inside San Jose del Monte, Bulacan. The city
        // field is fixed on the form, but never trust it — force it server-side.
        $city = SJDM_CITY;
        $canonicalBarangay = sjdm_barangay($barangay);
        if ($canonicalBarangay === null) {
            $errors[] = 'Please choose your barangay in San Jose del Monte. This portal only accepts reports within the city.';
        } else {
            $barangay = $canonicalBarangay;
        }
        if (($latitude === null) !== ($longitude === null)
            || ($latitude !== null && !sjdm_contains($latitude, $longitude))) {
            $errors[] = 'The pinned location is outside San Jose del Monte. This portal only accepts reports within the city.';
        }

        if ($errors) {
            json_fail(implode(' ', $errors), 422);
        }

        // ---- Anti-Spam, Heuristic, and Duplicate Content Analysis ----
        $spamAnalysis = SpamService::analyze($subject, $description, $fullName, $email, [
            'ip' => $callerIp,
            'honeypot' => $honeypot,
            'form_loaded_at' => $formLoadedAt,
        ]);

        if ($spamAnalysis['blocked']) {
            json_fail($spamAnalysis['block_message'] ?? 'Unable to process submission. Please check your details and try again.', 422);
        }

        // AI classification — submission creation NEVER depends on this succeeding.
        // Runs before the transaction so a slow Gemini call doesn't hold DB locks.
        $classification = AIClassifier::classifySafely($subject, $description);
        $submissionType = $preferredType ?: $classification['submissionType'];
        // What the citizen picked wins over the AI's guess; staff can still correct it in Moderation.
        if ($preferredCategory) $classification['category'] = $preferredCategory;
        if ($preferredPriority) $classification['priority'] = $preferredPriority;

        // The AI recognised the description as gibberish / keyboard-mash that slipped
        // past the local readability checks: queue it already flagged as spam so the
        // moderator sees it straight away. Not auto-rejected — a human still decides.
        if (empty($classification['readable'])) {
            $spamAnalysis['spam_flags'][] = 'Unreadable / gibberish (AI)';
            $spamAnalysis['spam_score'] = max(80, min(100, $spamAnalysis['spam_score'] + 60));
            $spamAnalysis['flag_label'] = 'Potential Spam';
        }

        $pdo = db();

        try {
            DB::beginTransaction(); // Laravel's, so it nests (savepoints) inside an outer transaction

            // Keep the latest contact number on their account.
            $pdo->prepare('UPDATE citizens SET phone = ? WHERE id = ?')->execute([$phone, $citizenId]);

            $referenceNumber = generate_reference_number($submissionType);

            $insSub = $pdo->prepare("
                INSERT INTO submissions
                  (reference_number, citizen_id, submission_type, subject, description, content_hash, category, priority, summary,
                   ai_confidence, ai_reasoning, sentiment, sentiment_score, city, barangay, street, latitude, longitude, status, moderation_status, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'Pending', 'Pending', NOW())
            ");
            $insSub->execute([
                $referenceNumber, $citizenId, $submissionType, $subject, $description,
                $spamAnalysis['content_hash'],
                $classification['category'], $classification['priority'], $classification['summary'],
                $classification['confidence'], $classification['reasoning'],
                $classification['sentiment'], $classification['sentiment_score'],
                $city ?: null, $barangay ?: null, $street ?: null, $latitude, $longitude,
            ]);
            $submissionId = (int) $pdo->lastInsertId();

            if ($submissionType === 'complaint') {
                $pdo->prepare('INSERT INTO complaints (submission_id, sla_status) VALUES (?, ?)')
                    ->execute([$submissionId, $classification['priority'] === 'Critical' ? 'At Risk' : 'On Track']);
            } elseif ($submissionType === 'proposal') {
                $pdo->prepare('INSERT INTO proposals (submission_id, proposal_status) VALUES (?, ?)')
                    ->execute([$submissionId, 'Under Review']);
            }

            // Every new citizen submission goes into the moderation queue first.
            // Attach spam scoring and security flags if detected.
            $moderationContentType = ucfirst($submissionType);
            $spamFlagsStr = !empty($spamAnalysis['spam_flags']) ? implode(', ', $spamAnalysis['spam_flags']) : null;
            $primaryFlag = $spamAnalysis['flag_label'] ?? null;

            $insMod = $pdo->prepare('
                INSERT INTO moderation_queue
                  (content_type, submission_id, citizen_id, content_snapshot, flag, spam_score, spam_flags, status)
                VALUES (?,?,?,?,?,?,?,?)
            ');
            $insMod->execute([
                $moderationContentType, $submissionId, $citizenId, mb_substr($description, 0, 480),
                $primaryFlag, $spamAnalysis['spam_score'], $spamFlagsStr, 'Pending'
            ]);
            $moderationId = (int) $pdo->lastInsertId();

            // Photos are required, so a failed upload rolls back the whole submission.
            $uploadResult = FileUploadService::handle($photos, $submissionId);
            if (!$uploadResult['ok'] || !$uploadResult['attachments']) {
                throw new RuntimeException('Attachment upload failed: ' . ($uploadResult['error'] ?? 'no files stored'));
            }
            $storedPhotos = $uploadResult['attachments'];
            $insAtt = $pdo->prepare('INSERT INTO attachments (submission_id, original_name, stored_name, file_path, mime_type, file_size) VALUES (?,?,?,?,?,?)');
            foreach ($storedPhotos as $att) {
                $insAtt->execute([$submissionId, $att['original_name'], $att['stored_name'], $att['file_path'], $att['mime_type'], $att['file_size']]);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            FileUploadService::cleanup($storedPhotos ?? []);
            logger()->error('[CEPFMS] Submission creation failed: ' . $e->getMessage());
            json_fail('Submission could not be completed. Please try again.', 500);
        }

        // Record submission for rate limiting
        record_submission_attempt($callerIp);

        log_activity(null, 'Submission created', 'submission', $submissionId, ['reference' => $referenceNumber, 'type' => $submissionType]);
        log_activity(null, 'Sent to moderation queue', 'moderation_queue', $moderationId, ['reference' => $referenceNumber]);

        // Reply to the citizen now; the confirmation email is sent after the
        // connection closes so a slow SMTP server doesn't keep them waiting.
        // Confirmation email is best-effort — never blocks or fails the submission itself.
        defer(function () use ($email, $referenceNumber, $subject, $fullName) {
            try {
                EmailService::notifySubmissionReceived($email, $referenceNumber, $subject, $fullName);
            } catch (Throwable $e) {
                logger()->error('[CEPFMS] Confirmation email failed for ' . $referenceNumber . ': ' . $e->getMessage());
            }
        });

        return json_response(true, [
            'reference_number' => $referenceNumber,
            'submission_id' => $submissionId,
            'message' => 'Your submission has been received and is awaiting validation.'
        ]);
    }

    public function list(Request $request)
    {
        $q = $request->query();
        $staff = $this->staff();

        $pdo = db();

        $search = trim($q['search'] ?? '');
        $category = trim($q['category'] ?? '');
        $status = trim($q['status'] ?? '');
        $priority = trim($q['priority'] ?? '');
        $sentiment = trim($q['sentiment'] ?? '');
        $type = trim($q['type'] ?? ''); // optional: feedback|complaint|proposal
        // Date range (YYYY-MM-DD, inclusive); anything that isn't a real date is ignored.
        $validDate = fn($d) => ($dt = DateTime::createFromFormat('!Y-m-d', $d)) && $dt->format('Y-m-d') === $d ? $d : '';
        $dateFrom = $validDate(trim($q['date_from'] ?? ''));
        $dateTo = $validDate(trim($q['date_to'] ?? ''));
        $page = max(1, (int) ($q['page'] ?? 1));
        $perPage = 8;

        $where = ["s.moderation_status = 'Approved'"];
        $params = [];

        if ($type !== '') { $where[] = 's.submission_type = ?'; $params[] = $type; }
        if ($search !== '') {
            $where[] = '(s.subject LIKE ? OR s.reference_number LIKE ? OR c.full_name LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like, $like);
        }
        if ($category !== '') { $where[] = 's.category = ?'; $params[] = $category; }
        if ($status === 'Unresolved') { $where[] = "s.status != 'Resolved'"; }
        elseif ($status !== '') { $where[] = 's.status = ?'; $params[] = $status; }
        if ($priority !== '') { $where[] = 's.priority = ?'; $params[] = $priority; }
        if ($sentiment !== '') { $where[] = 's.sentiment = ?'; $params[] = $sentiment; }
        if ($dateFrom !== '') { $where[] = 's.created_at >= ?'; $params[] = $dateFrom; }
        if ($dateTo !== '') { $where[] = 's.created_at < DATE_ADD(?, INTERVAL 1 DAY)'; $params[] = $dateTo; }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // ?format=csv — download every row matching the current filters (no paging).
        if (($q['format'] ?? '') === 'csv') {
            $stmt = $pdo->prepare("
                SELECT s.reference_number, s.submission_type, s.subject, s.category,
                       s.status, s.priority, s.sentiment, c.full_name AS citizen_name, s.created_at
                FROM submissions s
                LEFT JOIN citizens c ON c.id = s.citizen_id
                $whereSql
                ORDER BY s.created_at DESC
            ");
            $stmt->execute($params);

            log_activity((int) $staff['id'], 'export_csv', 'submission', null, array_filter(compact('search', 'category', 'status', 'priority', 'sentiment', 'type', 'dateFrom', 'dateTo')));

            return response()->streamDownload(function () use ($stmt) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 (ñ, accents) correctly
            fputcsv($out, ['Reference', 'Type', 'Subject', 'Category', 'Status', 'Priority', 'Sentiment', 'Citizen', 'Submitted']);
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                // Match the table's titleCase(): uppercase first letters only, keep internal capitals (McDonald).
                $row[7] = $row[7] !== null ? preg_replace_callback('/(^|[\s\'-])\p{L}/u', fn($m) => mb_strtoupper($m[0], 'UTF-8'), $row[7]) : '';
                // Neutralise spreadsheet formulas in citizen-supplied text (CSV injection).
                $row = array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $row);
                fputcsv($out, $row);
            }
            fclose($out);
            }, 'submissions-' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM submissions s LEFT JOIN citizens c ON c.id = s.citizen_id $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT s.id, s.reference_number, s.subject, s.category, s.status, s.priority, s.sentiment, s.created_at, s.submission_type,
                   c.full_name AS citizen_name
            FROM submissions s
            LEFT JOIN citizens c ON c.id = s.citizen_id
            $whereSql
            ORDER BY s.created_at DESC
            LIMIT $perPage OFFSET $offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return json_response(true, ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    public function get(Request $request)
    {
        $q = $request->query();
        $staff = $this->staff();

        $id = (int) ($q['id'] ?? 0);
        if (!$id) json_fail('Missing submission ID.', 422);

        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT s.*, c.full_name AS citizen_name, c.email AS citizen_email, c.phone AS citizen_phone,
                   p.proposal_status, cm.sla_status
            FROM submissions s
            LEFT JOIN citizens c ON c.id = s.citizen_id
            LEFT JOIN proposals p ON p.submission_id = s.id
            LEFT JOIN complaints cm ON cm.submission_id = s.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $submission = $stmt->fetch();

        if (!$submission) json_fail('Submission not found.', 404);

        $att = $pdo->prepare('SELECT id, original_name, file_path, mime_type FROM attachments WHERE submission_id = ?');
        $att->execute([$id]);
        $submission['attachments'] = $att->fetchAll();

        $history = $pdo->prepare("
            SELECT a.action, a.created_at, s.full_name AS staff_name, a.metadata
            FROM activity_log a LEFT JOIN users s ON s.id = a.staff_id
            WHERE a.entity = 'submission' AND a.entity_id = ?
            ORDER BY a.created_at DESC
        ");
        $history->execute([$id]);
        $submission['history'] = $history->fetchAll();

        return json_response(true, $submission);
    }

    public function update(Request $request)
    {
        $staff = $this->staff();


        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        $action = $input['action'] ?? '';
        if (!$id || !$action) json_fail('Missing required fields.', 422);

        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
        $stmt->execute([$id]);
        $submission = $stmt->fetch();
        if (!$submission) json_fail('Submission not found.', 404);

        $validStatuses = ['Pending','Received','In Review','Assigned','Resolved'];

        switch ($action) {
            case 'change_status':
                $newStatus = $input['status'] ?? '';
                if (!in_array($newStatus, $validStatuses, true)) json_fail('Invalid status.', 422);
                $pdo->prepare('UPDATE submissions SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
                if ($newStatus === 'Resolved') {
                    $pdo->prepare("UPDATE complaints SET closed_at = NOW(), sla_status = 'Met' WHERE submission_id = ?")->execute([$id]);
                }
                log_activity($staff['id'], 'Status changed', 'submission', $id, ['new_status' => $newStatus]);
                break;

            case 'reclassify':
                // Admin correction of the AI classification, done from Moderation &
                // Validation (admin-only, like the rest of moderation). The reference
                // number is kept as-is even if the type changes — the citizen tracks by it.
                if (!role_is_admin($staff['role'] ?? null)) json_fail('Only admins can change the classification.', 403);
                $type = $input['submission_type'] ?? '';
                $category = $input['category'] ?? '';
                $priority = $input['priority'] ?? '';
                $sentiment = $input['sentiment'] ?? '';
                if (!in_array($type, AIClassifier::TYPES, true)
                    || !in_array($category, AIClassifier::CATEGORIES, true)
                    || !in_array($priority, AIClassifier::PRIORITIES, true)
                    || !in_array($sentiment, AIClassifier::SENTIMENTS, true)) {
                    json_fail('Invalid classification.', 422);
                }

                $changes = [];
                foreach (['submission_type' => $type, 'category' => $category, 'priority' => $priority, 'sentiment' => $sentiment] as $field => $value) {
                    if ($submission[$field] !== $value) $changes[$field] = ['from' => $submission[$field], 'to' => $value];
                }
                if (!$changes) break;

                $pdo->beginTransaction();
                $pdo->prepare('UPDATE submissions SET submission_type = ?, category = ?, priority = ?, sentiment = ? WHERE id = ?')
                    ->execute([$type, $category, $priority, $sentiment, $id]);
                // Complaint/proposal pages join on these tables, so make sure the row exists for the new type.
                if ($type === 'complaint') {
                    $pdo->prepare('INSERT IGNORE INTO complaints (submission_id, sla_status) VALUES (?, ?)')
                        ->execute([$id, $priority === 'Critical' ? 'At Risk' : 'On Track']);
                } elseif ($type === 'proposal') {
                    $pdo->prepare('INSERT IGNORE INTO proposals (submission_id, proposal_status) VALUES (?, ?)')
                        ->execute([$id, 'Under Review']);
                }
                if (isset($changes['submission_type'])) {
                    $pdo->prepare('UPDATE moderation_queue SET content_type = ? WHERE submission_id = ?')
                        ->execute([ucfirst($type), $id]);
                }
                $pdo->commit();
                log_activity($staff['id'], 'Classification corrected', 'submission', $id, $changes);
                break;

            default:
                json_fail('Unknown action.', 422);
        }

        return json_response(true, ['id' => $id]);
    }
}
