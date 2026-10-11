<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmailService;
use App\Support\CitizenAuth;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Citizen identity verification: the citizen sends a government ID photo and
 * a selfie holding it; an admin approves or rejects. Only approved citizens
 * can submit feedback.
 *
 * The photos are stored on the private "local" disk (storage/app/private), never
 * under public/, and are only served to signed-in admins by photo().
 */
class VerificationController extends Controller
{
    /** Why an admin may reject an ID (shown to the citizen with the optional note). */
    public const REJECT_REASONS = [
        'ID not readable'      => 'The photo is blurry, cut off, or covered, so the details cannot be read.',
        'Address outside SJDM' => 'The address on the ID is not in San Jose del Monte.',
        'Name does not match'  => 'The name entered does not match the name on the ID.',
        'Selfie does not match' => 'The selfie does not clearly show the same person holding the same ID.',
        'ID not accepted'      => 'The ID is expired, not government-issued, or does not show an address.',
        'Other'                => 'Another problem, explained in the note.',
    ];

    private const PHOTO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    // ---- Citizen ---------------------------------------------------------

    public function store(Request $request)
    {
        $citizen = CitizenAuth::current();
        $status = CitizenAuth::status();
        if ($status === 'Approved') json_fail('Your identity is already verified.', 409);
        if ($status === 'Pending') json_fail('Your ID is already being reviewed. We will email you once it is checked.', 409);

        $recent = DB::table('citizen_verifications')
            ->where('citizen_id', $citizen['id'])
            ->where('created_at', '>=', now()->subDay())->count();
        if ($recent >= (int) config('cepfms.verification.max_per_day')) {
            json_fail('You have reached the limit of verification requests for today. Please try again tomorrow.', 429);
        }

        $fullName = preg_replace('/\s+/u', ' ', clean_str($request->post('full_name', '')));
        $idType = (string) $request->post('id_type', '');
        $idNumber = CitizenAuth::normalizeIdNumber((string) $request->post('id_number', ''));
        $barangay = sjdm_barangay(clean_str($request->post('barangay', '')));

        $errors = [];
        if ($fullName === '' || mb_strlen($fullName) > 150) $errors[] = 'Enter your full name exactly as it appears on your ID.';
        if (!in_array($idType, config('cepfms.verification.id_types'), true)) $errors[] = 'Choose the type of ID you are using.';
        if (strlen($idNumber) < 4 || strlen($idNumber) > 30) $errors[] = 'Enter the ID number shown on your ID.';
        if ($barangay === null) $errors[] = 'Choose your barangay in San Jose del Monte.';
        if (($e = $this->photoError($request->file('id_photo'), 'ID photo')) !== null) $errors[] = $e;
        if (($e = $this->photoError($request->file('selfie_photo'), 'selfie')) !== null) $errors[] = $e;
        if (!$request->boolean('consent')) $errors[] = 'Please agree to the processing of your ID for verification.';
        if ($errors) json_fail(implode(' ', $errors), 422);

        $dir = 'verifications/' . $citizen['id'];
        $stored = [];
        try {
            foreach (['id_photo', 'selfie_photo'] as $field) {
                $file = $request->file($field);
                $name = bin2hex(random_bytes(16)) . '.' . self::PHOTO_TYPES[$this->mime($file)];
                $stored[$field] = Storage::disk('local')->putFileAs($dir, $file, $name);
                if (!$stored[$field]) throw new \RuntimeException("Could not store {$field}");
            }

            DB::table('citizen_verifications')->insert([
                'citizen_id'      => $citizen['id'],
                'full_name'       => $fullName,
                'id_type'         => $idType,
                'id_number_hash'  => CitizenAuth::idNumberHash($idNumber),
                'id_number_last4' => substr($idNumber, -4),
                'barangay'        => $barangay,
                'id_photo'        => $stored['id_photo'],
                'selfie_photo'    => $stored['selfie_photo'],
                'status'          => 'Pending',
            ]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete(array_values(array_filter($stored)));
            logger()->error('[CEPFMS] Saving ID verification failed: ' . $e->getMessage());
            json_fail('We could not save your ID right now. Please try again shortly.', 500);
        }

        CitizenAuth::forget();
        return json_response(true, ['status' => 'Pending'], 'Thank you. Your ID has been sent for review.');
    }

    /**
     * Citizen updates their own mobile number. Name (from the approved ID) and
     * email (from Google) aren't editable here.
     */
    public function updateProfile(Request $request)
    {
        $citizen = CitizenAuth::current();
        $phone = preg_replace('/[\s\-()]/', '', clean_str($this->input()['phone'] ?? ''));
        if (!preg_match('/^(09|\+639)\d{9}$/', $phone)) {
            json_fail('Enter a valid mobile number (e.g. 09171234567).', 422);
        }

        DB::table('citizens')->where('id', $citizen['id'])->update(['phone' => $phone]);
        return json_response(true, ['phone' => $phone], 'Your mobile number has been saved.');
    }

    // ---- Admin -----------------------------------------------------------

    public function list(Request $request)
    {
        $status = (string) $request->query('status', 'Pending');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 8;

        $query = DB::table('citizen_verifications as v')->join('citizens as c', 'c.id', '=', 'v.citizen_id');
        if (in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
            $query->where('v.status', $status);
        }

        $total = (clone $query)->count();
        $page = min($page, max(1, (int) ceil($total / $perPage)));

        $rows = $query
            ->leftJoin('users as u', 'u.id', '=', 'v.reviewed_by')
            ->select(
                'v.id', 'v.full_name', 'v.id_type', 'v.id_number_last4', 'v.id_number_hash', 'v.barangay', 'v.status',
                'v.reject_reason', 'v.created_at', 'v.reviewed_at', 'v.citizen_id',
                'c.email AS citizen_email', 'c.full_name AS account_name', 'u.full_name AS reviewer_name'
            )
            ->orderBy($status === 'Pending' ? 'v.created_at' : 'v.id', $status === 'Pending' ? 'asc' : 'desc')
            ->offset(($page - 1) * $perPage)->limit($perPage)->get()
            ->map(function ($row) {
                $row = (array) $row;
                // Same ID number on another account that's verified or waiting: worth a closer look.
                $row['duplicate_accounts'] = DB::table('citizen_verifications')
                    ->where('id_number_hash', $row['id_number_hash'])
                    ->where('citizen_id', '<>', $row['citizen_id'])
                    ->whereIn('status', ['Pending', 'Approved'])
                    ->distinct()->count('citizen_id');
                unset($row['id_number_hash']);
                $row['id_photo_url'] = app_href("admin/verifications/{$row['id']}/id");
                $row['selfie_photo_url'] = app_href("admin/verifications/{$row['id']}/selfie");
                return $row;
            })->all();

        return json_response(true, ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    public function action(Request $request)
    {
        $staff = $this->staff();
        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        $action = (string) ($input['action'] ?? '');
        if (!$id || !in_array($action, ['approve', 'reject'], true)) json_fail('Missing required fields.', 422);

        $item = DB::table('citizen_verifications as v')->join('citizens as c', 'c.id', '=', 'v.citizen_id')
            ->select('v.*', 'c.email AS citizen_email')->where('v.id', $id)->first();
        if (!$item) json_fail('Verification not found.', 404);
        if ($item->status !== 'Pending') json_fail('This ID has already been reviewed.', 409);

        $rejectReason = null;
        if ($action === 'reject') {
            $reason = is_string($input['reason'] ?? null) ? trim($input['reason']) : '';
            if (!array_key_exists($reason, self::REJECT_REASONS)) json_fail('Choose a reason for rejecting this ID.', 422);
            $note = is_string($input['note'] ?? null) ? trim($input['note']) : '';
            if ($reason === 'Other' && $note === '') json_fail('Add a note explaining the problem.', 422);
            $rejectReason = mb_substr($reason . ($note !== '' ? ' — ' . $note : ''), 0, 255);
        }

        DB::transaction(function () use ($id, $item, $action, $rejectReason, $staff) {
            DB::table('citizen_verifications')->where('id', $id)->update([
                'status'        => $action === 'approve' ? 'Approved' : 'Rejected',
                'reject_reason' => $rejectReason,
                'reviewed_by'   => $staff['id'],
                'reviewed_at'   => now(),
            ]);
            if ($action === 'approve') {
                // The name on the ID becomes the name on their submissions.
                DB::table('citizens')->where('id', $item->citizen_id)->update(['full_name' => $item->full_name]);
            }
        });

        log_activity((int) $staff['id'], $action === 'approve' ? 'ID verification approved' : 'ID verification rejected', 'citizen_verification', $id, array_filter([
            'name'   => $item->full_name,
            'email'  => $item->citizen_email,
            'reason' => $rejectReason,
        ]));

        try {
            EmailService::notifyVerificationResult($item->citizen_email, $item->full_name, $action === 'approve', $rejectReason);
        } catch (Throwable $e) {
            logger()->error('[CEPFMS] Verification result email failed: ' . $e->getMessage());
        }

        return json_response(true, ['id' => $id, 'status' => $action === 'approve' ? 'Approved' : 'Rejected']);
    }

    /** One of the two photos, to signed-in admins only, never cached by the browser. */
    public function photo(int $id, string $kind)
    {
        $column = ['id' => 'id_photo', 'selfie' => 'selfie_photo'][$kind] ?? null;
        $path = $column ? DB::table('citizen_verifications')->where('id', $id)->value($column) : null;
        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $response = response()->file(Storage::disk('local')->path($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition'    => 'inline',
        ]);
        // File responses default to "public"; ID photos must never sit in a shared cache.
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        return $response;
    }

    // ---------------------------------------------------------------------

    private function photoError(?UploadedFile $file, string $label): ?string
    {
        if (!$file || !$file->isValid()) return "Please attach your {$label}.";
        if ($file->getSize() > config('cepfms.verification.photo_max_size')) return "Your {$label} must be smaller than 5MB.";
        if (!isset(self::PHOTO_TYPES[$this->mime($file)])) return "Your {$label} must be a JPG, PNG, or WEBP image.";
        $size = @getimagesize($file->getRealPath());
        $min = (int) config('cepfms.verification.photo_min_px');
        if (!$size || min($size[0], $size[1]) < $min) return "Your {$label} is too small to read. Please take a clearer, closer photo.";
        return null;
    }

    private function mime(UploadedFile $file): string
    {
        return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
    }
}
