<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Business;
use App\Services\Media\MediaService;
use App\Services\Media\SvgSanitizer;
use App\Support\Media\BusinessMediaSlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    /* ---------------------------------------------------------------- business */

    public function test_every_declared_slot_uploads_and_persists_to_its_own_column(): void
    {
        Storage::fake('public');

        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        foreach (BusinessMediaSlots::ALL as $slot => $config) {
            $this->postJson("/api/v1/settings/business/media/{$slot}", [
                'file' => UploadedFile::fake()->image("{$slot}.png", 600, 400)->size(100),
            ])->assertOk()->assertJsonPath('data.slot', $slot);

            $business = Business::findOrFail($owner->business_id);

            $this->assertNotNull(
                $business->{$config['column']},
                "Slot [{$slot}] did not write to [{$config['column']}]."
            );
        }
    }

    public function test_an_unknown_slot_is_rejected_by_the_router(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->createTenant());

        $this->postJson('/api/v1/settings/business/media/banner', [
            'file' => UploadedFile::fake()->image('x.png', 100, 100)->size(10),
        ])->assertNotFound();
    }

    public function test_business_settings_expose_a_url_for_each_slot(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->createTenant());

        $this->postJson('/api/v1/settings/business/media/logo', [
            'file' => UploadedFile::fake()->image('logo.png', 600, 600)->size(100),
        ])->assertOk();

        $response = $this->getJson('/api/v1/settings/business')->assertOk();

        $this->assertNotNull($response->json('data.media.logo.url'));
        $this->assertNotNull($response->json('data.media.logo.urls.sm'));
        // An empty slot reports null rather than being absent, so the client can
        // render a placeholder without special-casing missing keys.
        $this->assertNull($response->json('data.media.cover.url'));
    }

    public function test_deleting_a_slot_clears_the_column_and_the_objects(): void
    {
        Storage::fake('public');

        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/settings/business/media/logo', [
            'file' => UploadedFile::fake()->image('logo.png', 600, 600)->size(100),
        ])->assertOk();

        $path = Business::findOrFail($owner->business_id)->logo_path;

        $this->deleteJson('/api/v1/settings/business/media/logo')
            ->assertOk()
            ->assertJsonPath('data.path', null);

        $this->assertNull(Business::findOrFail($owner->business_id)->logo_path);
        Storage::disk('public')->assertMissing("{$path}/original.webp");
    }

    public function test_uploading_business_media_requires_the_settings_permission(): void
    {
        Storage::fake('public');

        $owner = $this->createTenant();
        $cashier = $this->userWithRole($owner, 'cashier');

        Sanctum::actingAs($cashier);

        $this->postJson('/api/v1/settings/business/media/logo', [
            'file' => UploadedFile::fake()->image('logo.png', 200, 200)->size(50),
        ])->assertForbidden();
    }

    /* ------------------------------------------------------------------ avatar */

    /**
     * Deliberately ungated: changing your own photo is not a tenant-admin
     * capability, so a cashier must be able to do it.
     */
    public function test_any_authenticated_user_can_set_their_own_avatar(): void
    {
        Storage::fake('public');

        $owner = $this->createTenant();
        $cashier = $this->userWithRole($owner, 'cashier');

        Sanctum::actingAs($cashier);

        $this->postJson('/api/v1/me/avatar', [
            'file' => UploadedFile::fake()->image('me.jpg', 800, 800)->size(200),
        ])->assertOk();

        $this->assertNotNull($cashier->refresh()->avatar_path);
        $this->assertNotNull($this->getJson('/api/v1/auth/me')->json('data.avatar_url'));
    }

    public function test_deleting_an_avatar_clears_it(): void
    {
        Storage::fake('public');

        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/me/avatar', [
            'file' => UploadedFile::fake()->image('me.jpg', 400, 400)->size(50),
        ])->assertOk();

        $this->deleteJson('/api/v1/me/avatar')->assertOk();

        $this->assertNull($owner->refresh()->avatar_path);
    }

    /* ------------------------------------------------------------------- brand */

    public function test_brand_logo_uploads_and_is_exposed_on_the_resource(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->createTenant());

        $brand = Brand::create(['name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);

        $this->postJson("/api/v1/brands/{$brand->id}/logo", [
            'file' => UploadedFile::fake()->image('acme.png', 400, 400)->size(50),
        ])->assertOk();

        $listed = collect($this->getJson('/api/v1/brands')->json('data'))
            ->firstWhere('id', $brand->id);

        $this->assertNotNull($listed['logo_url']);
    }

    /* ------------------------------------------------------ collection catalogue */

    public function test_the_collection_catalogue_matches_the_server_side_limits(): void
    {
        Sanctum::actingAs($this->createTenant());

        $collections = collect($this->getJson('/api/v1/media/collections')->assertOk()->json('data'));

        // The widget reads its client-side limits from here, so a drift between the
        // two would let the browser accept a file the server then rejects.
        $this->assertSame(
            array_keys(config('media.collections')),
            $collections->pluck('key')->all()
        );

        $product = $collections->firstWhere('key', 'product.image');
        $this->assertSame(config('media.collections')['product.image']['max_kb'], $product['max_kb']);
        $this->assertStringContainsString('image/webp', $product['accept']);
    }

    /* --------------------------------------------------------------- isolation */

    public function test_media_keys_are_prefixed_per_tenant(): void
    {
        Storage::fake('public');

        $a = $this->createTenant('a@example.test');
        Sanctum::actingAs($a);
        $this->postJson('/api/v1/settings/business/media/logo', [
            'file' => UploadedFile::fake()->image('a.png', 200, 200)->size(20),
        ])->assertOk();

        $b = $this->createTenant('b@example.test');
        Sanctum::actingAs($b);
        $this->postJson('/api/v1/settings/business/media/logo', [
            'file' => UploadedFile::fake()->image('b.png', 200, 200)->size(20),
        ])->assertOk();

        $pathA = Business::withoutTenancy()->findOrFail($a->business_id)->logo_path;
        $pathB = Business::withoutTenancy()->findOrFail($b->business_id)->logo_path;

        $this->assertStringStartsWith("media/{$a->tenant_id}/", $pathA);
        $this->assertStringStartsWith("media/{$b->tenant_id}/", $pathB);
        $this->assertNotSame($pathA, $pathB);
    }

    /* ----------------------------------------------------------- svg sanitiser */

    public function test_an_uploaded_svg_is_stripped_of_anything_executable(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->createTenant());

        $hostile = <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)">
          <script>alert(document.cookie)</script>
          <a xlink:href="javascript:alert(1)"><rect width="10" height="10" fill="#f00"/></a>
          <image href="https://evil.example/track.png"/>
          <circle cx="5" cy="5" r="4" fill="#0f0"/>
        </svg>
        SVG;

        $this->postJson('/api/v1/settings/business/media/logo', [
            'file' => UploadedFile::fake()->createWithContent('logo.svg', $hostile),
        ])->assertOk();

        $path = Business::first()->logo_path;
        $stored = Storage::disk('public')->get($path);

        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);
        $this->assertStringNotContainsString('evil.example', $stored);
        // The actual artwork survives — this is a sanitiser, not a rejection.
        $this->assertStringContainsString('<circle', $stored);
    }

    public function test_the_sanitiser_drops_entity_declarations(): void
    {
        $xxe = '<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            .'<svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>';

        $clean = SvgSanitizer::clean($xxe);

        $this->assertStringNotContainsString('ENTITY', $clean);
        $this->assertStringNotContainsString('file:///', $clean);
    }

    public function test_the_sanitiser_survives_junk_input(): void
    {
        $clean = SvgSanitizer::clean('not xml at all');

        $this->assertStringContainsString('<svg', $clean);
    }

    /* --------------------------------------------------------- url resolution */

    public function test_a_missing_path_resolves_to_a_null_url(): void
    {
        $media = app(MediaService::class);

        $this->assertNull($media->url(null));
        $this->assertNull($media->urls(null, 'product.image'));
    }

    /**
     * Uploads made before this service existed are stored as a single file with an
     * extension rather than a variant directory, and must keep resolving.
     */
    public function test_a_legacy_single_file_path_still_resolves(): void
    {
        Storage::fake('public');

        $url = app(MediaService::class)->url('products/tenant-1/old-image.jpg');

        $this->assertNotNull($url);
        $this->assertStringContainsString('old-image.jpg', $url);
    }
}
