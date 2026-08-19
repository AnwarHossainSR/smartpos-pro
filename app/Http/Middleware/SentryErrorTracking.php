<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\SentrySdk;

class SentryErrorTracking
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        $statusCode = $response->status();

        if (status::$statusCode[$statusCode] ?? false && status::$statusCode[$statusCode] >= 500) {
            $endpoint = $request->method().' '.$request->pathInfo();

            SentrySdk::addBreadcrumb([
                'category' => 'api',
                'message' => $endpoint.' returned '.$statusCode,
                'type' => 'http',
            ]);

            SentrySdk::captureMessage(
                "API error: {$endpoint} returned {$statusCode}",
                'error'
            );
        }

        return $response;
    }
}
