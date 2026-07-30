<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Billing\PlanLimitService;
use App\Services\Inventory\StockLedgerService;
use App\Support\Concerns\ValidatesSort;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use ValidatesSort;

    protected const SORTABLE = ['name', 'sku', 'selling_price', 'purchase_price', 'created_at'];

    public function __construct(protected StockLedgerService $ledger) {}

    public function index(Request $request)
    {
        [$sort, $direction] = $this->resolveSort($request, self::SORTABLE, 'name');

        $products = Product::query()
            ->with(['category', 'brand'])
            ->withSum('inventoryBalances as stock_quantity', 'quantity')
            ->when($request->search, function ($q, $s) {
                $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                    ->orWhere('sku', 'like', "%{$s}%")
                    ->orWhere('barcode', 'like', "%{$s}%"));
            })
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->brand_id, fn ($q, $id) => $q->where('brand_id', $id))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('low_stock'), fn ($q) => $q->havingRaw('stock_quantity <= min_stock_level'))
            ->orderBy($sort, $direction)
            ->paginate($request->integer('per_page', 25));

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
                (int) $opening['warehouse_id'],
                (float) $opening['quantity'],
                (float) $product->purchase_price,
            );
        }

        return new ProductResource($product->load('category', 'brand'));
    }

    public function show(Product $product): ProductResource
    {
        $product->loadSum('inventoryBalances as stock_quantity', 'quantity');

        return new ProductResource($product->load('category', 'brand', 'unit'));
    }

    /** POS barcode-scanner lookup: resolves a single product by its exact barcode. */
    public function findByBarcode(string $barcode): ProductResource
    {
        $product = Product::where('barcode', $barcode)->firstOrFail();
        $product->loadSum('inventoryBalances as stock_quantity', 'quantity');

        return new ProductResource($product->load('category', 'brand', 'unit'));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product->load('category', 'brand'));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->noContent();
    }
}
