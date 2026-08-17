<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 499,
                'interval' => 'month',
                'limits' => [
                    'branches' => 1,
                    'warehouses' => 1,
                    'users' => 2,
                    'products' => 100,
                    'monthly_sales' => 500,
                ],
                'features' => [
                    ['key' => 'products', 'label' => 'Product limit', 'value' => '100', 'included' => true],
                    ['key' => 'product_variations', 'label' => 'Product variation', 'value' => 'Simple & Variable', 'included' => true],
                    ['key' => 'import', 'label' => 'Import Product Service', 'value' => '', 'included' => true],
                    ['key' => 'export', 'label' => 'Export Product Service', 'value' => '', 'included' => false],
                    ['key' => 'staff', 'label' => 'Staff limit', 'value' => '2', 'included' => true],
                    ['key' => 'warehouses', 'label' => 'Inventory locations', 'value' => '1', 'included' => true],
                    ['key' => 'invoices', 'label' => 'Number of Invoices', 'value' => '500/month', 'included' => true],
                    ['key' => 'pos', 'label' => 'POS Service', 'value' => '', 'included' => true],
                    ['key' => 'terminals', 'label' => 'Terminal Limit', 'value' => '1', 'included' => true],
                    ['key' => 'coupons', 'label' => 'Coupon Service', 'value' => '', 'included' => false],
                    ['key' => 'reports', 'label' => 'Report Service', 'value' => 'Basic', 'included' => true],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'price' => 999,
                'interval' => 'month',
                'limits' => [
                    'branches' => 3,
                    'warehouses' => 3,
                    'users' => 10,
                    'products' => 1000,
                    'monthly_sales' => 5000,
                ],
                'features' => [
                    ['key' => 'products', 'label' => 'Product limit', 'value' => '1,000', 'included' => true],
                    ['key' => 'product_variations', 'label' => 'Product variation', 'value' => 'Simple & Variable', 'included' => true],
                    ['key' => 'import', 'label' => 'Import Product Service', 'value' => '', 'included' => true],
                    ['key' => 'export', 'label' => 'Export Product Service', 'value' => '', 'included' => false],
                    ['key' => 'staff', 'label' => 'Staff limit', 'value' => '10', 'included' => true],
                    ['key' => 'warehouses', 'label' => 'Inventory locations', 'value' => '3', 'included' => true],
                    ['key' => 'invoices', 'label' => 'Number of Invoices', 'value' => '5,000/month', 'included' => true],
                    ['key' => 'pos', 'label' => 'POS Service', 'value' => '', 'included' => true],
                    ['key' => 'terminals', 'label' => 'Terminal Limit', 'value' => '3', 'included' => true],
                    ['key' => 'coupons', 'label' => 'Coupon Service', 'value' => '', 'included' => true],
                    ['key' => 'reports', 'label' => 'Report Service', 'value' => 'Advanced', 'included' => true],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'price' => 1499,
                'interval' => 'month',
                'limits' => [
                    'branches' => 10,
                    'warehouses' => 10,
                    'users' => 50,
                    'products' => 10000,
                    'monthly_sales' => 50000,
                ],
                'features' => [
                    ['key' => 'products', 'label' => 'Product limit', 'value' => '10,000', 'included' => true],
                    ['key' => 'product_variations', 'label' => 'Product variation', 'value' => 'Simple & Variable', 'included' => true],
                    ['key' => 'import', 'label' => 'Import Product Service', 'value' => '', 'included' => true],
                    ['key' => 'export', 'label' => 'Export Product Service', 'value' => '', 'included' => true],
                    ['key' => 'staff', 'label' => 'Staff limit', 'value' => '50', 'included' => true],
                    ['key' => 'warehouses', 'label' => 'Inventory locations', 'value' => '10', 'included' => true],
                    ['key' => 'invoices', 'label' => 'Number of Invoices', 'value' => 'Unlimited', 'included' => true],
                    ['key' => 'pos', 'label' => 'POS Service', 'value' => '', 'included' => true],
                    ['key' => 'terminals', 'label' => 'Terminal Limit', 'value' => '10', 'included' => true],
                    ['key' => 'coupons', 'label' => 'Coupon Service', 'value' => '', 'included' => true],
                    ['key' => 'reports', 'label' => 'Report Service', 'value' => 'Full + Analytics', 'included' => true],
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
