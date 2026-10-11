<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The signed-in citizen (Google sign-in) and their identity verification.
 *
 * Kept in the session under its own key, separate from the staff sign-in
 * (staff_id), so a staff member and a citizen account can be signed in from
 * the same browser without affecting each other.
 */
class CitizenAuth
{
    private const SESSION_KEY = 'citizen_id';

    /** Cached for the request: false = not looked up yet. */
    private static array|null|false $citizen = false;
    private static array|null|false $verification = false;

    public static function check(): bool
    {
        return self::current() !== null;
    }

    /** The signed-in citizen row (array), or null. A deactivated account counts as signed out. */
    public static function current(): ?array
    {
        if (self::$citizen === false) {
            $id = session(self::SESSION_KEY);
            $row = $id ? DB::table('citizens')
                ->select('id', 'full_name', 'email', 'phone', 'is_active')
                ->where('id', $id)->first() : null;
            self::$citizen = $row && $row->is_active ? (array) $row : null;
            if ($id && !self::$citizen) {
                session()->forget(self::SESSION_KEY);
            }
        }
        return self::$citizen;
    }

    /** The citizen's latest verification (array), or null if they haven't sent one. */
    public static function verification(): ?array
    {
        if (self::$verification === false) {
            $citizen = self::current();
            $row = $citizen ? DB::table('citizen_verifications')
                ->select('id', 'full_name', 'id_type', 'id_number_last4', 'barangay', 'status', 'reject_reason', 'created_at', 'reviewed_at')
                ->where('citizen_id', $citizen['id'])
                ->orderByDesc('id')->first() : null;
            self::$verification = $row ? (array) $row : null;
        }
        return self::$verification;
    }

    /** 'none', 'Pending', 'Approved' or 'Rejected'. */
    public static function status(): string
    {
        return self::verification()['status'] ?? 'none';
    }

    public static function isVerified(): bool
    {
        return self::status() === 'Approved';
    }

    public static function login(int $citizenId): void
    {
        // New session ID (no fixation) without ending a staff sign-in in the same browser.
        session()->regenerate(true);
        session([self::SESSION_KEY => $citizenId]);
        self::forget();
    }

    public static function logout(): void
    {
        session()->forget(self::SESSION_KEY);
        session()->regenerate(true);
        self::forget();
    }

    /** Drop the per-request cache (after login/logout or a new verification). */
    public static function forget(): void
    {
        self::$citizen = false;
        self::$verification = false;
    }

    /**
     * Keyed hash of an ID number, for spotting the same ID on several accounts
     * without storing the number. Spaces and dashes are ignored.
     */
    public static function idNumberHash(string $idNumber): string
    {
        return hash_hmac('sha256', self::normalizeIdNumber($idNumber), (string) config('app.key'));
    }

    public static function normalizeIdNumber(string $idNumber): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $idNumber));
    }
}
