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
use App\Domain\Enums\AccountType;
use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\PurchaseStatus;
use App\Domain\Enums\SaleStatus;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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

    public function exportCsv(string $module, Request $request): string
    {
        $query = $this->getQuery($module, $request);
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

    public function exportPdf(string $module, Request $request): string
    {
        $data = $this->exportCsv($module, $request);

        $pdf = PDF::loadView('exports.export', [
            'module' => $module,
            'headers' => $this->columnMappings[$module],
            'rows' => collect(explode("\n", $data))->skip(1)->map(function ($line) {
                return str_getcsv($line);
            })->toArray(),
        ]);

        return $pdf->output();
    }

    /**
     * The export must return exactly what the matching index endpoint would, minus
     * pagination — an export that silently ignores the filters the user set is worse
     * than no export. Each module therefore declares the same filters as its index
     * action, run through the same QueryPipeline.
     *
     * `lenient()` because the export URL also carries `format`, which is not a filter.
     */
    protected function getQuery(string $module, Request $request): Builder
    {
        return match ($module) {
            'products' => QueryPipeline::for(Product::query()->with(['category', 'brand']), $request)
                ->lenient()
                ->search(['name', 'sku', 'barcode'])
                ->allow(
                    AllowedFilter::exact('category_id'),
                    AllowedFilter::exact('brand_id'),
                    AllowedFilter::boolean('is_active'),
                )
                ->sortable(['name', 'sku', 'selling_price', 'purchase_price', 'created_at'], default: 'name')
                ->toQuery(),

            'inventory' => QueryPipeline::for(InventoryBalance::query()->with(['product', 'warehouse']), $request)
                ->lenient()
                ->allow(
                    AllowedFilter::exact('warehouse_id'),
                    AllowedFilter::exact('product_id'),
                    AllowedFilter::searchRelation('search', 'product', ['name', 'sku']),
                )
                ->sortable(['quantity', 'average_cost'], default: '-quantity')
                ->toQuery(),

            'sales' => QueryPipeline::for(Sale::query()->with(['customer', 'cashier']), $request)
                ->lenient()
                ->search(['invoice_number'])
                ->allow(
                    AllowedFilter::exact('branch_id'),
                    AllowedFilter::exact('customer_id'),
                    AllowedFilter::enum('status', SaleStatus::class),
                    AllowedFilter::enum('payment_status', PaymentStatus::class),
                    AllowedFilter::dateRange('sold_at'),
                )
                ->sortable(['id', 'invoice_number', 'total', 'sold_at'], default: '-id')
                ->toQuery(),

            'purchases' => QueryPipeline::for(Purchase::query()->with('supplier'), $request)
                ->lenient()
                ->search(['reference_number'])
                ->allow(
                    AllowedFilter::exact('supplier_id'),
                    AllowedFilter::enum('status', PurchaseStatus::class),
                    AllowedFilter::enum('payment_status', PaymentStatus::class),
                    AllowedFilter::dateRange('purchased_at'),
                )
                ->sortable(['id', 'reference_number', 'total', 'purchased_at'], default: '-id')
                ->toQuery(),

            'customers' => QueryPipeline::for(Customer::query(), $request)
                ->lenient()
                ->search(['name', 'phone', 'email'])
                ->sortable(['name', 'phone', 'email', 'balance', 'created_at'], default: 'name')
                ->toQuery(),

            'suppliers' => QueryPipeline::for(Supplier::query(), $request)
                ->lenient()
                ->search(['name', 'company', 'phone'])
                ->sortable(['name', 'company', 'phone', 'balance', 'created_at'], default: 'name')
                ->toQuery(),

            'accounting' => QueryPipeline::for(Account::query(), $request)
                ->lenient()
                ->search(['name', 'account_number'])
                ->allow(
                    AllowedFilter::enum('type', AccountType::class),
                    AllowedFilter::boolean('is_active'),
                )
                ->sortable(['name', 'balance'], default: 'name')
                ->toQuery(),

            // 'reports' exports the journal, matching JournalController@index.
            default => QueryPipeline::for(JournalEntry::query(), $request)
                ->lenient()
                ->search(['reference', 'description'])
                ->allow(AllowedFilter::dateRange('entry_date'))
                ->sortable(['id', 'entry_date'], default: '-id')
                ->toQuery(),
        };
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
