<?php

namespace App\Services\Cache;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Warehouse;

class PosBootstrapService
{
    public function bootstrap($tenantId, $branchId): array
    {
        $catalog = cache()->remember(
            "pos:bootstrap:catalog:{$tenantId}:{$branchId}",
            3600,
            function () use ($tenantId, $branchId) {
                return $this->getCatalogData($tenantId, $branchId);
            }
        );

        $live = $this->getLiveData($tenantId, $branchId);

        return [
            'catalog' => $catalog,
            'live' => $live,
        ];
    }

    private function getCatalogData($tenantId, $branchId): array
    {
        $products = Product::where('tenant_id', $tenantId)
            ->select('id', 'name', 'sku', 'selling_price', 'purchase_price', 'stock', 'min_stock_level',
                'image_path as image_url', 'category_id', 'brand_id', 'unit_id')
            ->get()
            ->toArray();

        $categories = Category::where('tenant_id', $tenantId)
            ->select('id', 'name', 'slug', 'parent_id')
            ->get()
            ->toArray();

        $brands = Brand::where('tenant_id', $tenantId)
            ->select('id', 'name', 'slug')
            ->get()
            ->toArray();

        $units = Unit::where('tenant_id', $tenantId)
            ->select('id', 'name', 'short_name')
            ->get()
            ->toArray();

        $taxProfiles = TaxProfile::where('tenant_id', $tenantId)->get()->toArray();
        $taxRates = TaxRate::where('tenant_id', $tenantId)->get()->toArray();

        return [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'units' => $units,
            'tax_profiles' => $taxProfiles,
            'tax_rates' => $taxRates,
        ];
    }

    private function getLiveData($tenantId, $branchId): array
    {
        $branch = Branch::where('tenant_id', $tenantId)->find($branchId);

        $warehouse = Warehouse::where('branch_id', $branchId)->where('is_active', true)
            ->orderByDesc('is_default')->first();

        return [
            'warehouse' => $warehouse ? ['id' => $warehouse->id, 'name' => $warehouse->name] : null,
        ];
    }
}
