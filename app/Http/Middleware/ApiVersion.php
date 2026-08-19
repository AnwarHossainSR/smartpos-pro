<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiVersion
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-API-Version', '1.0');
        $response->headers->set('X-API-Version-Date', date('Y-m-d'));

        return $response;
    }
}
