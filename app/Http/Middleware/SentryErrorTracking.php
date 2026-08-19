<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\Severity;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reports 5xx API responses to Sentry. No-ops when no DSN is configured.
 */
class SentryErrorTracking
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() >= 500) {
            $endpoint = $request->method().' '.$request->getPathInfo();

            \Sentry\captureMessage(
                "API error: {$endpoint} returned {$response->getStatusCode()}",
                Severity::error()
            );
        }

        return $response;
    }
}
