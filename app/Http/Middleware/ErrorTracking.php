<?php

namespace App\Http\Middleware;

use App\Support\Telemetry\Telemetry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reports 5xx API responses to PostHog. No-ops when telemetry is disabled.
 */
class ErrorTracking
{
    public function __construct(private readonly Telemetry $telemetry) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() >= 500) {
            $this->telemetry->capture('api_error', [
                'endpoint' => $request->method().' '.$request->getPathInfo(),
                'status' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}
