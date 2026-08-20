<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Services\Analytics\ForecastService;
use Illuminate\Support\Collection;

class ReorderSuggestionService
{
    protected ForecastService $forecast;

    public function __construct(ForecastService $forecast)
    {
        $this->forecast = $forecast;
    }

    public function getSuggestions(): Collection
    {
        $products = Product::query()
            ->with('defaultSupplier')
            ->where('is_active', true)
            ->where('reorder_point', '>', 0)
            ->get();

        $suggestions = collect();

        foreach ($products as $product) {
            $currentStock = $product->inventoryBalances()->sum('quantity');
            $reorderPoint = (int) $product->reorder_point;

            if ($currentStock > $reorderPoint) {
                continue;
            }

            $avgDailySales = (float) $product->avg_daily_sales;
            $leadTimeDays = (int) $product->reorder_lead_time_days;
            $forecast = $this->forecast->forecastProduct($product->id);
            $predictedDaily = $forecast['predicted_demand'] / 30;
            $forecastAdjustment = $avgDailySales > 0 && $forecast['predicted_demand'] > 0
                ? $predictedDaily / $avgDailySales
                : 1;
            $suggestedQty = max(0, ceil(($avgDailySales * $leadTimeDays * $forecastAdjustment) - $currentStock + $reorderPoint));

            $suggestions->push([
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'purchase_price' => (float) $product->purchase_price,
                ],
                'supplier' => $product->defaultSupplier ? [
                    'id' => $product->default_supplier_id,
                    'name' => $product->defaultSupplier->name,
                ] : null,
                'current_stock' => $currentStock,
                'reorder_point' => $reorderPoint,
                'avg_daily_sales' => $avgDailySales,
                'lead_time_days' => $leadTimeDays,
                'suggested_qty' => $suggestedQty,
                'forecast' => $forecast,
            ]);
        }

        return $suggestions;
    }
}
