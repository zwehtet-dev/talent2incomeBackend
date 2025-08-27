<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register custom middleware aliases
        $middleware->alias([
            'auth.custom' => \App\Http\Middleware\AuthenticationMiddleware::class,
            'rate.limit' => \App\Http\Middleware\RateLimitMiddleware::class,
            'abilities' => \App\Http\Middleware\CheckTokenAbilities::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'track.activity' => \App\Http\Middleware\TrackUserActivity::class,
            'api.version' => \App\Http\Middleware\ApiVersionMiddleware::class,
            'cache.response' => \App\Http\Middleware\CacheResponseMiddleware::class,
            'security.headers' => \App\Http\Middleware\SecurityHeadersMiddleware::class,
            'input.sanitization' => \App\Http\Middleware\InputSanitizationMiddleware::class,
            'csrf.protection' => \App\Http\Middleware\CsrfProtectionMiddleware::class,
            'ip.filtering' => \App\Http\Middleware\IpFilteringMiddleware::class,
            'audit' => \App\Http\Middleware\AuditMiddleware::class,
        ]);

        // Global middleware for all requests
        $middleware->append([
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
            \App\Http\Middleware\InputSanitizationMiddleware::class,
        ]);

        // Web middleware group
        $middleware->group('web', [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\CsrfProtectionMiddleware::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Apply security and rate limiting to API routes
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'track.activity',
            'ip.filtering',
            'audit',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Enterprise-grade exception handling
        $exceptions->reportable(function (Throwable $e) {
            // Log security-related exceptions to dedicated channel
            if ($e instanceof \Illuminate\Auth\AuthenticationException ||
                $e instanceof \Illuminate\Auth\Access\AuthorizationException ||
                $e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException) {
                \Log::channel('security')->error('Security Exception', [
                    'exception' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'user_id' => auth()->id(),
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'url' => request()->fullUrl(),
                ]);
            }

            // Log payment-related exceptions to dedicated channel
            if (str_contains($e->getMessage(), 'payment') ||
                str_contains($e->getFile(), 'Payment')) {
                \Log::channel('payments')->error('Payment Exception', [
                    'exception' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'user_id' => auth()->id(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });

        // Custom API error responses
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*')) {
                $status = 500;
                $message = 'Internal Server Error';

                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    $status = 422;
                    $message = 'Validation failed';

                    return response()->json([
                        'message' => $message,
                        'errors' => $e->errors(),
                        'status_code' => $status,
                    ], $status);
                }

                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    $status = 401;
                    $message = 'Unauthenticated';
                }

                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    $status = 403;
                    $message = 'Forbidden';
                }

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    $status = 404;
                    $message = 'Not Found';
                }

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException) {
                    $status = 429;
                    $message = 'Too Many Requests';
                }

                return response()->json([
                    'message' => $message,
                    'status_code' => $status,
                    'error_id' => \Str::uuid()->toString(),
                ], $status);
            }
        });
    })->create();
