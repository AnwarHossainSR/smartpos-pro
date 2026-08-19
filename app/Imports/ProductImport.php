<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Product CSV/XLSX import. Rows are validated one at a time so a bad row is
 * reported with its line number instead of failing the whole file; in dry-run
 * mode the same checks run without writing anything.
 */
class ProductImport implements ToCollection, WithHeadingRow
{
    /** @var list<array{row: int, column: ?string, message: string}> */
    public array $errors = [];

    public int $rowsValid = 0;

    public function __construct(private readonly bool $dryRun = false) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            // Heading row is line 1 and $index is zero-based, so data starts at line 2.
            $this->importRow($row->toArray(), $index + 2);
        }
    }

    private function importRow(array $row, int $line): void
    {
        // Spreadsheet readers hand back numeric cells as ints/floats — barcodes and
        // SKUs are text as far as the rest of the import is concerned.
        foreach (['name', 'sku', 'barcode', 'category', 'brand', 'unit'] as $column) {
            if (isset($row[$column]) && ! is_string($row[$column])) {
                $row[$column] = (string) $row[$column];
            }
        }

        $validator = Validator::make($row, [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_stock_level' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $column => $messages) {
                $this->errors[] = ['row' => $line, 'column' => $column, 'message' => $messages[0]];
            }

            return;
        }

        if (Product::where('sku', $row['sku'])->exists()) {
            $this->errors[] = ['row' => $line, 'column' => 'sku', 'message' => 'SKU already exists.'];

            return;
        }

        if (! $this->dryRun) {
            Product::create([
                'category_id' => $this->resolveCategory($row['category'] ?? null),
                'brand_id' => $this->resolveBrand($row['brand'] ?? null),
                'unit_id' => $this->resolveUnit($row['unit'] ?? null),
                'name' => $row['name'],
                'slug' => Str::slug($row['name']),
                'sku' => $row['sku'],
                'barcode' => $row['barcode'] ?? null,
                'purchase_price' => (float) ($row['purchase_price'] ?? 0),
                'selling_price' => (float) ($row['selling_price'] ?? 0),
                'tax_rate' => (float) ($row['tax_rate'] ?? 0),
                'min_stock_level' => (float) ($row['min_stock_level'] ?? 0),
                'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOL),
            ]);
        }

        $this->rowsValid++;
    }

    private function resolveCategory(?string $name): ?string
    {
        if (blank($name)) {
            return null;
        }

        return Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id;
    }

    private function resolveBrand(?string $name): ?string
    {
        if (blank($name)) {
            return null;
        }

        return Brand::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id;
    }

    private function resolveUnit(?string $name): ?string
    {
        if (blank($name)) {
            return null;
        }

        return Unit::firstOrCreate(['name' => $name], ['short_name' => Str::limit($name, 8, '')])->id;
    }
}
