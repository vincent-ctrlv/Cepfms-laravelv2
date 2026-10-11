<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs staff out after a few minutes without activity (STAFF_IDLE_SECONDS).
 *
 * Only the staff sign-in is cleared and the session ID rotated; the session
 * itself (and its CSRF token) is kept, because it is shared with the citizen
 * portal — a staff member idling out in one tab must not break a citizen
 * form being filled in another, and the "session expired" flash must survive.
 */
class StaffIdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $idleLimit = (int) config('cepfms.staff_idle_seconds', 180);
        $last = (int) $session->get('_last_activity', 0);

        if ($session->has('staff_id') && $last > 0 && (time() - $last) > $idleLimit) {
            $session->forget(['staff_id', 'pending_staff_id', 'pending_since', 'otp_sent_at', 'otp_resends']);
            $session->migrate(true);
            forget_current_staff();
        }

        // Background polling (notification bell, dashboard KPIs) runs every
        // minute on every open admin tab; counting it as activity would mean
        // the session never idles.
        if ($request->header('X-Background-Refresh') !== '1' || !$session->has('_last_activity')) {
            $session->put('_last_activity', time());
        }

        return $next($request);
    }
}
