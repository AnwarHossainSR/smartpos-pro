<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Billing\PlanLimitService;
use App\Services\Inventory\StockLedgerService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(protected StockLedgerService $ledger) {}

    public function index(Request $request)
    {
        $products = QueryPipeline::for(
            Product::query()
                ->with(['category', 'brand', 'taxGroup'])
                ->withSum('inventoryBalances as stock_quantity', 'quantity'),
            $request,
        )
            ->search(['name', 'sku', 'barcode'])
            ->allow(
                AllowedFilter::exact('category_id'),
                AllowedFilter::exact('brand_id'),
                AllowedFilter::boolean('is_active'),
                AllowedFilter::flag('low_stock', fn (Builder $q) => $q->havingRaw('stock_quantity <= min_stock_level')),
            )
            ->sortable(['name', 'sku', 'selling_price', 'purchase_price', 'created_at'], default: 'name')
            ->paginate();

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): ProductResource
    {
        app(PlanLimitService::class)->ensureCanCreate('products');

        $data = $request->validated();
        $opening = $data['opening_stock'] ?? null;
        unset($data['opening_stock']);

        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(5));
        $data['sku'] = $data['sku'] ?? 'SKU-'.Str::upper(Str::random(8));

        $product = Product::create($data);

        if ($opening && (float) $opening['quantity'] > 0) {
            $this->ledger->addOpeningStock(
                $product->id,
                $opening['warehouse_id'],
                (float) $opening['quantity'],
                (float) $product->purchase_price,
            );
        }

        return new ProductResource($product->load('category', 'brand', 'taxGroup'));
    }

    public function show(Product $product): ProductResource
    {
        $product->loadSum('inventoryBalances as stock_quantity', 'quantity');

        return new ProductResource($product->load('category', 'brand', 'unit', 'taxGroup'));
    }

    /** POS barcode-scanner lookup: resolves a single product by its exact barcode. */
    public function findByBarcode(string $barcode): ProductResource
    {
        $product = Product::where('barcode', $barcode)->firstOrFail();
        $product->loadSum('inventoryBalances as stock_quantity', 'quantity');

        return new ProductResource($product->load('category', 'brand', 'unit', 'taxGroup'));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product->load('category', 'brand', 'taxGroup'));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->noContent();
    }
}
