<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Profile Settings: own name/position and two-factor sign-in.
 */
class ProfileController extends Controller
{
    public function update(Request $request)
    {
        /**
         * Signed-in staff update their own name. Position is super admin only (for
         * anyone else the submitted value is ignored), and email (the sign-in
         * address the login codes go to) and role aren't edited here.
         */
                $staff = $this->staff();

        $input = $this->input();
        $fullName = preg_replace('/\s+/u', ' ', clean_str($input['full_name'] ?? ''));
        $title = $staff['role'] === 'super_admin'
            ? preg_replace('/\s+/u', ' ', clean_str($input['title'] ?? ''))
            : (string) ($staff['title'] ?? '');

        if ($fullName === '') {
            json_fail('Please enter your full name.', 422);
        }
        if (mb_strlen($fullName) > 150 || mb_strlen($title) > 120) {
            json_fail('Name or position is too long.', 422);
        }

        db()->prepare('UPDATE users SET full_name = ?, title = ? WHERE id = ?')
            ->execute([$fullName, $title !== '' ? $title : null, $staff['id']]);

        $changes = [];
        if ($fullName !== $staff['full_name']) $changes['full_name'] = $fullName;
        if ($title !== (string) ($staff['title'] ?? '')) $changes['title'] = $title;
        if ($changes) log_activity($staff['id'], 'Profile updated', 'staff', $staff['id'], $changes);

        return json_response(true, [
            'full_name' => $fullName,
            'title' => $title,
            'subtitle' => $title !== '' ? $title : role_label($staff['role']),
        ]);
    }

    public function twoFactor(Request $request)
    {
        /**
         * Signed-in staff turn two-factor sign-in (the emailed 6-digit code) on or off
         * for their own account. Turning it off needs their current password, so an
         * unattended session can't quietly weaken the account. Super admins can't turn
         * it off at all.
         *
         * POST JSON: { enabled: bool, password?: string }
         */
                $staff = $this->staff();

        $input = $this->input();
        $enable = !empty($input['enabled']);

        if ($staff['role'] === 'super_admin') {
            json_fail('Two-factor sign-in is always on for super admins.', 422);
        }

        $pdo = db();
        $stmt = $pdo->prepare('SELECT password_hash, two_factor_enabled FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$staff['id']]);
        $account = $stmt->fetch();
        if (!$account) json_fail('Account not found.', 404);

        if ((bool) $account['two_factor_enabled'] === $enable) {
            return json_response(true, ['enabled' => $enable]); // already that way, nothing to change
        }

        if (!$enable) {
            // Same limit as the login form, so this can't be used to guess the password.
            if (login_rate_limited($staff['email'], 'password')) {
                json_rate_limited('Too many attempts. Please wait for the timer to finish before trying again.', login_retry_after($staff['email'], 'password'));
            }
            if (!password_verify((string) ($input['password'] ?? ''), $account['password_hash'])) {
                record_login_attempt($staff['email'], 'password');
                json_fail('Incorrect password.', 401);
            }
            clear_login_attempts($staff['email'], 'password');
        }

        $pdo->prepare('UPDATE users SET two_factor_enabled = ? WHERE id = ?')->execute([$enable ? 1 : 0, $staff['id']]);
        log_activity($staff['id'], $enable ? 'Two-factor turned on' : 'Two-factor turned off', 'staff', $staff['id'], []);

        return json_response(true, ['enabled' => $enable]);
    }
}
