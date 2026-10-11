<?php

namespace App\Http\Middleware;

use App\Support\CitizenAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Citizen portal access control.
 *
 *   ->middleware('citizen')           signed in with Google
 *   ->middleware('citizen:verified')  signed in, and their ID has been approved
 *
 * Pages redirect (to sign-in, or to the verification page); JSON endpoints
 * under /api answer 401/403, with X-Citizen-Login so the page can send the
 * citizen to sign in.
 */
class RequireCitizen
{
    public function handle(Request $request, Closure $next, string $level = 'signed_in'): Response
    {
        $isApi = $request->is('api/*');

        if (!CitizenAuth::check()) {
            if ($isApi) {
                return json_response(false, null, 'Please sign in to continue.', 401, ['X-Citizen-Login' => '1']);
            }
            // Back to this page after signing in (GET pages only).
            if ($request->isMethod('GET')) {
                session(['citizen_intended' => $request->getRequestUri()]);
            }
            return redirect(app_href('citizen/login'));
        }

        if ($level === 'verified' && !CitizenAuth::isVerified()) {
            if ($isApi) {
                return json_response(false, null, 'Your ID must be verified before you can submit feedback.', 403);
            }
            return redirect(app_href('citizen/verify'));
        }

        return $next($request);
    }
}
