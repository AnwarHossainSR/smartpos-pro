<?php

namespace App\Services\Platform;

use App\Models\PlatformSetting;

/**
 * The single answer to "is module X on for tenant Y?".
 *
 * There used to be two, and they disagreed. `ModuleController` skipped anything
 * switched off globally *before* consulting the tenant override, so enabling a
 * globally-off module for one tenant — the beta-rollout case the overrides exist
 * for — was impossible; `SettingController::isModuleEnabled()` checked the override
 * first, as intended. The rule is the one below, in one place, used by both the
 * `/modules` endpoint and the middleware that enforces it.
 */
class ModuleRegistry
{
    private const CACHE_KEY = 'platform:modules';

    private const CACHE_TTL_SECONDS = 300;

    /**
     * A tenant override always wins, in both directions — it can switch a module on
     * that is off by default, and off that is on by default.
     */
    public function enabled(string $module, ?string $tenantId = null): bool
    {
        $settings = $this->settings();

        if ($tenantId !== null && isset($settings['overrides'][$tenantId][$module])) {
            return (bool) $settings['overrides'][$tenantId][$module];
        }

        return (bool) ($settings['modules'][$module] ?? false);
    }

    /**
     * Every module and its effective state for one tenant — what the SPA builds its
     * navigation from.
     *
     * @return array<string, bool>
     */
    public function forTenant(?string $tenantId): array
    {
        $settings = $this->settings();
        $resolved = [];

        foreach (array_keys($settings['modules']) as $module) {
            $resolved[$module] = $this->enabled($module, $tenantId);
        }

        return $resolved;
    }

    public function flush(): void
    {
        cache()->forget(self::CACHE_KEY);
    }

    /**
     * Cached: this is read on every `/modules` call and on every request that passes
     * through the module middleware, and it used to be two uncached queries each time.
     *
     * @return array{modules: array<string, bool>, overrides: array<string, array<string, bool>>}
     */
    private function settings(): array
    {
        return cache()->remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => [
            'modules' => (array) PlatformSetting::get('modules', []),
            'overrides' => (array) PlatformSetting::get('tenant_overrides', []),
        ]);
    }
}
