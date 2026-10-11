<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * CEPFMS — File Upload Service
 * Validates and securely stores citizen-submitted photo attachments under
 * public/uploads/submissions/{submission_id}/ with randomized names.
 */
class FileUploadService
{
    /** MIME type → allowed extensions, checked against the file's real content. */
    private static function allowed(): array
    {
        return config('cepfms.uploads.allowed_mime');
    }

    /**
     * Checks uploads without storing anything, so a bad photo can reject the
     * submission before any rows are written.
     *
     * @param UploadedFile[] $files
     * @return string|null Error message, or null when at least one valid photo is present
     */
    public static function validate(array $files): ?string
    {
        $max = config('cepfms.uploads.max_files');
        if (!$files) {
            return 'Please attach at least one photo of the issue.';
        }
        if (count($files) > $max) {
            return 'You may upload a maximum of ' . $max . ' images.';
        }
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                return 'Unable to upload this file. Please check the file type and size.';
            }
            if ($file->getSize() > config('cepfms.uploads.max_size')) {
                return 'Each photo must be smaller than 5MB.';
            }
            if (!isset(self::allowed()[self::realMime($file)])) {
                return 'Unsupported file type. Please upload JPG, PNG, WEBP, or HEIC images only.';
            }
        }
        return null;
    }

    /**
     * Stores the (already validated) photos for a submission.
     *
     * @param UploadedFile[] $files
     * @return array{ok:bool, attachments:array, error:?string}
     */
    public static function handle(array $files, int $submissionId): array
    {
        if (!$files) {
            return ['ok' => true, 'attachments' => [], 'error' => null];
        }

        $relativeDir = config('cepfms.uploads.dir') . $submissionId . '/';
        $targetDir = public_path($relativeDir);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return ['ok' => false, 'attachments' => [], 'error' => 'Unable to upload this file. Please check the file type and size.'];
        }

        $stored = [];
        foreach ($files as $file) {
            $mime = self::realMime($file);
            if (!$file->isValid() || $file->getSize() > config('cepfms.uploads.max_size') || !isset(self::allowed()[$mime])) {
                self::cleanup($stored);
                return ['ok' => false, 'attachments' => [], 'error' => 'Unable to upload this file. Please check the file type and size.'];
            }

            // Never trust the user-provided filename — generate a random, safe one.
            $storedName = bin2hex(random_bytes(16)) . '.' . self::allowed()[$mime][0];
            $size = $file->getSize();
            $originalName = $file->getClientOriginalName();

            try {
                $file->move($targetDir, $storedName);
            } catch (\Throwable $e) {
                self::cleanup($stored);
                return ['ok' => false, 'attachments' => [], 'error' => 'Unable to upload this file. Please check the file type and size.'];
            }
            @chmod($targetDir . $storedName, 0644);

            $stored[] = [
                'original_name' => mb_substr(basename($originalName), 0, 255),
                'stored_name'   => $storedName,
                'file_path'     => $relativeDir . $storedName,
                'mime_type'     => $mime,
                'file_size'     => $size,
            ];
        }

        return ['ok' => true, 'attachments' => $stored, 'error' => null];
    }

    /** Removes stored files (used when the surrounding submission is rolled back). */
    public static function cleanup(array $stored): void
    {
        foreach ($stored as $file) {
            $full = public_path($file['file_path']);
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }

    /** MIME type sniffed from the file's bytes, not the browser's claim or the extension. */
    private static function realMime(UploadedFile $file): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($finfo, $file->getRealPath() ?: $file->getPathname());
        finfo_close($finfo);
        return $mime;
    }
}
