<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Auth\TenantProvisioner;
use App\Services\Inventory\StockLedgerService;
use App\Services\Sales\SaleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Builds a realistic demo tenant: owner + staff, catalog, opening stock,
 * customers, suppliers and a week of sales history.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        /** @var TenantProvisioner $provisioner */
        $provisioner = app(TenantProvisioner::class);
        $ledger = app(StockLedgerService::class);
        $sales = app(SaleService::class);
        $context = app(TenantContext::class);

        // Owner + tenant + business + branch + warehouse + roles.
        $owner = $provisioner->register([
            'name' => 'Demo Owner',
            'email' => 'owner@smartpos.test',
            'password' => 'password123',
            'business_name' => 'SmartPOS Demo Store',
            'business_type' => 'supermarket',
            'currency' => 'BDT',
        ]);

        $context->set($owner->tenant_id);
        $branch = $owner->branch;
        $warehouse = Warehouse::where('business_id', $owner->business_id)->first();

        // Put the demo business on the Professional plan.
        if ($plan = Plan::where('slug', 'professional')->first()) {
            Subscription::create([
                'tenant_id' => $owner->tenant_id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_ends_at' => now()->addMonth(),
            ]);
        }

        // Staff users across the default roles.
        $roles = ['admin', 'branch_manager', 'cashier', 'accountant', 'viewer'];
        foreach ($roles as $i => $role) {
            $user = User::create([
                'tenant_id' => $owner->tenant_id,
                'business_id' => $owner->business_id,
                'branch_id' => $branch->id,
                'name' => Str::title($role).' User',
                'email' => "{$role}@smartpos.test",
                'password' => 'password123',
                'status' => 'active',
            ]);
            $user->assignRole($role);
        }

        // Catalog: brands, categories, units.
        $brands = collect(['Acme', 'Globex', 'Umbrella', 'Initech', 'Soylent'])
            ->map(fn ($name) => Brand::create(['name' => $name, 'slug' => Str::slug($name)]));

        $categories = collect(['Beverages', 'Snacks', 'Dairy', 'Bakery', 'Household', 'Personal Care'])
            ->map(fn ($name) => Category::create(['name' => $name, 'slug' => Str::slug($name)]));

        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pcs']);

        // 100 products with opening stock.
        $products = collect();
        for ($i = 1; $i <= 100; $i++) {
            $purchase = fake()->randomFloat(2, 1, 80);
            $product = Product::create([
                'category_id' => $categories->random()->id,
                'brand_id' => $brands->random()->id,
                'unit_id' => $unit->id,
                'name' => ucfirst(fake()->unique()->words(2, true)),
                'slug' => Str::slug(fake()->unique()->words(3, true)),
                'sku' => 'SKU-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'barcode' => (string) fake()->unique()->ean13(),
                'purchase_price' => $purchase,
                'selling_price' => round($purchase * fake()->randomFloat(2, 1.15, 1.8), 2),
                'tax_rate' => fake()->randomElement([0, 5, 10, 15]),
                'min_stock_level' => 10,
                'reorder_quantity' => 50,
            ]);
            $ledger->addOpeningStock($product->id, $warehouse->id, fake()->numberBetween(20, 200), $purchase);
            $products->push($product);
        }

        // Customers + suppliers.
        $customers = collect();
        for ($i = 0; $i < 20; $i++) {
            $customers->push(Customer::create([
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'phone' => fake()->numerify('01#########'),
                'credit_limit' => fake()->randomElement([0, 500, 1000]),
            ]));
        }
        for ($i = 0; $i < 10; $i++) {
            Supplier::create([
                'name' => fake()->name(),
                'company' => fake()->company(),
                'phone' => fake()->numerify('01#########'),
            ]);
        }

        // A week of sales history.
        for ($d = 6; $d >= 0; $d--) {
            $salesToday = fake()->numberBetween(3, 8);
            for ($s = 0; $s < $salesToday; $s++) {
                $lineCount = fake()->numberBetween(1, 4);
                $items = $products->random($lineCount)->map(fn ($p) => [
                    'product_id' => $p->id,
                    'quantity' => fake()->numberBetween(1, 3),
                ])->values()->all();

                $sale = $sales->create([
                    'branch_id' => $branch->id,
                    'warehouse_id' => $warehouse->id,
                    'customer_id' => fake()->boolean(60) ? $customers->random()->id : null,
                    'items' => $items,
                    'payments' => [], // filled below once total is known
                ], $owner);

                // Record a matching cash payment and backdate the sale.
                $sale->payments()->create(['method' => 'cash', 'amount' => $sale->total, 'paid_at' => now()->subDays($d)]);
                $sale->forceFill([
                    'paid_amount' => $sale->total,
                    'due_amount' => 0,
                    'payment_status' => 'paid',
                    'sold_at' => now()->subDays($d),
                    'created_at' => now()->subDays($d),
                ])->save();
            }
        }

        $this->command?->info('Demo tenant seeded. Login: owner@smartpos.test / password123');
    }
}
