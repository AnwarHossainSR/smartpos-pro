<?php

namespace Tests\Feature\Api;

use App\Support\Rbac\PermissionCatalog;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The catalogue is the only source permissions are seeded from, so a slug that is
 * guarded against but missing from it can never be attached to a role: the route
 * becomes owner-only forever, silently and with no error anywhere.
 *
 * These two tests close that gap from both sides — the route guards and the
 * in-code checks.
 */
class PermissionCatalogueCoverageTest extends TestCase
{
    /** @return string[] */
    private function catalogueSlugs(): array
    {
        return array_column(PermissionCatalog::all(), 'slug');
    }

    public function test_every_route_permission_guard_exists_in_the_catalogue(): void
    {
        $catalogue = $this->catalogueSlugs();
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'permission:')) {
                    continue;
                }

                $slug = substr($middleware, strlen('permission:'));

                if (! in_array($slug, $catalogue, true)) {
                    $missing[$slug] = $route->methods()[0].' '.$route->uri();
                }
            }
        }

        $this->assertSame([], $missing, sprintf(
            "Route guards reference permissions that PermissionCatalog does not define:\n  %s\n".
            'Add them to PermissionCatalog::MATRIX, or fix the slug on the route.',
            implode("\n  ", array_map(
                fn ($route, $slug) => "{$slug}  ({$route})",
                $missing,
                array_keys($missing),
            )),
        ));
    }

    public function test_every_permission_checked_in_code_exists_in_the_catalogue(): void
    {
        $catalogue = $this->catalogueSlugs();
        $missing = [];

        foreach ($this->phpFilesIn(app_path()) as $file) {
            $contents = file_get_contents($file);

            // `hasPermission('pos.approve')` and `can('reports.export')` — literal slugs
            // only; anything built at runtime is out of reach of a static check like this.
            preg_match_all(
                "/->(?:hasPermission|checkPermissionTo)\(\s*'([a-z0-9_.\-]+)'/",
                $contents,
                $matches,
            );

            foreach ($matches[1] as $slug) {
                if (! in_array($slug, $catalogue, true)) {
                    $missing[$slug] = str_replace(base_path().'/', '', $file);
                }
            }
        }

        $this->assertSame([], $missing, sprintf(
            "Code checks permissions that PermissionCatalog does not define:\n  %s",
            implode("\n  ", array_map(
                fn ($file, $slug) => "{$slug}  ({$file})",
                $missing,
                array_keys($missing),
            )),
        ));
    }

    /** @return iterable<string> */
    private function phpFilesIn(string $directory): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
