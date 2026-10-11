<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Staff sign-in pages. The sign-in itself (password → emailed code) runs
 * through Api\AuthController; these only render the screens.
 */
class AuthController extends Controller
{
    public function login()
    {
        if (is_staff_logged_in() && current_staff()) {
            return redirect(app_href('admin/dashboard'));
        }
        return view('auth.login', ['pageTitle' => 'Login', 'flashes' => flash_get()]);
    }

    public function logout(Request $request)
    {
        if (is_staff_logged_in() && ($current = current_staff())) {
            $roleLabel = role_label($current['role'] ?? null);
            log_activity((int) $current['id'], "{$roleLabel} logged out", 'staff', (int) $current['id'], []);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        forget_current_staff();

        return redirect(app_href('auth/login'));
    }

    public function forgotPassword()
    {
        if (is_staff_logged_in() && current_staff()) {
            return redirect(app_href('admin/dashboard'));
        }
        return view('auth.forgot-password', ['pageTitle' => 'Forgot Password', 'flashes' => flash_get()]);
    }

    /**
     * Landing page from the emailed reset link. Validates the token up front
     * so an invalid/expired link never reaches the form; the reset itself
     * re-validates it on submit.
     */
    public function resetPassword(Request $request)
    {
        if (is_staff_logged_in() && current_staff()) {
            return redirect(app_href('admin/dashboard'));
        }

        $staffId = (int) $request->query('id', 0);
        $token = (string) $request->query('token', '');

        $valid = false;
        $expiresIn = 0;
        if ($staffId > 0 && $token !== '') {
            $row = DB::table('users')->select('reset_token_hash', 'reset_token_expires_at')
                ->where('id', $staffId)->where('is_active', 1)->first();
            if ($row && !empty($row->reset_token_hash) && !empty($row->reset_token_expires_at)
                && strtotime($row->reset_token_expires_at) >= time()
                && hash_equals($row->reset_token_hash, hash('sha256', $token))
            ) {
                $valid = true;
                // Same clock the submit endpoint checks against.
                $expiresIn = max(0, strtotime($row->reset_token_expires_at) - time());
            }
        }

        if (!$valid) {
            flash_set('warning', 'This password reset link is invalid or has expired. Please request a new one.');
            return redirect(app_href('auth/forgot-password'));
        }

        return view('auth.reset-password', [
            'pageTitle' => 'Reset Password',
            'flashes'   => flash_get(),
        ] + compact('staffId', 'token', 'expiresIn'));
    }

    /**
     * Reached voluntarily or via the forced redirect when
     * users.must_change_password = 1 (new accounts, seeded admins).
     */
    public function changePassword()
    {
        return view('auth.change-password', [
            'pageTitle' => 'Change Password',
            'staff'     => $this->staff(),
            'flashes'   => flash_get(),
        ]);
    }
}
