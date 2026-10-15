<?php

use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\EnsureDelalaIsApproved;
use App\Http\Middleware\ThrottleOtp;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register named middleware aliases
        $middleware->alias([
            'delala.approved' => EnsureDelalaIsApproved::class,
            'otp.throttle' => ThrottleOtp::class,
        ]);

        // Append X-Request-ID to all API requests (NFR-OBS-1)
        $middleware->appendToGroup('api', AttachRequestId::class);

        // Trust proxies for correct IP detection
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always return JSON for API routes
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Standardise 401 authentication error
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ], 401);
            }
        });
    })->create();
