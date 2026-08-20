<?php

namespace App\Services\Analytics;

use App\Models\Product;
use App\Models\SaleItem;

class ForecastService
{
    public function forecastProduct($productId, int $days = 30): array
    {
        $product = Product::find($productId);
        if (! $product) {
            return [
                'product_id' => (string) $productId,
                'predicted_demand' => 0,
                'confidence' => 0,
                'trend' => 'stable',
            ];
        }

        $salesItems = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->where('sales.sold_at', '>=', now()->subDays(90))
            ->where('sale_items.product_id', $product->id)
            ->orderBy('sales.sold_at')
            ->get();

        if ($salesItems->isEmpty()) {
            return [
                'product_id' => $product->id,
                'predicted_demand' => 0,
                'confidence' => 0,
                'trend' => 'stable',
            ];
        }

        $quantities = $salesItems->pluck('quantity')->toArray();

        $averageDaily = $this->simpleMovingAverage($quantities, min(7, count($quantities)));
        $trend = $this->calculateTrend($quantities);
        $confidence = $this->calculateConfidence($quantities, $averageDaily);

        $predictedDemand = $averageDaily * $days;

        return [
            'product_id' => $product->id,
            'predicted_demand' => round($predictedDemand, 2),
            'confidence' => round($confidence, 2),
            'trend' => $trend,
        ];
    }

    private function simpleMovingAverage(array $quantities, int $period): float
    {
        $count = count($quantities);
        if ($count === 0) {
            return 0;
        }

        if ($count <= $period) {
            return array_sum($quantities) / $count;
        }

        $start = $count - $period;

        return array_sum(array_slice($quantities, $start)) / $period;
    }

    private function calculateTrend(array $quantities): string
    {
        $count = count($quantities);
        if ($count < 2) {
            return 'stable';
        }

        $mid = (int) floor($count / 2);
        $firstHalf = array_sum(array_slice($quantities, 0, $mid));
        $secondHalf = array_sum(array_slice($quantities, $mid));

        if ($secondHalf > $firstHalf * 1.1) {
            return 'increasing';
        }

        if ($firstHalf > $secondHalf * 1.1) {
            return 'decreasing';
        }

        return 'stable';
    }

    private function calculateConfidence(array $quantities, float $averageDaily): float
    {
        $count = count($quantities);
        if ($count < 2) {
            return 0;
        }

        $mean = array_sum($quantities) / $count;
        $variance = array_sum(array_map(function ($q) use ($mean) {
            return pow($q - $mean, 2);
        }, $quantities)) / $count;

        $stdDev = sqrt($variance);
        $cv = $mean > 0 ? $stdDev / $mean : 0;

        return max(0, min(100, 100 - ($cv * 50)));
    }
}
