<?php

use App\Http\Controllers\Admin\PageController as AdminPages;
use App\Http\Controllers\Api;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CitizenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CEPFMS routes
|--------------------------------------------------------------------------
| Two portals:
|   /citizen/*  public citizen portal (no login)
|   /admin/*    staff/admin portal (password + emailed code)
| JSON endpoints used by the pages live under /api/* (same session + CSRF
| protection as the pages; the browser sends X-CSRF-TOKEN on every POST).
*/

Route::get('/', fn () => redirect(app_href('citizen')));
Route::get('/healthz', fn () => response()->json(['status' => 'ok', 'timestamp' => time()]));

// ---- Citizen portal (public) -------------------------------------------
Route::prefix('citizen')->controller(CitizenController::class)->group(function () {
    Route::get('/', 'home')->name('citizen.home');
    Route::get('/submit', 'submit')->name('citizen.submit');
    Route::get('/success', 'success')->name('citizen.success');
    Route::get('/track', 'track')->name('citizen.track');
    Route::get('/privacy', 'privacy')->name('citizen.privacy');
    Route::get('/terms', 'terms')->name('citizen.terms');
});

// ---- Staff sign-in -----------------------------------------------------
Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::get('/login', 'login')->name('login');
    Route::get('/logout', 'logout')->name('logout');
    Route::get('/forgot-password', 'forgotPassword')->name('password.request');
    Route::get('/reset-password', 'resetPassword')->name('password.reset');
    Route::get('/change-password', 'changePassword')->middleware('staff')->name('password.change');
});

// ---- Staff/admin portal pages ------------------------------------------
Route::prefix('admin')->middleware('staff')->controller(AdminPages::class)->group(function () {
    Route::get('/', fn () => redirect(app_href('admin/dashboard')));
    Route::get('/dashboard', 'dashboard')->name('admin.dashboard');
    Route::get('/submissions', 'submissions')->name('admin.submissions');
    Route::get('/complaints', 'complaints')->name('admin.complaints');
    Route::get('/proposals', 'proposals')->name('admin.proposals');
    Route::get('/responses', 'responses')->name('admin.responses');
    Route::get('/analytics', 'analytics')->name('admin.analytics');
    Route::get('/profile', 'profile')->name('admin.profile');
    Route::get('/moderation', 'moderation')->middleware('staff:admin')->name('admin.moderation');
    Route::get('/staff', 'accounts')->middleware('staff:super_admin')->name('admin.staff');
    Route::get('/audit-log', 'auditLog')->middleware('staff:super_admin')->name('admin.audit-log');
    Route::get('/settings', 'settings')->middleware('staff:super_admin')->name('admin.settings');
});

// ---- JSON API ----------------------------------------------------------
Route::prefix('api')->group(function () {
    // Public
    Route::post('/auth/login', [Api\AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [Api\AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [Api\AuthController::class, 'resetPassword']);
    Route::post('/submissions/create', [Api\SubmissionController::class, 'create']);

    // Any signed-in staff
    Route::middleware('staff')->group(function () {
        Route::match(['get', 'post'], '/auth/keepalive', [Api\AuthController::class, 'keepalive']);
        Route::post('/auth/change-password', [Api\AuthController::class, 'changePassword']);

        Route::get('/submissions/list', [Api\SubmissionController::class, 'list']);
        Route::get('/submissions/get', [Api\SubmissionController::class, 'get']);
        Route::post('/submissions/update', [Api\SubmissionController::class, 'update']);

        Route::get('/complaints/list', [Api\ComplaintController::class, 'list']);
        Route::post('/complaints/update', [Api\ComplaintController::class, 'update']);
        Route::post('/complaints/log-update', [Api\ComplaintController::class, 'logUpdate']);

        Route::get('/proposals/list', [Api\ProposalController::class, 'list']);
        Route::post('/proposals/update', [Api\ProposalController::class, 'update']);

        Route::get('/responses/list', [Api\ResponseController::class, 'list']);
        Route::get('/responses/submissions-for-response', [Api\ResponseController::class, 'submissionsForResponse']);
        Route::post('/responses/create', [Api\ResponseController::class, 'create']);
        Route::post('/responses/send', [Api\ResponseController::class, 'send']);

        Route::get('/notifications/recent', [Api\NotificationController::class, 'recent']);
        Route::get('/dashboard/kpis', [Api\DashboardController::class, 'kpis']);
        Route::post('/dashboard/pdf', [Api\DashboardController::class, 'pdf']);
        Route::get('/analytics/summary', [Api\AnalyticsController::class, 'summary']);
        Route::post('/analytics/pdf', [Api\AnalyticsController::class, 'pdf']);

        Route::post('/profile/update', [Api\ProfileController::class, 'update']);
        Route::post('/profile/two-factor', [Api\ProfileController::class, 'twoFactor']);
    });

    // Admins (moderation)
    Route::middleware('staff:admin')->group(function () {
        Route::get('/moderation/list', [Api\ModerationController::class, 'list']);
        Route::post('/moderation/action', [Api\ModerationController::class, 'action']);
    });

    // Super admins (accounts and system settings)
    Route::middleware('staff:super_admin')->group(function () {
        Route::post('/staff/create', [Api\StaffController::class, 'create']);
        Route::post('/staff/update', [Api\StaffController::class, 'update']);
        Route::post('/staff/set-active', [Api\StaffController::class, 'setActive']);
        Route::post('/staff/delete', [Api\StaffController::class, 'delete']);
        Route::post('/staff/resend-invite', [Api\StaffController::class, 'resendInvite']);
        Route::post('/settings/branding', [Api\SettingsController::class, 'branding']);
    });
});

// Old links from the plain-PHP version (e.g. /citizen/track.php?ref=… in
// emails already sent) keep working: drop the ".php" and redirect.
Route::get('{path}.php', function (string $path) {
    $path = $path === 'index' ? 'citizen' : preg_replace('#/index$#', '', $path);
    $query = request()->getQueryString();
    return redirect(app_href($path) . ($query ? '?' . $query : ''), 301);
})->where('path', '[A-Za-z0-9/_-]+');
