<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_dry_run_returns_validation_errors_with_row_numbers(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $csv = <<<'CSV'
name,sku,barcode,category,brand,unit,purchase_price,selling_price,tax_rate,min_stock_level,is_active
,SKU-001,1234567890123,Beverages,BrandA,piece,5.00,8.00,10,5,1
Valid Product,SKU-002,1234567890124,Snacks,BrandB,pack,-1.00,10.00,5,10,1
CSV;

        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

        $response = $this->post('/api/v1/products/import', [
            'file' => $file,
            'dry_run' => true,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'rows_valid',
                    'rows_invalid',
                    'errors' => [
                        '*' => ['row', 'message'],
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['rows_invalid']);
        $this->assertNotEmpty($data['errors']);

        $rowNumbers = array_column($data['errors'], 'row');
        $this->assertContains(2, $rowNumbers);
        $this->assertContains(3, $rowNumbers);
    }

    public function test_real_import_inserts_products_and_auto_creates_category(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $csv = <<<'CSV'
name,sku,barcode,category,brand,unit,purchase_price,selling_price,tax_rate,min_stock_level,is_active
Cola 500ml,SKU-COLA-001,8901030123456,Beverages,BrandX,piece,5.00,8.00,10,5,1
Chips Pack,SKU-CHIPS-001,8901030123457,Snacks,BrandY,pack,3.00,5.00,10,10,1
CSV;

        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

        $response = $this->post('/api/v1/products/import', [
            'file' => $file,
            'dry_run' => false,
        ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertOk()
            ->assertJsonPath('message', 'Products imported successfully');

        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('categories', 2);
        $this->assertDatabaseCount('brands', 2);
        $this->assertDatabaseCount('units', 2);

        $cola = Product::where('sku', 'SKU-COLA-001')->first();
        $this->assertNotNull($cola);
        $this->assertEquals('Cola 500ml', $cola->name);
        $this->assertEquals(5.00, $cola->purchase_price);
        $this->assertEquals(8.00, $cola->selling_price);
        $this->assertTrue($cola->is_active);

        $category = Category::where('name', 'Beverages')->first();
        $this->assertNotNull($category);
        $this->assertEquals($category->id, $cola->category_id);

        $brand = $cola->brand;
        $this->assertEquals('BrandX', $brand->name);

        $unit = $cola->unit;
        $this->assertEquals('piece', $unit->name);
    }

    public function test_invalid_file_returns_422(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $file = UploadedFile::fake()->createWithContent('import.txt', 'not a csv');

        $response = $this->post('/api/v1/products/import', [
            'file' => $file,
            'dry_run' => false,
        ]);

        $response->assertStatus(422);
    }

    public function test_template_endpoint_returns_csv(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $response = $this->get('/api/v1/products/import-template');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8')
            ->assertHeader('Content-Disposition', 'attachment; filename=product_import_template.csv');

        $content = $response->getContent();
        $this->assertStringContainsString('name,sku,barcode,category,brand,unit,purchase_price,selling_price,tax_rate,min_stock_level,is_active', $content);
        $this->assertStringContainsString('Cola 500ml,SKU-COLA-001', $content);
    }

    public function test_import_requires_products_create_permission(): void
    {
        $owner = $this->createTenant();
        $owner->assignRole('cashier');
        $owner->save();
        Sanctum::actingAs($owner->fresh());

        $csv = <<<'CSV'
name,sku,barcode,category,brand,unit,purchase_price,selling_price,tax_rate,min_stock_level,is_active
Cola 500ml,SKU-COLA-001,8901030123456,Beverages,BrandX,piece,5.00,8.00,10,5,1
CSV;

        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

        $response = $this->post('/api/v1/products/import', [
            'file' => $file,
            'dry_run' => false,
        ]);

        $response->assertForbidden();
    }

    public function test_dry_run_does_not_persist_products(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $csv = <<<'CSV'
name,sku,barcode,category,brand,unit,purchase_price,selling_price,tax_rate,min_stock_level,is_active
Dry Run Product,SKU-DRY-001,1234567890125,Test Category,Test Brand,piece,10.00,15.00,5,5,1
CSV;

        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

        $this->post('/api/v1/products/import', [
            'file' => $file,
            'dry_run' => true,
        ]);

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('categories', 0);
    }
}
