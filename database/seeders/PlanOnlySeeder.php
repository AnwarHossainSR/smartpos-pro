<?php

namespace Database\Seeders;

use App\Models\Plan;

class PlanOnlySeeder extends TransactionalSeeder
{
    protected function seed(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price' => 0,
                'interval' => 'month',
                'limits' => [
                    'branches' => 1,
                    'warehouses' => 1,
                    'users' => 2,
                    'products' => 50,
                    'monthly_sales' => 200,
                ],
                'features' => [
                    ['key' => 'products', 'label' => 'Product limit', 'value' => '50', 'included' => true],
                    ['key' => 'staff', 'label' => 'Staff limit', 'value' => '2', 'included' => true],
                    ['key' => 'warehouses', 'label' => 'Inventory locations', 'value' => '1', 'included' => true],
                    ['key' => 'invoices', 'label' => 'Number of Invoices', 'value' => '200/month', 'included' => true],
                    ['key' => 'pos', 'label' => 'POS Service', 'value' => '', 'included' => true],
                    ['key' => 'reports', 'label' => 'Report Service', 'value' => 'Basic', 'included' => true],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 499,
                'interval' => 'month',
                'limits' => [
                    'branches' => 1,
                    'warehouses' => 1,
                    'users' => 5,
                    'products' => 500,
                    'monthly_sales' => 1000,
                ],
                'features' => [
                    ['key' => 'products', 'label' => 'Product limit', 'value' => '500', 'included' => true],
                    ['key' => 'staff', 'label' => 'Staff limit', 'value' => '5', 'included' => true],
                    ['key' => 'warehouses', 'label' => 'Inventory locations', 'value' => '1', 'included' => true],
                    ['key' => 'invoices', 'label' => 'Number of Invoices', 'value' => '1,000/month', 'included' => true],
                    ['key' => 'pos', 'label' => 'POS Service', 'value' => '', 'included' => true],
                    ['key' => 'coupons', 'label' => 'Coupon Service', 'value' => '', 'included' => true],
                    ['key' => 'reports', 'label' => 'Report Service', 'value' => 'Advanced', 'included' => true],
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
                    'users' => 20,
                    'products' => 5000,
                    'monthly_sales' => 10000,
                ],
                'features' => [
                    ['key' => 'products', 'label' => 'Product limit', 'value' => '5,000', 'included' => true],
                    ['key' => 'staff', 'label' => 'Staff limit', 'value' => '20', 'included' => true],
                    ['key' => 'warehouses', 'label' => 'Inventory locations', 'value' => '3', 'included' => true],
                    ['key' => 'invoices', 'label' => 'Number of Invoices', 'value' => '10,000/month', 'included' => true],
                    ['key' => 'pos', 'label' => 'POS Service', 'value' => '', 'included' => true],
                    ['key' => 'coupons', 'label' => 'Coupon Service', 'value' => '', 'included' => true],
                    ['key' => 'import', 'label' => 'Import Products', 'value' => '', 'included' => true],
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
