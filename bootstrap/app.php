<?php

use App\Http\Middleware\RequireStaff;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StaffIdleTimeout;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Hosting platforms sit behind a reverse proxy; trust its
        // X-Forwarded-* headers so HTTPS and the host are detected correctly.
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            StaffIdleTimeout::class,
            SecurityHeaders::class,
        ]);
        // Passwords are compared exactly as typed.
        $middleware->trimStrings(except: ['password', 'current_password', 'new_password', 'confirm_password']);
        $middleware->alias([
            'staff' => RequireStaff::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api/*');

        // Expired/missing CSRF token (Laravel reports it as HTTP 419): same JSON
        // shape as every other API error; pages go back with a flash message.
        $csrfExpired = function (\Throwable $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return json_response(false, null, 'Invalid or expired security token. Please refresh and try again.', 403);
            }
            flash_set('warning', 'Your session has expired. Please try again.');
            return redirect()->back();
        };
        $exceptions->render(fn (TokenMismatchException $e, Request $request) => $csrfExpired($e, $request));
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($csrfExpired) {
            return $e->getStatusCode() === 419 ? $csrfExpired($e, $request) : null;
        });

        // Any other uncaught error on a JSON endpoint: same envelope, no details leaked.
        $exceptions->render(function (\Throwable $e, Request $request) use ($isApi) {
            if (!$isApi($request) || $e instanceof HttpResponseException) {
                return null;
            }
            if ($e instanceof HttpExceptionInterface) {
                $code = $e->getStatusCode();
                $message = match ($code) {
                    405     => 'Invalid request method.',
                    404     => 'Not found.',
                    default => 'Unable to process request.',
                };
                return json_response(false, null, $message, $code);
            }
            if (config('app.debug')) {
                return null;
            }
            return json_response(false, null, 'Service temporarily unavailable. Please try again later.', 500);
        });
    })->create();
