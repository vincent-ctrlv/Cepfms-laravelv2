<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff portal access control.
 *
 *   ->middleware('staff')              any signed-in staff account
 *   ->middleware('staff:admin')        admins and super admins
 *   ->middleware('staff:super_admin')  super admins only
 *
 * Pages redirect (to the login page, or the dashboard when the role is
 * missing) with a flash message; JSON endpoints under /api answer 401/403.
 * The 401 carries X-Session-Expired so the browser's CivicConnect.apiFetch
 * sends the user to the login page, where the flash explains why.
 */
class RequireStaff
{
    public function handle(Request $request, Closure $next, string $level = 'staff'): Response
    {
        $isApi = $request->is('api/*');
        $staff = is_staff_logged_in() ? current_staff() : null;

        if (!$staff) {
            flash_set('warning', 'Your session has expired. Please log in again.');
            if ($isApi) {
                return json_response(false, null, 'Your session has expired. Please log in again.', 401, ['X-Session-Expired' => '1']);
            }
            return redirect(app_href('auth/login'));
        }

        // Force a password change before any other admin page is usable —
        // closes the window where a seeded/temporary credential stays valid.
        if (!$isApi && !empty($staff['must_change_password']) && !$request->is('auth/change-password')) {
            return redirect(app_href('auth/change-password'));
        }

        $allowed = match ($level) {
            'admin'       => role_is_admin($staff['role']),
            'super_admin' => $staff['role'] === 'super_admin',
            default       => true,
        };
        if (!$allowed) {
            if ($isApi) {
                return json_response(false, null, 'You do not have permission to perform this action.', 403);
            }
            flash_set('warning', 'You do not have permission to access that page.');
            return redirect(app_href('admin/dashboard'));
        }

        // Controllers read the signed-in account from the request.
        $request->attributes->set('staff', $staff);

        return $next($request);
    }
}
