<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Supplier;
use App\Models\TaxGroup;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Services\Auth\TenantProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FullDemoSeeder extends TransactionalSeeder
{
    private string $tenantId;

    private string $businessId;

    private string $branchId;

    private string $warehouseId;

    private string $ownerId;

    private array $categoryMap = [
        'Electronics' => ['Samsung', 'Apple', 'Xiaomi', 'Sony', 'LG', 'Walton', 'Nokia'],
        'Groceries' => ['Pran', 'RFL', 'Aarong', 'Fresh', 'Organic'],
        'Clothing' => ['Aarong', 'Lifestyle', 'Yellow', 'Richman', 'Tex'],
        'Stationery' => ['Lions', 'Deli', 'Piano', 'Matador'],
        'Home & Kitchen' => ['RFL', 'Pran', 'KDAL', 'Singer', 'Walton'],
        'Beauty' => ['Loreal', 'Nivea', 'Ponds', 'Garnier'],
        'Sports' => ['Nike', 'Adidas', 'Puma', 'Nivia'],
        'Automotive' => ['Bosch', 'Shell'],
        'Jewelry' => ['Gold', 'Silver', 'Fashion'],
        'Baby' => ['Pampers', 'Johnson', 'Nestle'],
        'Pet' => ['Whiskas', 'Pedigree'],
        'Garden' => ['Seeds', 'Tools'],
    ];

    private array $storeNames = [
        'TechZone Electronics', 'FreshBasket Groceries', 'StyleHub Fashion', 'BookNook Stationery',
        'HomeEase Appliances', 'BeautyBliss Cosmetics', 'SportZone Athletics', 'PetParadise Supplies',
        'GreenLeaf Organic', 'CityPharmacy Medical', 'ToyLand Kids', 'AutoParts Pro',
        'JewelGleam Accessories', 'KitchenKing Utensils', 'GardenJoy Plants', 'MusicMania Instruments',
        'FabricWorld Textiles', 'TechFix Repairs', 'CoffeeHeaven Beverages', 'BakeStudio Bakery',
        'DigitalDen Computers', 'FreshCatch Seafood', 'TrendSetters Clothing', 'ArtSupply Studio',
        'HardwareHub Tools', 'BabyBliss Maternity', 'SportsFan Equipment', 'OrganicFarm Produce',
        'MobileMart Phones', 'FrameIt Optics', 'CleanPro Cleaning', 'PackRight Packaging',
    ];

    private array $customerNames = [
        'Walk-in Customer', 'Rahim Store', 'Karim Enterprise', 'Fatima Trading',
        'Haque & Sons', 'Nusrat Traders', 'Rana Corporation', 'Sumaiya Mart',
        'Bismillah Shops', 'Kabir Distributors', 'Fresh Corner', 'Tanvir Holdings',
        'Mega Outlet', 'Rashida Super', 'City Plaza', 'Rahman Brothers',
        'Akter Trading Co', 'Hasan General Store', 'Jahan Enterprises', 'Ali & Co',
    ];

    private array $supplierNames = [
        'Tech Distributors BD', 'Fresh Foods Ltd', 'Cloth House Wholesale', 'Walton Direct',
        'Pran Agro Industries', 'Aarong Supply Chain', 'RFL Trading', 'BRAC Distribution',
        'Nestle Bangladesh', 'Unilever BD', 'P&G Bangladesh', 'Reckitt Benckiser',
    ];

    protected function seed(): void
    {
        $this->createSuperAdmin();
        $this->seedPlans();
        $this->seedAllTenants();
    }

    private function createSuperAdmin(): void
    {
        User::unguard(true);
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@smartpos.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->forceFill(['is_super_admin' => true])->save();
        User::unguard(false);
    }

    private function seedPlans(): void
    {
        $plans = [
            ['name' => 'Free', 'slug' => 'free', 'price' => 0, 'interval' => 'month',
                'limits' => ['branches' => 1, 'warehouses' => 1, 'users' => 2, 'products' => 50, 'monthly_sales' => 200],
                'features' => [['key' => 'pos', 'label' => 'POS', 'value' => '', 'included' => true]], 'is_active' => true],
            ['name' => 'Starter', 'slug' => 'starter', 'price' => 499, 'interval' => 'month',
                'limits' => ['branches' => 1, 'warehouses' => 1, 'users' => 5, 'products' => 500, 'monthly_sales' => 1000],
                'features' => [['key' => 'pos', 'label' => 'POS', 'value' => '', 'included' => true]], 'is_active' => true],
            ['name' => 'Professional', 'slug' => 'professional', 'price' => 999, 'interval' => 'month',
                'limits' => ['branches' => 3, 'warehouses' => 3, 'users' => 20, 'products' => 5000, 'monthly_sales' => 10000],
                'features' => [['key' => 'pos', 'label' => 'POS', 'value' => '', 'included' => true]], 'is_active' => true],
            ['name' => 'Enterprise', 'slug' => 'enterprise', 'price' => 2999, 'interval' => 'month',
                'limits' => ['branches' => 10, 'warehouses' => 10, 'users' => 100, 'products' => 50000, 'monthly_sales' => 100000],
                'features' => [['key' => 'pos', 'label' => 'POS', 'value' => '', 'included' => true]], 'is_active' => true],
        ];
        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }

    private function seedAllTenants(): void
    {
        foreach ($this->storeNames as $storeName) {
            $this->seedTenant($storeName);
        }
    }

    private function seedTenant(string $storeName): void
    {
        $provisioner = app(TenantProvisioner::class);
        $email = Str::slug($storeName).'@demo.com';

        $owner = $provisioner->register([
            'name' => $storeName.' Owner',
            'email' => $email,
            'password' => bcrypt('password'),
            'business_name' => $storeName,
            'business_type' => 'retail',
            'currency' => 'BDT',
        ]);

        $this->tenantId = $owner->tenant_id;
        $this->businessId = $owner->business_id;
        $this->branchId = $owner->branch_id;
        $this->warehouseId = $owner->business->warehouses->first()?->id ?? '';
        $this->ownerId = $owner->id;

        app(TenantContext::class)->set($this->tenantId);

        $this->command?->info('Tenant: '.$storeName.' | Login: '.$email.' / password');

        $categories = $this->seedCatalog();
        $customers = $this->seedCustomers();
        $suppliers = $this->seedSuppliers();
        $products = $this->seedProducts($categories);
        $this->seedTax();
        $this->seedExpenseCategories();
        $this->seedCoupons();
        $this->seedSales($customers, $products);
        $this->seedPurchases($suppliers, $products);
        $this->seedExpenses();
    }

    private function seedCatalog(): array
    {
        $allCategoryNames = array_values(array_keys($this->categoryMap));
        $selectedCount = rand(3, 5);
        $selectedCategoryNames = array_rand($allCategoryNames, $selectedCount);
        if (!is_array($selectedCategoryNames)) {
            $selectedCategoryNames = [$selectedCategoryNames];
        }

        $categories = [];
        foreach ($selectedCategoryNames as $idx) {
            $catName = $allCategoryNames[$idx];
            $categories[$catName] = Category::updateOrCreate(
                ['tenant_id' => $this->tenantId, 'name' => $catName],
                ['slug' => Str::slug($catName), 'is_active' => true]
            );
        }

        foreach ($selectedCategoryNames as $idx) {
            $catName = $allCategoryNames[$idx];
            foreach ($this->categoryMap[$catName] as $brandName) {
                Brand::updateOrCreate(
                    ['tenant_id' => $this->tenantId, 'name' => $brandName],
                    ['slug' => Str::slug($brandName), 'is_active' => true]
                );
            }
        }

        $units = ['piece', 'kg', 'box', 'pair', 'liter', 'dozen', 'pack'];
        foreach ($units as $name) {
            Unit::updateOrCreate(
                ['tenant_id' => $this->tenantId, 'name' => $name],
                ['short_name' => $name]
            );
        }

        return $categories;
    }

    private function seedCustomers(): array
    {
        $selectedCount = rand(10, 15);
        $selectedKeys = array_rand($this->customerNames, $selectedCount);
        if (!is_array($selectedKeys)) {
            $selectedKeys = [$selectedKeys];
        }

        $customers = [];
        foreach ($selectedKeys as $key) {
            $name = $this->customerNames[$key];
            $phone = '017'.str_pad((string) $key, 8, '0', STR_PAD_LEFT).rand(1000, 9999);
            $customers[$phone] = Customer::updateOrCreate(
                ['tenant_id' => $this->tenantId, 'phone' => $phone],
                [
                    'name' => $name,
                    'email' => Str::slug($name).'@customer.com',
                    'billing_address' => rand(1, 999).' Main Road, Dhaka',
                    'credit_limit' => rand(10000, 200000),
                    'is_active' => true,
                ]
            );
        }

        return $customers;
    }

    private function seedSuppliers(): array
    {
        $selectedCount = rand(4, 6);
        $selectedKeys = array_rand($this->supplierNames, $selectedCount);
        if (!is_array($selectedKeys)) {
            $selectedKeys = [$selectedKeys];
        }

        $suppliers = [];
        foreach ($selectedKeys as $key) {
            $name = $this->supplierNames[$key];
            $phone = '018'.str_pad((string) $key, 8, '0', STR_PAD_LEFT).rand(1000, 9999);
            $suppliers[$phone] = Supplier::updateOrCreate(
                ['tenant_id' => $this->tenantId, 'phone' => $phone],
                [
                    'name' => $name,
                    'email' => Str::slug($name).'@supplier.com',
                    'address' => rand(1, 999).' Industrial Area, Dhaka',
                    'opening_balance' => rand(0, 100000),
                    'is_active' => true,
                ]
            );
        }

        return $suppliers;
    }

    private function seedProducts(array $categories): array
    {
        $brandNames = Brand::where('tenant_id', $this->tenantId)->pluck('id', 'name')->toArray();
        $unitNames = Unit::where('tenant_id', $this->tenantId)->pluck('id', 'name')->toArray();
        $units = array_keys($unitNames);
        $products = [];

        foreach ($categories as $catName => $category) {
            $catBrands = $this->categoryMap[$catName] ?? [];
            $unit = $catName === 'Groceries' ? 'kg' : ($catName === 'Clothing' ? 'piece' : 'piece');
            if (!in_array($unit, $units)) {
                $unit = $units[0];
            }

            foreach ($catBrands as $brandName) {
                $brandId = $brandNames[$brandName] ?? null;
                $unitId = $unitNames[$unit] ?? null;
                $productName = $brandName.' '.$catName.' Item '.rand(1, 100);
                $sku = Str::slug($brandName).'-'.Str::slug($catName).'-'.rand(1000, 9999);
                $cost = rand(10, 50000);
                $price = (int) ($cost * rand(12, 30) / 10);

                $products[$sku] = Product::updateOrCreate(
                    ['tenant_id' => $this->tenantId, 'sku' => $sku],
                    [
                        'name' => $productName,
                        'slug' => Str::slug($productName),
                        'category_id' => $category->id,
                        'brand_id' => $brandId,
                        'unit_id' => $unitId,
                        'purchase_price' => $cost,
                        'selling_price' => $price,
                        'min_stock_level' => rand(5, 50),
                        'track_inventory' => true,
                        'is_active' => true,
                    ]
                );
            }
        }

        return $products;
    }

    private function seedTax(): void
    {
        $t = $this->tenantId;

        $vat15 = TaxRate::updateOrCreate(['tenant_id' => $t, 'name' => 'VAT 15%'], ['code' => 'VAT15', 'rate' => 15, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]);
        $sd5 = TaxRate::updateOrCreate(['tenant_id' => $t, 'name' => 'SD 5%'], ['code' => 'SD5', 'rate' => 5, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]);
        $sd10 = TaxRate::updateOrCreate(['tenant_id' => $t, 'name' => 'SD 10%'], ['code' => 'SD10', 'rate' => 10, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]);
        $ait5 = TaxRate::updateOrCreate(['tenant_id' => $t, 'name' => 'AIT 5%'], ['code' => 'AIT5', 'rate' => 5, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]);
        $zero = TaxRate::updateOrCreate(['tenant_id' => $t, 'name' => 'Zero Rated'], ['code' => 'ZERO', 'rate' => 0, 'type' => 'percentage', 'is_compound' => false, 'is_active' => true]);

        $std = TaxGroup::updateOrCreate(['tenant_id' => $t, 'name' => 'Standard VAT 15%'], ['code' => 'STD15', 'is_default' => false, 'is_active' => true]);
        DB::table('tax_group_rate')->updateOrInsert(['tax_group_id' => $std->id, 'tax_rate_id' => $vat15->id], ['tenant_id' => $t, 'priority' => 1]);

        $sd5g = TaxGroup::updateOrCreate(['tenant_id' => $t, 'name' => 'SD 5%'], ['code' => 'SD5G', 'is_default' => false, 'is_active' => true]);
        DB::table('tax_group_rate')->updateOrInsert(['tax_group_id' => $sd5g->id, 'tax_rate_id' => $sd5->id], ['tenant_id' => $t, 'priority' => 1]);

        $sd10g = TaxGroup::updateOrCreate(['tenant_id' => $t, 'name' => 'SD 10%'], ['code' => 'SD10G', 'is_default' => false, 'is_active' => true]);
        DB::table('tax_group_rate')->updateOrInsert(['tax_group_id' => $sd10g->id, 'tax_rate_id' => $sd10->id], ['tenant_id' => $t, 'priority' => 1]);

        $aitg = TaxGroup::updateOrCreate(['tenant_id' => $t, 'name' => 'AIT 5%'], ['code' => 'AIT5G', 'is_default' => false, 'is_active' => true]);
        DB::table('tax_group_rate')->updateOrInsert(['tax_group_id' => $aitg->id, 'tax_rate_id' => $ait5->id], ['tenant_id' => $t, 'priority' => 1]);

        $zerog = TaxGroup::updateOrCreate(['tenant_id' => $t, 'name' => 'Zero Rated'], ['code' => 'ZERO', 'is_default' => true, 'is_active' => true]);
        DB::table('tax_group_rate')->updateOrInsert(['tax_group_id' => $zerog->id, 'tax_rate_id' => $zero->id], ['tenant_id' => $t, 'priority' => 1]);
    }

    private function seedExpenseCategories(): void
    {
        foreach (['Rent', 'Utilities', 'Transport', 'Salaries', 'Marketing', 'Office Supplies', 'Maintenance', 'Miscellaneous'] as $name) {
            ExpenseCategory::updateOrCreate(['tenant_id' => $this->tenantId, 'name' => $name]);
        }
    }

    private function seedCoupons(): void
    {
        $coupons = [
            ['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10, 'min_purchase' => 500, 'usage_limit' => 100, 'expires_at' => '2026-12-31'],
            ['code' => 'FLAT200', 'type' => 'fixed', 'value' => 200, 'min_purchase' => 2000, 'usage_limit' => 50, 'expires_at' => '2026-12-31'],
            ['code' => 'EID15', 'type' => 'percentage', 'value' => 15, 'min_purchase' => 1000, 'usage_limit' => 200, 'expires_at' => '2026-10-31'],
            ['code' => 'NEWYEAR', 'type' => 'fixed', 'value' => 500, 'min_purchase' => 5000, 'usage_limit' => 30, 'expires_at' => '2027-01-31'],
        ];
        foreach ($coupons as $c) {
            Coupon::updateOrCreate(['tenant_id' => $this->tenantId, 'code' => $c['code']], [
                'type' => $c['type'], 'value' => $c['value'], 'min_purchase' => $c['min_purchase'],
                'usage_limit' => $c['usage_limit'], 'starts_at' => now()->subMonth(), 'expires_at' => $c['expires_at'], 'is_active' => true,
            ]);
        }
    }

    private function seedSales(array $customers, array $products): void
    {
        $phoneKeys = array_keys($customers);
        $productList = array_values($products);
        $methods = ['cash', 'card', 'mobile_banking'];

        for ($day = 29; $day >= 0; $day--) {
            $salesPerDay = rand(3, 8);
            for ($s = 0; $s < $salesPerDay; $s++) {
                $customerPhone = $phoneKeys[array_rand($phoneKeys)];
                $customer = $customers[$customerPhone];
                $itemCount = rand(1, 5);
                $items = [];
                $subtotal = 0;

                for ($i = 0; $i < $itemCount; $i++) {
                    $product = $productList[array_rand($productList)];
                    $qty = rand(1, 4);
                    $lineTotal = $product->selling_price * $qty;
                    $subtotal += $lineTotal;
                    $items[] = ['product_id' => $product->id, 'name' => $product->name, 'quantity' => $qty,
                        'unit_price' => $product->selling_price, 'unit_cost' => $product->purchase_price,
                        'discount_amount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => $lineTotal];
                }

                $total = $subtotal;
                $paidAmount = $s === 0 && $day === 0 ? (int) ($total * 0.6) : $total;
                $paymentStatus = $paidAmount >= $total ? 'paid' : 'partial';
                $dueAmount = max(0, $total - $paidAmount);

                $sale = Sale::create([
                    'tenant_id' => $this->tenantId, 'business_id' => $this->businessId,
                    'branch_id' => $this->branchId, 'warehouse_id' => $this->warehouseId,
                    'customer_id' => $customer->id, 'user_id' => $this->ownerId,
                    'invoice_number' => 'INV-'.str_pad(Sale::where('tenant_id', $this->tenantId)->count() + 1, 5, '0', STR_PAD_LEFT),
                    'status' => 'completed', 'payment_status' => $paymentStatus,
                    'subtotal' => $subtotal, 'discount_amount' => 0, 'coupon_discount' => 0,
                    'tax_amount' => 0, 'shipping_amount' => 0, 'total' => $total,
                    'paid_amount' => $paidAmount, 'change_amount' => 0, 'due_amount' => $dueAmount,
                    'sold_at' => now()->subDays($day)->addHours(rand(9, 20))->addMinutes(rand(0, 59)),
                ]);

                foreach ($items as $item) {
                    SaleItem::create(array_merge($item, ['tenant_id' => $this->tenantId, 'sale_id' => $sale->id]));
                }

                SalePayment::create([
                    'tenant_id' => $this->tenantId, 'sale_id' => $sale->id,
                    'method' => $methods[array_rand($methods)], 'amount' => $paidAmount,
                    'paid_at' => $sale->sold_at,
                ]);
            }
        }
    }

    private function seedPurchases(array $suppliers, array $products): void
    {
        $supplierPhones = array_keys($suppliers);
        $productList = array_values($products);

        for ($i = 0; $i < 8; $i++) {
            $supplierPhone = $supplierPhones[array_rand($supplierPhones)];
            $supplier = $suppliers[$supplierPhone];
            $itemCount = rand(2, 6);
            $items = [];
            $subtotal = 0;

            for ($j = 0; $j < $itemCount; $j++) {
                $product = $productList[array_rand($productList)];
                $qty = rand(10, 50);
                $lineTotal = $product->purchase_price * $qty;
                $subtotal += $lineTotal;
                $items[] = ['product_id' => $product->id, 'quantity' => $qty,
                    'unit_cost' => $product->purchase_price, 'discount_amount' => 0,
                    'tax_amount' => 0, 'line_total' => $lineTotal];
            }

            $total = $subtotal;
            $paidAmount = $i < 4 ? $total : (int) ($total * 0.5);

            $purchase = Purchase::create([
                'tenant_id' => $this->tenantId, 'business_id' => $this->businessId,
                'warehouse_id' => $this->warehouseId, 'supplier_id' => $supplier->id,
                'user_id' => $this->ownerId,
                'reference_number' => 'PO-'.str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'status' => 'received', 'payment_status' => $paidAmount >= $total ? 'paid' : 'partial',
                'subtotal' => $subtotal, 'discount_amount' => 0, 'tax_amount' => 0,
                'shipping_amount' => 0, 'total' => $total, 'paid_amount' => $paidAmount,
                'due_amount' => $total - $paidAmount,
                'purchased_at' => now()->subDays(rand(0, 30))->addHours(rand(9, 17)),
            ]);

            foreach ($items as $item) {
                PurchaseItem::create(array_merge($item, ['tenant_id' => $this->tenantId, 'purchase_id' => $purchase->id]));
            }
        }
    }

    private function seedExpenses(): void
    {
        $cats = ExpenseCategory::where('tenant_id', $this->tenantId)->get();
        $expenses = [
            ['cat' => 'Rent', 'desc' => 'Monthly shop rent', 'amount' => 25000, 'method' => 'bank', 'days_ago' => 15],
            ['cat' => 'Utilities', 'desc' => 'Electricity bill', 'amount' => 3500, 'method' => 'cash', 'days_ago' => 10],
            ['cat' => 'Transport', 'desc' => 'Delivery fuel cost', 'amount' => 1200, 'method' => 'cash', 'days_ago' => 8],
            ['cat' => 'Salaries', 'desc' => 'Staff salary', 'amount' => 45000, 'method' => 'bank', 'days_ago' => 5],
            ['cat' => 'Marketing', 'desc' => 'Facebook ads campaign', 'amount' => 5000, 'method' => 'mobile_banking', 'days_ago' => 3],
            ['cat' => 'Office Supplies', 'desc' => 'Printer paper and ink', 'amount' => 1500, 'method' => 'cash', 'days_ago' => 2],
            ['cat' => 'Maintenance', 'desc' => 'AC servicing', 'amount' => 2000, 'method' => 'cash', 'days_ago' => 1],
            ['cat' => 'Miscellaneous', 'desc' => 'Staff tea and snacks', 'amount' => 500, 'method' => 'cash', 'days_ago' => 0],
        ];

        foreach ($expenses as $e) {
            $cat = $cats->firstWhere('name', $e['cat']);
            if ($cat) {
                Expense::create([
                    'tenant_id' => $this->tenantId, 'branch_id' => $this->branchId,
                    'expense_category_id' => $cat->id, 'user_id' => $this->ownerId,
                    'reference' => 'EXP-'.strtoupper(Str::random(6)),
                    'amount' => $e['amount'], 'payment_method' => $e['method'],
                    'status' => 'approved', 'note' => $e['desc'],
                    'spent_at' => now()->subDays($e['days_ago'])->toDateString(),
                ]);
            }
        }
    }
}
