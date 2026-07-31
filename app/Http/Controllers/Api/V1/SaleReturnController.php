<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sales\Data\CreateSaleReturnData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleReturnRequest;
use App\Http\Resources\SaleReturnResource;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\Sales\SaleReturnService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleReturnController extends Controller
{
    public function __construct(protected SaleReturnService $returns) {}

    public function index(Request $request)
    {
        $returns = QueryPipeline::for(SaleReturn::query(), $request)
            ->allow(
                AllowedFilter::exact('sale_id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::dateRange('returned_at'),
            )
            ->sortable(['id', 'total', 'returned_at'], default: '-id')
            ->paginate();

        return SaleReturnResource::collection($returns);
    }

    /** Create a return/refund against a completed sale. */
    public function store(StoreSaleReturnRequest $request, Sale $sale): JsonResponse
    {
        $return = $this->returns->create(
            $sale,
            CreateSaleReturnData::fromArray($request->validated()),
            $request->user(),
        );

        return (new SaleReturnResource($return))->response()->setStatusCode(201);
    }

    public function show(SaleReturn $saleReturn): SaleReturnResource
    {
        return new SaleReturnResource($saleReturn->load('items'));
    }
}
