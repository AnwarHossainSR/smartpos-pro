<?php

namespace App\Services\Export;

use App\Models\Account;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use PDF;

class ExportService
{
    protected $columnMappings = [
        'products' => [
            'name', 'sku', 'barcode', 'category_name', 'brand_name',
            'selling_price', 'purchase_price', 'min_stock_level', 'max_stock_level',
            'is_active', 'created_at',
        ],
        'inventory' => [
            'product_sku', 'product_name', 'warehouse', 'quantity',
            'low_stock', 'last_adjusted',
        ],
        'sales' => [
            'invoice_number', 'customer_name', 'customer_phone', 'total',
            'payment_status', 'sold_at', 'user_name',
        ],
        'purchases' => [
            'reference_number', 'supplier_name', 'total',
            'payment_status', 'purchased_at', 'user_name',
        ],
        'customers' => [
            'name', 'email', 'phone', 'balance',
            'credit_limit', 'is_active', 'created_at',
        ],
        'suppliers' => [
            'name', 'company', 'email', 'phone',
            'balance', 'is_active', 'created_at',
        ],
        'reports' => [
            'title', 'period', 'total', 'generated_at',
        ],
        'accounting' => [
            'account_name', 'description', 'debit', 'credit',
            'entry_date', 'reference', 'journal_name',
        ],
    ];

    public function exportCsv(string $module, array $filters): string
    {
        $query = $this->getQuery($module, $filters);
        $rows = $query->get()->toArray();

        $headers = $this->columnMappings[$module];
        $output = fopen('php://memory', 'w');

        fputcsv($output, $headers);

        foreach ($rows as $row) {
            $mapped = $this->mapRow($module, $row);
            fputcsv($output, array_map(function ($cell) {
                return is_null($cell) ? '' : $cell;
            }, $mapped));
        }

        rewind($output);

        return stream_get_contents($output);
    }

    public function exportPdf(string $module, array $filters): string
    {
        $data = $this->exportCsv($module, $filters);

        $pdf = PDF::loadView('exports.export', [
            'module' => $module,
            'headers' => $this->columnMappings[$module],
            'rows' => collect(explode("\n", $data))->skip(1)->map(function ($line) {
                return str_getcsv($line);
            })->toArray(),
        ]);

        return $pdf->output();
    }

    protected function getQuery(string $module, array $filters): Builder
    {
        $models = [
            'products' => Product::query(),
            'inventory' => InventoryBalance::query(),
            'sales' => Sale::query(),
            'purchases' => Purchase::query(),
            'customers' => Customer::query(),
            'suppliers' => Supplier::query(),
            'reports' => JournalEntry::query(),
            'accounting' => Account::query(),
        ];

        $query = $models[$module] ?? JournalEntry::query();

        return $query->withoutGlobalScopes()->withTenant(false);
    }

    protected function mapRow(string $module, array $row): array
    {
        $mapped = [];

        foreach ($this->columnMappings[$module] as $col) {
            $key = $this->resolveKey($module, $col);
            $mapped[$col] = $row[$key] ?? null;
        }

        return $mapped;
    }

    protected function resolveKey(string $module, string $column): string
    {
        $maps = [
            'products' => [
                'category_name' => 'category.name',
                'brand_name' => 'brand.name',
            ],
            'inventory' => [
                'product_sku' => 'product.sku',
                'product_name' => 'product.name',
                'warehouse' => 'warehouse.name',
                'low_stock' => fn ($q) => $q->whereRaw('quantity <= min_stock_level'),
            ],
            'sales' => [
                'customer_name' => 'customer.name',
                'user_name' => 'user.name',
            ],
            'purchases' => [
                'supplier_name' => 'supplier.name',
                'user_name' => 'user.name',
            ],
            'customers' => [
                'balance' => 'balance',
                'credit_limit' => 'credit_limit',
            ],
            'suppliers' => [
                'balance' => 'balance',
            ],
            'accounting' => [
                'debit' => 'total_debit',
                'credit' => 'total_credit',
            ],
        ];

        $moduleMap = $maps[$module] ?? [];

        if (isset($moduleMap[$column])) {
            $key = $moduleMap[$column];

            return is_callable($key) ? $key($row) : $key;
        }

        return $column;
    }
}
