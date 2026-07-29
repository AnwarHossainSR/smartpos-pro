<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant context per request lifecycle.
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Global per-token (or per-IP for unauthenticated requests) limit for the whole API.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Reports and PDF generation are expensive queries — a tighter ceiling.
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Login/register: per-account AND per-IP, so distributed credential stuffing against
        // one account is throttled even when it's spread across many source IPs.
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by('email:'.strtolower((string) $request->input('email'))),
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });
    }
}
