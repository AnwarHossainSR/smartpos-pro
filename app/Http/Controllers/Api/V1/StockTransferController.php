<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ReceiveStockTransferRequest;
use App\Http\Requests\Inventory\StoreStockTransferRequest;
use App\Models\StockTransfer;
use App\Services\Inventory\StockTransferService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    use ApiResponse;

    public function __construct(protected StockTransferService $service) {}

    public function index(Request $request): JsonResponse
    {
        $transfers = QueryPipeline::for(
            StockTransfer::with(['fromWarehouse', 'toWarehouse', 'requestedBy']),
            $request,
        )
            ->search(['reference_number'])
            ->allow(
                AllowedFilter::enum('status', TransferStatus::class),
                AllowedFilter::exact('from_warehouse_id'),
                AllowedFilter::exact('to_warehouse_id'),
            )
            ->sortable(['id', 'requested_at'], default: '-id')
            ->paginate(20);

        return $this->success($transfers);
    }

    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $transfer = $this->service->request($request->validated(), $request->user());

        return $this->created($transfer);
    }

    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        return $this->success($stockTransfer->load(['items.product', 'fromWarehouse', 'toWarehouse', 'requestedBy', 'approvedBy', 'receivedBy']));
    }

    public function approve(StockTransfer $stockTransfer, Request $request): JsonResponse
    {
        $transfer = $this->service->approve($stockTransfer, $request->user());

        return $this->success($transfer);
    }

    public function receive(StockTransfer $stockTransfer, ReceiveStockTransferRequest $request): JsonResponse
    {
        $transfer = $this->service->receive($stockTransfer, $request->validated(), $request->user());

        return $this->success($transfer);
    }

    public function cancel(StockTransfer $stockTransfer, Request $request): JsonResponse
    {
        $transfer = $this->service->cancel($stockTransfer, $request->user());

        return $this->success($transfer);
    }
}
