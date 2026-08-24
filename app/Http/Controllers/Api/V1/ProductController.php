<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Requests\Media\UploadImageRequest;
use App\Http\Resources\ProductResource;
use App\Imports\ProductImport;
use App\Models\Product;
use App\Services\Barcode\WeightedBarcodeParser;
use App\Services\Billing\PlanLimitService;
use App\Services\Inventory\StockLedgerService;
use App\Services\Media\MediaService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StockLedgerService $ledger,
        protected MediaService $media,
    ) {}

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
        $parser = app(WeightedBarcodeParser::class);
        $parsed = $parser->parse($barcode);

        if ($parsed['product_id'] !== null) {
            $product = Product::findOrFail($parsed['product_id']);
            $product->loadSum('inventoryBalances as stock_quantity', 'quantity');

            $resource = new ProductResource($product->load('category', 'brand', 'unit', 'taxGroup'));
            $resource->additional['parsed_weight'] = $parsed['weight'];

            return $resource;
        }

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

    public function uploadImage(UploadImageRequest $request, Product $product): JsonResponse
    {
        $path = $this->media->replace(
            $product->image_path,
            $request->file('file'),
            $request->collection(),
            $product->tenant_id,
        );

        $product->update(['image_path' => $path]);

        return $this->success([
            'path' => $path,
            'url' => $this->media->url($path),
            'urls' => $this->media->urls($path, $request->collection()),
        ]);
    }

    public function deleteImage(Product $product): JsonResponse
    {
        $this->media->delete($product->image_path);
        $product->update(['image_path' => null]);

        return $this->success(['path' => null, 'url' => null, 'urls' => null]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx|max:10240',
            'dry_run' => 'nullable|boolean',
        ]);

        $dryRun = $request->boolean('dry_run', false);
        $file = $request->file('file');

        if ($dryRun) {
            return $this->dryRunImport($file);
        }

        return $this->executeImport($file);
    }

    private function dryRunImport($file): JsonResponse
    {
        $import = new ProductImport(dryRun: true);
        $rowsValid = 0;
        $errors = [];

        try {
            Excel::import($import, $file);
            $rowsValid = $import->rowsValid;
            $errors = $import->errors;
        } catch (\Throwable $e) {
            $errors[] = [
                'row' => 0,
                'message' => 'File parsing error: '.$e->getMessage(),
            ];
        }

        return $this->success([
            'rows_valid' => $rowsValid,
            'rows_invalid' => count($errors),
            'errors' => $errors,
        ]);
    }

    private function executeImport($file): JsonResponse
    {
        $import = new ProductImport;

        try {
            DB::transaction(function () use ($import, $file) {
                Excel::import($import, $file);

                if (! empty($import->errors)) {
                    throw new ValidationException(
                        Validator::make(
                            ['errors' => $import->errors],
                            ['errors' => 'required']
                        ),
                        'Import completed with errors'
                    );
                }
            });
        } catch (ValidationException $e) {
            return $this->error('Import completed with errors', 422, ['errors' => $import->errors]);
        }

        return $this->success(null, 'Products imported successfully');
    }

    public function template()
    {
        $csv = "name,sku,barcode,category,brand,unit,purchase_price,selling_price,tax_rate,min_stock_level,is_active\n";
        $csv .= "Cola 500ml,SKU-COLA-001,8901030123456,Beverages,BrandX,piece,5.00,8.00,10,5,1\n";
        $csv .= "Chips Pack,SKU-CHIPS-001,8901030123457,Snacks,BrandY,pack,3.00,5.00,10,10,1\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=product_import_template.csv',
        ]);
    }
}
