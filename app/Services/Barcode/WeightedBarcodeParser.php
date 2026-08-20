<?php

namespace App\Services\Barcode;

use App\Models\Product;

class WeightedBarcodeParser
{
    public function parse(string $barcode): array
    {
        $barcode = trim($barcode);

        $product = $this->findByEmbeddedWeight($barcode);

        if ($product) {
            return $product;
        }

        return [
            'product_id' => null,
            'weight' => null,
            'raw' => $barcode,
        ];
    }

    private function findByEmbeddedWeight(string $barcode): ?array
    {
        $weight = $this->extractWeightFromGs1($barcode);

        if ($weight !== null) {
            return $this->resolveProduct($barcode, $weight, 2);
        }

        $weight = $this->extractWeightFromItf($barcode);

        if ($weight !== null) {
            return $this->resolveProduct($barcode, $weight, 6);
        }

        return null;
    }

    private function extractWeightFromGs1(string $barcode): ?float
    {
        if (strlen($barcode) < 8 || ! str_starts_with($barcode, '21')) {
            return null;
        }

        $digits = substr($barcode, 2);
        $weightStr = substr($digits, -5);

        if (! ctype_digit($weightStr)) {
            return null;
        }

        return (float) $weightStr;
    }

    private function extractWeightFromItf(string $barcode): ?float
    {
        if (strlen($barcode) !== 14 || ! ctype_digit($barcode)) {
            return null;
        }

        $weightStr = substr($barcode, -6);

        if (! ctype_digit($weightStr)) {
            return null;
        }

        return (float) $weightStr;
    }

    private function resolveProduct(string $barcode, float $weightGrams, int $productPartLength): ?array
    {
        $productCode = substr($barcode, 0, $productPartLength);

        $product = Product::withoutTenancy()
            ->where('barcode', 'LIKE', $productCode.'%')
            ->where('is_weighed', true)
            ->first();

        if (! $product) {
            $product = Product::withoutTenancy()
                ->where('barcode', $productCode)
                ->first();
        }

        return [
            'product_id' => $product?->id,
            'weight' => $weightGrams / 1000,
            'raw' => $barcode,
        ];
    }
}
