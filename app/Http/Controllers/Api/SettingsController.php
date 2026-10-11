<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Throwable;

/**
 * System Settings (super admins): branding.
 */
class SettingsController extends Controller
{
    public function branding(Request $request)
    {
        $post = $request->post();
        /**
         * Super admin saves the branding on System Settings: names, contact details,
         * reply sign-off, and optionally a new logo (multipart form, field "logo") or
         * a return to the bundled city seal ("remove_logo" = 1).
         */
                $staff = $this->staff();

        // Field => [label, max length, required]
        $fields = [
            'brand_short_name' => ['System short name', 40, true],
            'brand_full_name'  => ['System full name', 150, true],
            'brand_government' => ['Government name', 150, true],
            'brand_office'     => ['Office name', 120, true],
            'contact_address'  => ['Address', 200, true],
            'contact_phone'    => ['Phone', 60, true],
            'contact_email'    => ['Email', 190, true],
            'contact_hours'    => ['Office hours', 100, true],
            'email_signoff'    => ['Reply sign-off', 80, true],
        ];

        $values = [];
        foreach ($fields as $key => [$label, $max, $required]) {
            $value = preg_replace('/\s+/u', ' ', clean_str($request->post($key, '')));
            if ($required && $value === '') json_fail("$label is required.", 422);
            if (mb_strlen($value) > $max) json_fail("$label is too long (max $max characters).", 422);
            $values[$key] = $value;
        }
        if (!filter_var($values['contact_email'], FILTER_VALIDATE_EMAIL)) {
            json_fail('Please enter a valid contact email.', 422);
        }

        // ---- Logo ----
        $oldLogo = setting('brand_logo');
        $newLogo = $oldLogo;
        $file = $request->file('logo');

        if ($file) {
            if (!$file->isValid()) {
                json_fail('The logo could not be uploaded. Please try again.', 422);
            }
            if ($file->getSize() > 1024 * 1024) {
                json_fail('The logo must be 1 MB or smaller.', 422);
            }
            // Real image check (not the file name or the browser's claim). No SVG: it can carry scripts.
            $types = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
            $size = @getimagesize($file->getRealPath());
            if (!isset($types[$mime]) || !$size || $size[0] < 32 || $size[1] < 32) {
                json_fail('The logo must be a PNG, JPG, or WebP image at least 32×32 pixels.', 422);
            }

            $dir = public_path(BRAND_LOGO_DIR);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                json_fail('Unable to save the logo right now.', 500);
            }
            $name = 'logo-' . bin2hex(random_bytes(6)) . '.' . $types[$mime];
            try {
                $file->move($dir, $name);
            } catch (Throwable $e) {
                json_fail('Unable to save the logo right now.', 500);
            }
            $newLogo = BRAND_LOGO_DIR . $name;
        } elseif (!empty($post['remove_logo'])) {
            $newLogo = '';
        }
        $values['brand_logo'] = $newLogo;

        // What changed, for the Audit Log (compared with what visitors saw before).
        $changed = [];
        foreach ($values as $key => $value) {
            if ($value !== ($key === 'brand_logo' ? $oldLogo : setting($key))) $changed[] = $key;
        }

        try {
            settings_save($values);
        } catch (Throwable $e) {
            logger()->error('[CEPFMS] Saving branding failed: ' . $e->getMessage());
            if ($newLogo !== $oldLogo && $newLogo !== '') @unlink(public_path($newLogo));
            json_fail('Unable to save settings right now. Please try again shortly.', 500);
        }

        // Old uploaded logo is no longer used (never touches the bundled seal).
        if ($oldLogo !== $newLogo && str_starts_with($oldLogo, BRAND_LOGO_DIR)) {
            @unlink(public_path($oldLogo));
        }

        if ($changed) log_activity($staff['id'], 'Branding updated', 'settings', null, ['changed' => $changed]);

        return json_response(true, ['changed' => $changed]);
    }
}
