<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\EmailService;
use Throwable;

/**
 * User Accounts (super admins): create, edit, deactivate, delete, re-invite.
 */
class StaffController extends Controller
{
    public function create(Request $request)
    {
        $staff = $this->staff();

        $input = $this->input();
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $role = (string) ($input['role'] ?? 'staff');
        $title = trim((string) ($input['title'] ?? ''));

        if ($fullName === '' || $email === '') {
            json_fail('Name and email are required.', 422);
        }
        if (mb_strlen($fullName) > 150 || mb_strlen($title) > 120) {
            json_fail('Name or position is too long.', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            json_fail('Please enter a valid email address.', 422);
        }
        // New super admins are made with bin/make-super-admin.php, not from the UI.
        if (!in_array($role, ['admin', 'staff'], true)) {
            json_fail('Invalid role.', 422);
        }

        $pdo = db();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            json_fail('An account with that email already exists.', 409);
        }

        // Temporary password, emailed to the new user (they must change it at first login).
        $tempPassword = temp_password();

        try {
            $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash, role, title, is_active, must_change_password)
                 VALUES (?, ?, ?, ?, ?, 1, 1)'
            )->execute([$fullName, $email, password_hash($tempPassword, PASSWORD_DEFAULT), $role, $title !== '' ? $title : null]);
            $newId = (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            logger()->error('[CEPFMS] Staff create failed: ' . $e->getMessage());
            json_fail('Unable to create the account right now. Please try again shortly.', 500);
        }

        // Dev mode only logs the email, so nothing reaches the person — treat that like a failed send.
        $emailed = EmailService::sendAccountInvite($email, $fullName, $tempPassword) && !EmailService::isDevMode();

        log_activity($staff['id'], 'Staff account created', 'staff', $newId, [
            'email'   => $email,
            'role'    => $role,
            'emailed' => $emailed,
        ]);

        // The password goes back to the super admin only when the email didn't go out,
        // so they can pass it on themselves and nobody is locked out.
        return json_response(true, ['id' => $newId, 'email' => $email, 'emailed' => $emailed] + ($emailed ? [] : ['temp_password' => $tempPassword]));
    }

    public function update(Request $request)
    {
        // Super admin edits another account's name, email, or position. Role isn't
        // changed here, and like deactivate/delete, super admins and your own account
        // are off-limits (your own details are on Profile Settings).
                $staff = $this->staff();

        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        $fullName = preg_replace('/\s+/u', ' ', clean_str($input['full_name'] ?? ''));
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $title = preg_replace('/\s+/u', ' ', clean_str($input['title'] ?? ''));

        if ($id <= 0) {
            json_fail('Invalid account.', 422);
        }
        if ($id === (int) $staff['id']) {
            json_fail('Edit your own details on Profile Settings.', 422);
        }
        if ($fullName === '' || $email === '') {
            json_fail('Name and email are required.', 422);
        }
        if (mb_strlen($fullName) > 150 || mb_strlen($title) > 120) {
            json_fail('Name or position is too long.', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            json_fail('Please enter a valid email address.', 422);
        }

        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, full_name, email, role, title FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            json_fail('Account not found.', 404);
        }
        // Super admins are managed from the command line, same as creating them.
        if ($target['role'] === 'super_admin') {
            json_fail('Super admin accounts cannot be changed here.', 422);
        }

        $emailChanged = $email !== mb_strtolower($target['email']);
        if ($emailChanged) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                json_fail('Another account already uses that email.', 409);
            }
        }

        try {
            // A new email also clears any pending login code, which went to the old address.
            $pdo->prepare('UPDATE users SET full_name = ?, email = ?, title = ?' . ($emailChanged ? ', otp_code = NULL, otp_expires_at = NULL' : '') . ' WHERE id = ?')
                ->execute([$fullName, $email, $title !== '' ? $title : null, $id]);
        } catch (Throwable $e) {
            logger()->error('[CEPFMS] Staff update failed: ' . $e->getMessage());
            json_fail('Unable to update the account right now. Please try again shortly.', 500);
        }

        $changes = [];
        if ($fullName !== $target['full_name']) $changes['full_name'] = ['from' => $target['full_name'], 'to' => $fullName];
        if ($emailChanged) $changes['email'] = ['from' => $target['email'], 'to' => $email];
        if ($title !== (string) ($target['title'] ?? '')) $changes['title'] = ['from' => (string) ($target['title'] ?? ''), 'to' => $title];
        if ($changes) log_activity($staff['id'], 'Staff account updated', 'staff', $id, $changes);

        return json_response(true, ['id' => $id, 'changed' => array_keys($changes)]);
    }

    public function setActive(Request $request)
    {
        // Deactivate (e.g. staff who left) or reactivate an account. Unlike delete.php this
        // is reversible, and their responses, reviews, and activity log entries keep their author.
                $staff = $this->staff();

        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        $active = !empty($input['active']);

        if ($id <= 0) {
            json_fail('Invalid account.', 422);
        }
        if ($id === (int) $staff['id']) {
            json_fail('You cannot deactivate your own account.', 422);
        }

        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, email, role, is_active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            json_fail('Account not found.', 404);
        }
        // Super admins are managed from the command line, same as creating them.
        if ($target['role'] === 'super_admin') {
            json_fail('Super admin accounts cannot be changed here.', 422);
        }

        try {
            // Clearing the OTP also kills any login that's halfway through verification.
            $pdo->prepare('UPDATE users SET is_active = ?, otp_code = NULL, otp_expires_at = NULL WHERE id = ?')
                ->execute([$active ? 1 : 0, $id]);
        } catch (Throwable $e) {
            logger()->error('[CEPFMS] Staff set-active failed: ' . $e->getMessage());
            json_fail('Unable to update the account right now. Please try again shortly.', 500);
        }

        log_activity($staff['id'], $active ? 'Staff account reactivated' : 'Staff account deactivated', 'staff', $id, [
            'email' => $target['email'],
        ]);

        return json_response(true, ['id' => $id, 'active' => $active]);
    }

    public function delete(Request $request)
    {
        // Permanently delete an account. Their responses, moderation reviews, and activity
        // log entries stay, but lose their author (the foreign keys are ON DELETE SET NULL).
        // Deactivating (set-active.php) is the reversible option.
                $staff = $this->staff();

        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            json_fail('Invalid account.', 422);
        }
        if ($id === (int) $staff['id']) {
            json_fail('You cannot delete your own account.', 422);
        }

        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, full_name, email, role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            json_fail('Account not found.', 404);
        }
        // Super admins are managed from the command line, same as creating them.
        if ($target['role'] === 'super_admin') {
            json_fail('Super admin accounts cannot be deleted here.', 422);
        }

        try {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        } catch (Throwable $e) {
            logger()->error('[CEPFMS] Staff delete failed: ' . $e->getMessage());
            json_fail('Unable to delete the account right now. Please try again shortly.', 500);
        }

        // Name and email go in the details, since the account row itself is now gone.
        log_activity($staff['id'], 'Staff account deleted', 'staff', $id, [
            'name'  => $target['full_name'],
            'email' => $target['email'],
        ]);

        return json_response(true, ['id' => $id]);
    }

    public function resendInvite(Request $request)
    {
        /**
         * Re-send login details to someone who hasn't signed in yet (lost or spam-filtered
         * invite). A fresh temporary password replaces the old one, so an earlier email
         * that ended up in the wrong hands stops working.
         */
                $staff = $this->staff();

        $input = $this->input();
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            json_fail('Invalid account.', 422);
        }

        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, full_name, email, role, is_active, must_change_password FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            json_fail('Account not found.', 404);
        }
        if ($target['role'] === 'super_admin' || $id === (int) $staff['id']) {
            json_fail('This account cannot be changed here.', 422);
        }
        if (!$target['is_active']) {
            json_fail('Reactivate the account before sending login details.', 422);
        }
        // Once they've set their own password, a temporary one would only lock them out.
        if (!$target['must_change_password']) {
            json_fail('This person has already set their own password. They can use "Forgot password" on the login page.', 422);
        }

        $tempPassword = temp_password();
        try {
            // Clearing the OTP also cancels a login that was halfway through with the old password.
            $pdo->prepare('UPDATE users SET password_hash = ?, otp_code = NULL, otp_expires_at = NULL WHERE id = ?')
                ->execute([password_hash($tempPassword, PASSWORD_DEFAULT), $id]);
        } catch (Throwable $e) {
            logger()->error('[CEPFMS] Staff resend invite failed: ' . $e->getMessage());
            json_fail('Unable to update the account right now. Please try again shortly.', 500);
        }

        // Dev mode only logs the email, so nothing reaches the person — treat that like a failed send.
        $emailed = EmailService::sendAccountInvite($target['email'], $target['full_name'], $tempPassword, true) && !EmailService::isDevMode();

        log_activity($staff['id'], 'Staff login details resent', 'staff', $id, [
            'email'   => $target['email'],
            'emailed' => $emailed,
        ]);

        return json_response(true, ['id' => $id, 'email' => $target['email'], 'emailed' => $emailed] + ($emailed ? [] : ['temp_password' => $tempPassword]));
    }
}
