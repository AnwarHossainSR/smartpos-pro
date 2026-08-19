<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxGroup;
use App\Models\TaxRate;
use App\Models\TaxZone;
use App\Models\Unit;
use App\Services\Auth\TenantProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlans();

        $provisioner = app(TenantProvisioner::class);
        $owner = $provisioner->register([
            'name' => 'Admin User',
            'email' => 'admin@demostore.com',
            'password' => bcrypt('password123'),
            'business_name' => 'Demo Store BD',
            'business_type' => 'retail',
            'currency' => 'BDT',
        ]);

        $tenantId = $owner->tenant_id;
        app(TenantContext::class)->set($tenantId);

        $this->command?->info("Tenant: Demo Store BD | Login: admin@demostore.com / password123");

        $this->seedTax($tenantId);
        $this->seedCatalog($tenantId);

        $this->command?->info('Done. Plans + tax + catalog seeded.');
    }

    private function seedPlans(): void
    {
        $plans = [
            ['name' => 'Free', 'slug' => 'free', 'price' => 0, 'interval' => 'month',
                'limits' => ['branches' => 1, 'warehouses' => 1, 'users' => 2, 'products' => 50, 'monthly_sales' => 200],
                'features' => [
                    ['key' => 'products', 'label' => 'Product limit', 'value' => '50', 'included' => true],
                    ['key' => 'pos', 'label' => 'POS Service', 'value' => '', 'included' => true],
                    ['key' => 'reports', 'label' => 'Report Service', 'value' => 'Basic', 'included' => true],
                ], 'is_active' => true],
            ['name' => 'Starter', 'slug' => 'starter', 'price' => 499, 'interval' => 'month',
                'limits' => ['branches' => 1, 'warehouses' => 1, 'users' => 5, 'products' => 500, 'monthly_sales' => 1000],
                'features' => [
                    ['key' => 'products', 'label' => 'Product limit', 'value' => '500', 'included' => true],
                    ['key' => 'pos', 'label' => 'POS Service', 'value' => '', 'included' => true],
                    ['key' => 'coupons', 'label' => 'Coupon Service', 'value' => '', 'included' => true],
                    ['key' => 'reports', 'label' => 'Report Service', 'value' => 'Advanced', 'included' => true],
                ], 'is_active' => true],
            ['name' => 'Professional', 'slug' => 'professional', 'price' => 999, 'interval' => 'month',
                'limits' => ['branches' => 3, 'warehouses' => 3, 'users' => 20, 'products' => 5000, 'monthly_sales' => 10000],
                'features' => [
                    ['key' => 'products', 'label' => 'Product limit', 'value' => '5,000', 'included' => true],
                    ['key' => 'pos', 'label' => 'POS Service', 'value' => '', 'included' => true],
                    ['key' => 'coupons', 'label' => 'Coupon Service', 'value' => '', 'included' => true],
                    ['key' => 'reports', 'label' => 'Report Service', 'value' => 'Full + Analytics', 'included' => true],
                ], 'is_active' => true],
        ];

        foreach ($plans as $plan) {
            \App\Models\Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }

    private function seedTax(string $tenantId): void
    {
        $vat15 = TaxRate::updateOrCreate(['tenant_id' => $tenantId, 'name' => 'VAT 15%'],
            ['code' => 'VAT15', 'rate' => 15, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]);
        $sd5 = TaxRate::updateOrCreate(['tenant_id' => $tenantId, 'name' => 'SD 5%'],
            ['code' => 'SD5', 'rate' => 5, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]);
        $sd10 = TaxRate::updateOrCreate(['tenant_id' => $tenantId, 'name' => 'SD 10%'],
            ['code' => 'SD10', 'rate' => 10, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]);
        $ait5 = TaxRate::updateOrCreate(['tenant_id' => $tenantId, 'name' => 'AIT 5%'],
            ['code' => 'AIT5', 'rate' => 5, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]);
        $zero = TaxRate::updateOrCreate(['tenant_id' => $tenantId, 'name' => 'Zero Rated'],
            ['code' => 'ZERO', 'rate' => 0, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]);

        $std = TaxGroup::updateOrCreate(['tenant_id' => $tenantId, 'name' => 'Standard VAT 15%'],
            ['code' => 'STD15', 'is_default' => false, 'is_active' => true]);
        DB::table('tax_group_rate')->updateOrInsert(
            ['tax_group_id' => $std->id, 'tax_rate_id' => $vat15->id],
            ['tenant_id' => $tenantId, 'priority' => 1]
        );

        $sd5g = TaxGroup::updateOrCreate(['tenant_id' => $tenantId, 'name' => 'SD 5%'],
            ['code' => 'SD5G', 'is_default' => false, 'is_active' => true]);
        DB::table('tax_group_rate')->updateOrInsert(
            ['tax_group_id' => $sd5g->id, 'tax_rate_id' => $sd5->id],
            ['tenant_id' => $tenantId, 'priority' => 1]
        );

        $sd10g = TaxGroup::updateOrCreate(['tenant_id' => $tenantId, 'name' => 'SD 10%'],
            ['code' => 'SD10G', 'is_default' => false, 'is_active' => true]);
        DB::table('tax_group_rate')->updateOrInsert(
            ['tax_group_id' => $sd10g->id, 'tax_rate_id' => $sd10->id],
            ['tenant_id' => $tenantId, 'priority' => 1]
        );

        $aitg = TaxGroup::updateOrCreate(['tenant_id' => $tenantId, 'name' => 'AIT 5%'],
            ['code' => 'AIT5G', 'is_default' => false, 'is_active' => true]);
        DB::table('tax_group_rate')->updateOrInsert(
            ['tax_group_id' => $aitg->id, 'tax_rate_id' => $ait5->id],
            ['tenant_id' => $tenantId, 'priority' => 1]
        );

        $zerog = TaxGroup::updateOrCreate(['tenant_id' => $tenantId, 'name' => 'Zero Rated'],
            ['code' => 'ZERO', 'is_default' => true, 'is_active' => true]);
        DB::table('tax_group_rate')->updateOrInsert(
            ['tax_group_id' => $zerog->id, 'tax_rate_id' => $zero->id],
            ['tenant_id' => $tenantId, 'priority' => 1]
        );

        $zones = ['Dhaka', 'Chittagong', 'Rajshahi', 'Khulna', 'Barisal', 'Rangpur', 'Mymensingh', 'Sylhet'];
        foreach ($zones as $i => $zone) {
            TaxZone::updateOrCreate(['tenant_id' => $tenantId, 'name' => "$zone Division"],
                ['country_code' => 'BD', 'state' => $zone, 'tax_group_id' => $std->id,
                 'is_default' => $zone === 'Dhaka', 'priority' => 10 - $i, 'is_active' => true]);
        }
    }

    private function seedCatalog(string $tenantId): void
    {
        $cats = ['Electronics', 'Groceries', 'Clothing', 'Stationery'];
        $categories = [];
        foreach ($cats as $name) {
            $categories[$name] = Category::updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $name],
                ['slug' => Str::slug($name), 'is_active' => true]
            );
        }

        $brands = ['Samsung', 'Unilever', 'Nokia', 'Local Brand'];
        $brandModels = [];
        foreach ($brands as $name) {
            $brandModels[$name] = Brand::updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $name],
                ['slug' => Str::slug($name), 'is_active' => true]
            );
        }

        $unitNames = ['piece', 'kg', 'box', 'pair', 'liter'];
        $units = [];
        foreach ($unitNames as $name) {
            $units[$name] = Unit::updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $name],
                ['short_name' => $name]
            );
        }

        $customers = [
            ['name' => 'Walk-in Customer', 'phone' => '01700-000001'],
            ['name' => 'Rahim Uddin', 'phone' => '01712-345678', 'email' => 'rahim@test.com', 'billing_address' => '456 Elephant Road, Dhaka'],
            ['name' => 'Karim Store', 'phone' => '01812-345679', 'email' => 'karim@store.com', 'billing_address' => '78 Agrabad, Chittagong'],
            ['name' => 'Fatima Begum', 'phone' => '01912-345680', 'email' => 'fatima@test.com', 'billing_address' => '321 Mirpur, Dhaka'],
        ];
        foreach ($customers as $c) {
            Customer::updateOrCreate(
                ['tenant_id' => $tenantId, 'phone' => $c['phone']],
                ['name' => $c['name'], 'email' => $c['email'] ?? null, 'billing_address' => $c['billing_address'] ?? null, 'is_active' => true]
            );
        }

        $suppliers = [
            ['name' => 'Tech Distributors BD', 'phone' => '01711-111111', 'email' => 'sales@techdist.com', 'address' => '12 Electronic Market, Dhaka'],
            ['name' => 'Fresh Foods Ltd', 'phone' => '01811-222222', 'email' => 'info@freshfoods.com', 'address' => '45 Farmgate, Dhaka'],
            ['name' => 'Cloth House Wholesale', 'phone' => '01911-333333', 'email' => 'order@clothhouse.com', 'address' => '78 Zindabazar, Sylhet'],
        ];
        foreach ($suppliers as $s) {
            Supplier::updateOrCreate(
                ['tenant_id' => $tenantId, 'phone' => $s['phone']],
                ['name' => $s['name'], 'email' => $s['email'], 'address' => $s['address'], 'is_active' => true]
            );
        }

        $products = [
            ['name' => 'Samsung Galaxy A15', 'sku' => 'SAM-A15', 'cat' => 'Electronics', 'brand' => 'Samsung', 'unit' => 'piece', 'cost' => 12000, 'price' => 15999, 'min' => 10],
            ['name' => 'Nokia 105', 'sku' => 'NK-105', 'cat' => 'Electronics', 'brand' => 'Nokia', 'unit' => 'piece', 'cost' => 1500, 'price' => 2200, 'min' => 5],
            ['name' => 'Rice 5kg Bag', 'sku' => 'RCE-5KG', 'cat' => 'Groceries', 'brand' => 'Local Brand', 'unit' => 'box', 'cost' => 350, 'price' => 450, 'min' => 20],
            ['name' => 'Sunlight Soap', 'sku' => 'SUN-01', 'cat' => 'Groceries', 'brand' => 'Unilever', 'unit' => 'piece', 'cost' => 35, 'price' => 50, 'min' => 30],
            ['name' => 'Pencil 10-Pack', 'sku' => 'PEN-10', 'cat' => 'Stationery', 'brand' => 'Local Brand', 'unit' => 'box', 'cost' => 40, 'price' => 60, 'min' => 15],
            ['name' => 'T-Shirt Basic', 'sku' => 'TSH-01', 'cat' => 'Clothing', 'brand' => 'Local Brand', 'unit' => 'piece', 'cost' => 180, 'price' => 350, 'min' => 10],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(
                ['tenant_id' => $tenantId, 'sku' => $p['sku']],
                [
                    'name' => $p['name'],
                    'slug' => Str::slug($p['name']),
                    'category_id' => $categories[$p['cat']]->id,
                    'brand_id' => $brandModels[$p['brand']]->id,
                    'unit_id' => $units[$p['unit']]->id,
                    'purchase_price' => $p['cost'],
                    'selling_price' => $p['price'],
                    'min_stock_level' => $p['min'],
                    'track_inventory' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}
