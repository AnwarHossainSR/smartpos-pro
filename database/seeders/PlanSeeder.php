<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'Free', 'slug' => 'free', 'price' => 0,
                'limits' => ['branches' => 1, 'warehouses' => 1, 'users' => 3, 'products' => 50, 'monthly_sales' => 200]],
            ['name' => 'Starter', 'slug' => 'starter', 'price' => 19,
                'limits' => ['branches' => 2, 'warehouses' => 2, 'users' => 5, 'products' => 500, 'monthly_sales' => 2000]],
            ['name' => 'Professional', 'slug' => 'professional', 'price' => 49,
                'limits' => ['branches' => 5, 'warehouses' => 5, 'users' => 20, 'products' => 5000, 'monthly_sales' => 20000]],
            ['name' => 'Business', 'slug' => 'business', 'price' => 99,
                'limits' => ['branches' => 15, 'warehouses' => 15, 'users' => 75, 'products' => 50000, 'monthly_sales' => 100000]],
            ['name' => 'Enterprise', 'slug' => 'enterprise', 'price' => 299,
                'limits' => ['branches' => null, 'warehouses' => null, 'users' => null, 'products' => null, 'monthly_sales' => null]],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
