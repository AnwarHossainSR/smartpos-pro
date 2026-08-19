<?php

namespace App\Http;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SentryErrorTracking;
use App\Http\Middleware\SuperAdmin;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\SubstituteInput;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrors;
use Spatie\Permission\Middleware\RoleOrPermission;

class Kernel extends HttpKernel
{
    protected $middleware = [];

    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            StartSession::class,
            ShareErrors::class,
            VerifyCsrfToken::class,
            SubstituteInput::class,
        ],

        'api' => [
            'throttle:api',
            SubstituteBindings::class,
        ],
    ];

    protected $routeMiddleware = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'throttle' => ThrottleRequests::class,
        'throttle.request' => ThrottleRequests::class,
        'permission' => RoleOrPermission::class,
        'tenant' => ResolveTenant::class,
        'ensure.tenant' => EnsureTenant::class,
        'super_admin' => SuperAdmin::class,
    ];

    protected function initializeRouteMiddleware(): void
    {
        $this->routeMiddleware = array_merge($this->routeMiddleware, [
            'sentry-error-tracking' => SentryErrorTracking::class,
        ]);
    }
}
