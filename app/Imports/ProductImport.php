<?php

namespace Database\Imports;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProductImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function collection($rows)
    {
        $validated = $this->validator()->validate();

        if ($validated->fails()) {
            return $validated->errors()->toArray();
        }

        $errors = [];
        $validRows = 0;

        foreach ($rows as $row) {
            try {
                $data = $this->prepareRow($row);

                $existing = Product::where('tenant_id', auth()->user()?->tenant_id ?? 1)
                    ->where('sku', $data['sku'] ?? null)
                    ->first();

                if ($existing) {
                    $errors[] = ['row' => $validRows + 1, 'column' => 'sku', 'message' => 'SKU already exists for this tenant'];

                    continue;
                }

                $product = Product::create($data);
                $validRows++;
            } catch (\Exception $e) {
                $errors[] = ['row' => $validRows + 1, 'column' => null, 'message' => $e->getMessage()];
            }
        }

        return [
            'rows_valid' => $validRows,
            'rows_invalid' => count($rows) - $validRows - count($errors),
            'errors' => $errors,
        ];
    }

    public function validator()
    {
        return Validator::make(request()->all(), [
            'file' => 'required|mimes:csv,xlsx,xls',
        ]);
    }

    protected function prepareRow(array $row)
    {
        return [
            'name' => $row['name'] ?? null,
            'sku' => $row['sku'] ?? null,
            'barcode' => $row['barcode'] ?? null,
            'category_id' => $this->resolveCategory($row['category'] ?? null),
            'brand_id' => $this->resolveBrand($row['brand'] ?? null),
            'unit_id' => $this->resolveUnit($row['unit'] ?? null),
            'purchase_price' => $row['purchase_price'] ? (float) $row['purchase_price'] : 0,
            'selling_price' => $row['selling_price'] ? (float) $row['selling_price'] : 0,
            'tax_rate' => $row['tax_rate'] ? (float) $row['tax_rate'] : 0,
            'min_stock_level' => $row['min_stock_level'] ? (int) $row['min_stock_level'] : 0,
            'is_active' => $row['is_active'] ?? true,
        ];
    }

    protected function resolveCategory(?string $name)
    {
        if (! $name) {
            return null;
        }

        return Category::where('tenant_id', auth()->user()?->tenant_id ?? 1)
            ->where('name', $name)
            ->firstOrCreate(['name' => $name])->id;
    }

    protected function resolveBrand(?string $name)
    {
        if (! $name) {
            return null;
        }

        return Brand::where('tenant_id', auth()->user()?->tenant_id ?? 1)
            ->where('name', $name)
            ->firstOrCreate(['name' => $name])->id;
    }

    protected function resolveUnit(?string $name)
    {
        if (! $name) {
            return null;
        }

        return Unit::where('tenant_id', auth()->user()?->tenant_id ?? 1)
            ->where('name', $name)
            ->firstOrCreate(['name' => $name])->id;
    }
}
