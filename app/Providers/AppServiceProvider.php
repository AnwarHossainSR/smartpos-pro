<?php

namespace App\Providers;

use App\Models\Business;
use App\Models\PersonalAccessToken;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxProfile;
use App\Observers\BusinessObserver;
use App\Observers\ProductObserver;
use App\Observers\ProductVariantObserver;
use App\Observers\TaxProfileObserver;
use App\Services\Audit\AuditLogService;
use App\Support\Telemetry\Telemetry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant context per request lifecycle.
        $this->app->singleton(TenantContext::class);

        $this->app->singleton(Telemetry::class, fn () => new Telemetry(
            config('posthog.key'),
            config('posthog.host'),
            (bool) config('posthog.enabled'),
        ));

        // Scoped, not transient: AuditLogService memoises the current impersonation
        // for the life of the request, and a fresh instance per injection would look
        // it up again on every audit write.
        $this->app->scoped(AuditLogService::class);

        $this->app->register(WebhookServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Platform super admins bypass every ability. Doing it as a Gate::before hook
        // (spatie's recommended pattern) rather than as a role row means policies,
        // `$user->can()` and spatie's own gate check all agree with
        // `User::hasPermission()`, and no super admin ever needs a tenant-scoped role.
        //
        // Returning null — not false — for everyone else is load bearing: a `before`
        // callback that returns false short circuits the gate and would deny ordinary
        // users everything, tenant scoping included.
        Gate::before(fn ($user) => ($user->is_super_admin ?? false) ? true : null);

        Product::observe(ProductObserver::class);
        ProductVariant::observe(ProductVariantObserver::class);
        TaxProfile::observe(TaxProfileObserver::class);
        Business::observe(BusinessObserver::class);

        // Turn the silent failure modes into loud ones outside production: lazy loading
        // (the N+1 that only shows up under real data volume), assigning an attribute
        // that isn't fillable, and reading one the query never selected. Left off in
        // production so a missed eager-load degrades performance rather than 500ing.
        Model::shouldBeStrict(! $this->app->isProduction());

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

        // Supervisor PIN entry and PIN management. Keyed per caller so one terminal
        // hammering the keypad cannot lock out the shop, and set well below what a
        // brute force needs — the service adds its own 5-miss lockout on top.
        RateLimiter::for('approval-pin', function (Request $request) {
            return Limit::perMinute(10)->by('approval-pin:'.($request->user()?->id ?: $request->ip()));
        });

        // The reset-password flow is entirely frontend-driven (the SPA collects the new
        // password and posts it back), so the emailed link points at the frontend rather
        // than a Laravel view.
        ResetPassword::createUrlUsing(fn ($notifiable, string $token) => sprintf(
            '%s/reset-password?token=%s&email=%s',
            rtrim((string) config('app.frontend_url'), '/'),
            $token,
            urlencode($notifiable->getEmailForPasswordReset()),
        ));
    }
}
