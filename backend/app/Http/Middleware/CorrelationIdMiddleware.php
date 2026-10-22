<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdMiddleware
{
    /**
     * Handle an incoming request.
     * Enforces end-to-end distributed tracing via X-Request-ID (SRS NFR-OBS-1).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID');

        if (empty($requestId)) {
            $requestId = (string) Str::uuid();
            $request->headers->set('X-Request-ID', $requestId);
        }

        // Attach correlation ID to all structured logs in current request lifecycle
        Log::withContext([
            'request_id' => $requestId,
        ]);

        $response = $next($request);

        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
