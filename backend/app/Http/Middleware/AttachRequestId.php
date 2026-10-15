<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attaches a unique X-Request-ID to every HTTP request for correlation tracing.
 * SRS NFR-OBS-1.
 */
class AttachRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID') ?? (string) Str::uuid();

        // Inject into request for downstream use
        $request->headers->set('X-Request-ID', $requestId);

        /** @var Response $response */
        $response = $next($request);

        // Echo back in response header
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
