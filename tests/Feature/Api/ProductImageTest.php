<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function product(): Product
    {
        $this->postJson('/api/v1/products', [
            'name' => 'Cola 500ml',
            'purchase_price' => 5,
            'selling_price' => 8,
        ])->assertCreated();

        return Product::first();
    }

    public function test_upload_image_writes_every_configured_variant(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->createTenant());
        $product = $this->product();

        $response = $this->postJson("/api/v1/products/{$product->id}/image", [
            'file' => UploadedFile::fake()->image('product.jpg', 2000, 2000)->size(100),
        ]);

        $response->assertOk()->assertJsonStructure([
            'data' => ['path', 'url', 'urls' => ['original', 'md', 'sm']],
        ]);

        $product->refresh();
        $this->assertNotNull($product->image_path);

        // The stored path is the prefix; each variant is an object beneath it.
        foreach (['original', 'md', 'sm'] as $variant) {
            Storage::disk('public')->assertExists("{$product->image_path}/{$variant}.webp");
        }
    }

    public function test_uploads_are_scoped_to_the_owning_tenant(): void
    {
        Storage::fake('public');

        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        $product = $this->product();

        $this->postJson("/api/v1/products/{$product->id}/image", [
            'file' => UploadedFile::fake()->image('product.jpg', 200, 200)->size(100),
        ])->assertOk();

        // The tenant id is the first path segment, so one tenant's media can never
        // collide with another's and the whole set is removable on account closure.
        $this->assertStringStartsWith("media/{$owner->tenant_id}/product.image/", $product->refresh()->image_path);
    }

    /**
     * The re-encode is the security property, not a nicety: whatever the client
     * uploaded is discarded and only re-encoded pixels reach the bucket.
     */
    public function test_uploads_are_re_encoded_to_webp_and_bounded_by_the_configured_box(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->createTenant());
        $product = $this->product();

        $this->postJson("/api/v1/products/{$product->id}/image", [
            'file' => UploadedFile::fake()->image('huge.png', 4000, 3000)->size(500),
        ])->assertOk();

        $path = $product->refresh()->image_path;
        $bytes = Storage::disk('public')->get("{$path}/sm.webp");

        $this->assertSame('WEBP', substr($bytes, 8, 4));

        [$width, $height] = getimagesizefromstring($bytes);
        $this->assertLessThanOrEqual(160, $width);
        $this->assertLessThanOrEqual(160, $height);
        // 4000x3000 scaled into a 160 box keeps its 4:3 ratio rather than being squared off.
        $this->assertSame(160, $width);
        $this->assertSame(120, $height);
    }

    public function test_replacing_an_image_deletes_the_previous_one(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->createTenant());
        $product = $this->product();

        $this->postJson("/api/v1/products/{$product->id}/image", [
            'file' => UploadedFile::fake()->image('first.jpg', 200, 200)->size(100),
        ])->assertOk();

        $first = $product->refresh()->image_path;

        $this->postJson("/api/v1/products/{$product->id}/image", [
            'file' => UploadedFile::fake()->image('second.jpg', 200, 200)->size(100),
        ])->assertOk();

        $second = $product->refresh()->image_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing("{$first}/original.webp");
        Storage::disk('public')->assertExists("{$second}/original.webp");
    }

    public function test_oversized_file_is_rejected(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->createTenant());
        $product = $this->product();

        $this->postJson("/api/v1/products/{$product->id}/image", [
            'file' => UploadedFile::fake()->image('product.jpg', 200, 200)->size(5000),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    /** A file is judged on its decoded contents, never on the name it arrived with. */
    public function test_a_non_image_masquerading_as_one_is_rejected(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->createTenant());
        $product = $this->product();

        $this->postJson("/api/v1/products/{$product->id}/image", [
            'file' => UploadedFile::fake()->createWithContent('payload.png', '<?php echo "hi";'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_delete_removes_every_variant_and_nulls_the_column(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->createTenant());
        $product = $this->product();

        $this->postJson("/api/v1/products/{$product->id}/image", [
            'file' => UploadedFile::fake()->image('product.jpg', 200, 200)->size(100),
        ])->assertOk();

        $path = $product->refresh()->image_path;

        $this->deleteJson("/api/v1/products/{$product->id}/image")
            ->assertOk()
            ->assertJson(['data' => ['url' => null]]);

        $this->assertNull($product->refresh()->image_path);
        Storage::disk('public')->assertMissing("{$path}/original.webp");
        Storage::disk('public')->assertMissing("{$path}/sm.webp");
    }

    public function test_cross_tenant_product_upload_returns_404(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->createTenant('a@example.test'));
        $product = $this->product();

        Sanctum::actingAs($this->createTenant('b@example.test'));

        $this->postJson("/api/v1/products/{$product->id}/image", [
            'file' => UploadedFile::fake()->image('product.jpg', 200, 200)->size(100),
        ])->assertNotFound();
    }
}
