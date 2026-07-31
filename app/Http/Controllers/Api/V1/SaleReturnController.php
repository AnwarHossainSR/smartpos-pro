<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleReturnRequest;
use App\Http\Resources\SaleReturnResource;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\Sales\SaleReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleReturnController extends Controller
{
    public function __construct(protected SaleReturnService $returns) {}

    public function index(Request $request)
    {
        $returns = SaleReturn::query()
            ->when($request->sale_id, fn ($q, $id) => $q->where('sale_id', $id))
            ->latest('id')
            ->paginate($this->perPage($request, 25));

        return SaleReturnResource::collection($returns);
    }

    /** Create a return/refund against a completed sale. */
    public function store(StoreSaleReturnRequest $request, Sale $sale): JsonResponse
    {
        $return = $this->returns->create($sale, $request->validated(), $request->user());

        return (new SaleReturnResource($return))->response()->setStatusCode(201);
    }

    public function show(SaleReturn $saleReturn): SaleReturnResource
    {
        return new SaleReturnResource($saleReturn->load('items'));
    }
}
