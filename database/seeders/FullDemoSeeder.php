<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
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
use App\Models\Shift;
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

class FullDemoSeeder extends Seeder
{
    private string $tenantId;

    private string $businessId;

    private string $branchId;

    private string $warehouseId;

    private string $ownerId;

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

        $this->tenantId = $owner->tenant_id;
        $this->businessId = $owner->business_id;
        $this->branchId = $owner->branch_id;
        $this->ownerId = $owner->id;
        $this->warehouseId = $owner->business->warehouses->first()?->id ?? '';

        app(TenantContext::class)->set($this->tenantId);

        $this->command?->info('Tenant: Demo Store BD | Login: admin@demostore.com / password123');

        $categories = $this->seedCatalog();
        $customers = $this->seedCustomers();
        $suppliers = $this->seedSuppliers();
        $products = $this->seedProducts($categories);
        $this->seedTax();
        $this->seedExpenseCategories();
        $this->seedCoupons();
        $this->seedCashRegister();
        $this->seedSales($customers, $products);
        $this->seedPurchases($suppliers, $products);
        $this->seedExpenses();
        $this->seedShifts();

        app(AccountSeeder::class)->run();

        $this->command?->info('Full demo seeded: catalog, customers, suppliers, products, tax, sales, purchases, expenses, coupons, shifts.');
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
        ];
        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }

    private function seedCatalog(): array
    {
        $cats = ['Electronics', 'Groceries', 'Clothing', 'Stationery', 'Home & Kitchen', 'Beauty'];
        $categories = [];
        foreach ($cats as $name) {
            $categories[$name] = Category::updateOrCreate(
                ['tenant_id' => $this->tenantId, 'name' => $name],
                ['slug' => Str::slug($name), 'is_active' => true]
            );
        }

        $brands = ['Samsung', 'Unilever', 'Nokia', 'Local Brand', 'Walton', 'Pran', 'Aarong', 'Lifestyle'];
        foreach ($brands as $name) {
            Brand::updateOrCreate(
                ['tenant_id' => $this->tenantId, 'name' => $name],
                ['slug' => Str::slug($name), 'is_active' => true]
            );
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
        $data = [
            ['name' => 'Walk-in Customer', 'phone' => '01700-000001'],
            ['name' => 'Rahim Uddin', 'phone' => '01712-345678', 'email' => 'rahim@test.com', 'billing_address' => '456 Elephant Road, Dhaka', 'credit_limit' => 50000],
            ['name' => 'Karim Store', 'phone' => '01812-345679', 'email' => 'karim@store.com', 'billing_address' => '78 Agrabad, Chittagong', 'credit_limit' => 100000],
            ['name' => 'Fatima Begum', 'phone' => '01912-345680', 'email' => 'fatima@test.com', 'billing_address' => '321 Mirpur, Dhaka', 'credit_limit' => 25000],
            ['name' => 'Haque Trading', 'phone' => '01715-111222', 'email' => 'haque@trading.com', 'billing_address' => '55 Motijheel, Dhaka', 'credit_limit' => 200000],
            ['name' => 'Nusrat Jahan', 'phone' => '01816-333444', 'email' => 'nusrat@test.com', 'billing_address' => '12 Uttara, Dhaka', 'credit_limit' => 30000],
            ['name' => 'Rana Enterprise', 'phone' => '01917-555666', 'email' => 'rana@enterprise.com', 'billing_address' => '88 Gazipur', 'credit_limit' => 150000],
            ['name' => 'Sumaiya Akter', 'phone' => '01618-777888', 'email' => 'sumaiya@test.com', 'billing_address' => '33 Dhanmondi, Dhaka', 'credit_limit' => 20000],
            ['name' => 'Bismillah Traders', 'phone' => '01719-999000', 'email' => 'bismillah@traders.com', 'billing_address' => '7 Narayanganj', 'credit_limit' => 80000],
            ['name' => 'Kabir Ahmed', 'phone' => '01820-112233', 'email' => 'kabir@test.com', 'billing_address' => '44 Bogra', 'credit_limit' => 40000],
            ['name' => 'Fresh Mart', 'phone' => '01921-445566', 'email' => 'fresh@mart.com', 'billing_address' => '9 Sylhet', 'credit_limit' => 60000],
            ['name' => 'Tanvir Hasan', 'phone' => '01622-778899', 'email' => 'tanvir@test.com', 'billing_address' => '21 Comilla', 'credit_limit' => 35000],
            ['name' => 'Mega Stores', 'phone' => '01723-001122', 'email' => 'mega@stores.com', 'billing_address' => '66 Khulna', 'credit_limit' => 120000],
            ['name' => 'Rashida Parveen', 'phone' => '01824-334455', 'email' => 'rashida@test.com', 'billing_address' => '15 Barisal', 'credit_limit' => 15000],
            ['name' => 'City Supermarket', 'phone' => '01925-667788', 'email' => 'city@supermarket.com', 'billing_address' => '42 Rajshahi', 'credit_limit' => 90000],
        ];

        $customers = [];
        foreach ($data as $c) {
            $phone = $c['phone'];
            $customers[$phone] = Customer::updateOrCreate(
                ['tenant_id' => $this->tenantId, 'phone' => $phone],
                ['name' => $c['name'], 'email' => $c['email'] ?? null, 'billing_address' => $c['billing_address'] ?? null,
                    'credit_limit' => $c['credit_limit'] ?? 0, 'is_active' => true]
            );
        }

        return $customers;
    }

    private function seedSuppliers(): array
    {
        $data = [
            ['name' => 'Tech Distributors BD', 'phone' => '01711-111111', 'email' => 'sales@techdist.com', 'address' => '12 Electronic Market, Dhaka', 'balance' => 50000],
            ['name' => 'Fresh Foods Ltd', 'phone' => '01811-222222', 'email' => 'info@freshfoods.com', 'address' => '45 Farmgate, Dhaka', 'balance' => 25000],
            ['name' => 'Cloth House Wholesale', 'phone' => '01911-333333', 'email' => 'order@clothhouse.com', 'address' => '78 Zindabazar, Sylhet', 'balance' => 0],
            ['name' => ' Walton Direct', 'phone' => '01611-444555', 'email' => 'walton@direct.com', 'address' => '100 Dhaka Cantonment', 'balance' => 75000],
            ['name' => 'Pran Agro Industries', 'phone' => '01711-666777', 'email' => 'pran@agro.com', 'address' => '30 Narsingdi', 'balance' => 35000],
            ['name' => 'Aarong Supply Chain', 'phone' => '01811-888999', 'email' => 'supply@aarong.com', 'address' => '5 Gulshan, Dhaka', 'balance' => 0],
        ];

        $suppliers = [];
        foreach ($data as $s) {
            $phone = $s['phone'];
            $suppliers[$phone] = Supplier::updateOrCreate(
                ['tenant_id' => $this->tenantId, 'phone' => $phone],
                ['name' => $s['name'], 'email' => $s['email'], 'address' => $s['address'],
                    'opening_balance' => $s['balance'], 'is_active' => true]
            );
        }

        return $suppliers;
    }

    private function seedProducts(array $categories): array
    {
        $catIds = array_map(fn ($c) => $c->id, $categories);
        $brandNames = Brand::where('tenant_id', $this->tenantId)->pluck('id', 'name')->toArray();
        $unitNames = Unit::where('tenant_id', $this->tenantId)->pluck('id', 'name')->toArray();

        $products = [
            ['name' => 'Samsung Galaxy A15', 'sku' => 'SAM-A15', 'cat' => 'Electronics', 'brand' => 'Samsung', 'unit' => 'piece', 'cost' => 12000, 'price' => 15999, 'min' => 10],
            ['name' => 'Samsung Galaxy A05', 'sku' => 'SAM-A05', 'cat' => 'Electronics', 'brand' => 'Samsung', 'unit' => 'piece', 'cost' => 8500, 'price' => 11500, 'min' => 8],
            ['name' => 'Nokia 105', 'sku' => 'NK-105', 'cat' => 'Electronics', 'brand' => 'Nokia', 'unit' => 'piece', 'cost' => 1500, 'price' => 2200, 'min' => 15],
            ['name' => 'Walton Primo H8', 'sku' => 'WAL-H8', 'cat' => 'Electronics', 'brand' => 'Walton', 'unit' => 'piece', 'cost' => 7500, 'price' => 9999, 'min' => 10],
            ['name' => 'Rice 5kg Bag', 'sku' => 'RCE-5KG', 'cat' => 'Groceries', 'brand' => 'Pran', 'unit' => 'box', 'cost' => 350, 'price' => 450, 'min' => 50],
            ['name' => 'Sunlight Soap', 'sku' => 'SUN-01', 'cat' => 'Groceries', 'brand' => 'Unilever', 'unit' => 'piece', 'cost' => 35, 'price' => 50, 'min' => 100],
            ['name' => 'Pran Chanachur 500g', 'sku' => 'PRN-CC500', 'cat' => 'Groceries', 'brand' => 'Pran', 'unit' => 'piece', 'cost' => 45, 'price' => 65, 'min' => 80],
            ['name' => 'Pencil 10-Pack', 'sku' => 'PEN-10', 'cat' => 'Stationery', 'brand' => 'Local Brand', 'unit' => 'box', 'cost' => 40, 'price' => 60, 'min' => 30],
            ['name' => 'Notebook 200p', 'sku' => 'NOT-200', 'cat' => 'Stationery', 'brand' => 'Local Brand', 'unit' => 'piece', 'cost' => 25, 'price' => 40, 'min' => 50],
            ['name' => 'T-Shirt Basic', 'sku' => 'TSH-01', 'cat' => 'Clothing', 'brand' => 'Aarong', 'unit' => 'piece', 'cost' => 180, 'price' => 350, 'min' => 20],
            ['name' => 'Jeans Classic', 'sku' => 'JNS-01', 'cat' => 'Clothing', 'brand' => 'Aarong', 'unit' => 'piece', 'cost' => 450, 'price' => 850, 'min' => 15],
            ['name' => 'Shirt Formal', 'sku' => 'SHT-01', 'cat' => 'Clothing', 'brand' => 'Lifestyle', 'unit' => 'piece', 'cost' => 300, 'price' => 599, 'min' => 20],
            ['name' => 'Cooking Oil 5L', 'sku' => 'OIL-5L', 'cat' => 'Home & Kitchen', 'brand' => 'Pran', 'unit' => 'piece', 'cost' => 550, 'price' => 680, 'min' => 30],
            ['name' => 'Rice Dal 1kg', 'sku' => 'RDL-1KG', 'cat' => 'Groceries', 'brand' => 'Pran', 'unit' => 'kg', 'cost' => 120, 'price' => 150, 'min' => 40],
            ['name' => 'Shampoo 400ml', 'sku' => 'SHP-400', 'cat' => 'Beauty', 'brand' => 'Unilever', 'unit' => 'piece', 'cost' => 180, 'price' => 250, 'min' => 25],
            ['name' => 'Face Cream 50g', 'sku' => 'FCR-50', 'cat' => 'Beauty', 'brand' => 'Unilever', 'unit' => 'piece', 'cost' => 120, 'price' => 180, 'min' => 30],
            ['name' => 'Tea 500g', 'sku' => 'TEA-500', 'cat' => 'Groceries', 'brand' => 'Pran', 'unit' => 'piece', 'cost' => 200, 'price' => 260, 'min' => 40],
            ['name' => 'Sugar 1kg', 'sku' => 'SGR-1KG', 'cat' => 'Groceries', 'brand' => 'Pran', 'unit' => 'kg', 'cost' => 80, 'price' => 100, 'min' => 50],
            ['name' => 'Laptop Bag', 'sku' => 'LBG-01', 'cat' => 'Electronics', 'brand' => 'Local Brand', 'unit' => 'piece', 'cost' => 250, 'price' => 450, 'min' => 10],
            ['name' => 'Pen Set (5pc)', 'sku' => 'PEN-5S', 'cat' => 'Stationery', 'brand' => 'Local Brand', 'unit' => 'set', 'cost' => 60, 'price' => 90, 'min' => 40],
        ];

        $created = [];
        foreach ($products as $p) {
            $brandId = $brandNames[$p['brand']] ?? null;
            $unitId = $unitNames[$p['unit']] ?? null;
            $created[$p['sku']] = Product::updateOrCreate(
                ['tenant_id' => $this->tenantId, 'sku' => $p['sku']],
                [
                    'name' => $p['name'], 'slug' => Str::slug($p['name']),
                    'category_id' => $categories[$p['cat']]->id,
                    'brand_id' => $brandId, 'unit_id' => $unitId,
                    'purchase_price' => $p['cost'], 'selling_price' => $p['price'],
                    'min_stock_level' => $p['min'], 'track_inventory' => true, 'is_active' => true,
                ]
            );
        }

        return $created;
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

        foreach (['Dhaka', 'Chittagong', 'Rajshahi', 'Khulna', 'Barisal', 'Rangpur', 'Mymensingh', 'Sylhet'] as $i => $zone) {
            TaxZone::updateOrCreate(['tenant_id' => $t, 'name' => "$zone Division"],
                ['country_code' => 'BD', 'state' => $zone, 'tax_group_id' => $std->id, 'is_default' => $zone === 'Dhaka', 'priority' => 10 - $i, 'is_active' => true]);
        }
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

    private function seedCashRegister(): void
    {
        $register = CashRegister::updateOrCreate(
            ['tenant_id' => $this->tenantId, 'name' => 'Main Register'],
            ['branch_id' => $this->branchId, 'is_active' => true]
        );

        CashRegisterSession::updateOrCreate(
            ['tenant_id' => $this->tenantId, 'cash_register_id' => $register->id, 'status' => 'open'],
            ['user_id' => $this->ownerId, 'opening_cash' => 5000, 'cash_in' => 0, 'cash_out' => 0,
                'expected_cash' => 5000, 'status' => 'open', 'opened_at' => now()]
        );
    }

    private function seedSales(array $customers, array $products): void
    {
        $phoneKeys = array_keys($customers);
        $productList = array_values($products);
        $methods = ['cash', 'card', 'mobile_banking'];

        for ($day = 13; $day >= 0; $day--) {
            $salesPerDay = rand(2, 5);
            for ($s = 0; $s < $salesPerDay; $s++) {
                $customerPhone = $phoneKeys[array_rand($phoneKeys)];
                $customer = $customers[$customerPhone];
                $itemCount = rand(1, 4);
                $items = [];
                $subtotal = 0;

                for ($i = 0; $i < $itemCount; $i++) {
                    $product = $productList[array_rand($productList)];
                    $qty = rand(1, 3);
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

        for ($i = 0; $i < 6; $i++) {
            $supplierPhone = $supplierPhones[array_rand($supplierPhones)];
            $supplier = $suppliers[$supplierPhone];
            $itemCount = rand(2, 5);
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
            $paidAmount = $i < 3 ? $total : (int) ($total * 0.5);

            $purchase = Purchase::create([
                'tenant_id' => $this->tenantId, 'business_id' => $this->businessId,
                'warehouse_id' => $this->warehouseId, 'supplier_id' => $supplier->id,
                'user_id' => $this->ownerId,
                'reference_number' => 'PO-'.str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'status' => 'received', 'payment_status' => $paidAmount >= $total ? 'paid' : 'partial',
                'subtotal' => $subtotal, 'discount_amount' => 0, 'tax_amount' => 0,
                'shipping_amount' => 0, 'total' => $total, 'paid_amount' => $paidAmount,
                'due_amount' => $total - $paidAmount,
                'purchased_at' => now()->subDays(rand(0, 20))->addHours(rand(9, 17)),
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
            ['cat' => 'Rent', 'desc' => 'Monthly shop rent - July', 'amount' => 25000, 'method' => 'bank', 'days_ago' => 15],
            ['cat' => 'Utilities', 'desc' => 'Electricity bill', 'amount' => 3500, 'method' => 'cash', 'days_ago' => 10],
            ['cat' => 'Transport', 'desc' => 'Delivery fuel cost', 'amount' => 1200, 'method' => 'cash', 'days_ago' => 8],
            ['cat' => 'Salaries', 'desc' => 'Staff salary - July', 'amount' => 45000, 'method' => 'bank', 'days_ago' => 5],
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

    private function seedShifts(): void
    {
        for ($day = 6; $day >= 0; $day--) {
            $clockIn = now()->subDays($day)->setTime(9, 0);
            $clockOut = now()->subDays($day)->setTime(rand(17, 21), rand(0, 59));
            $worked = $clockIn->diffInMinutes($clockOut);

            Shift::create([
                'tenant_id' => $this->tenantId, 'user_id' => $this->ownerId,
                'branch_id' => $this->branchId,
                'clock_in' => $clockIn, 'clock_out' => $clockOut,
                'worked_minutes' => $worked, 'status' => 'closed',
            ]);
        }
    }
}
