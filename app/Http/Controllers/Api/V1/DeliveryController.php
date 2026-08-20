<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\AssignDeliveryRequest;
use App\Http\Requests\Sales\UpdateDeliveryStatusRequest;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Models\Sale;
use App\Services\Delivery\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryController extends Controller
{
    public function __construct(protected DeliveryService $service) {}

    public function assign(AssignDeliveryRequest $request, Sale $sale): JsonResponse
    {
        $sale = $this->service->assignDelivery($sale, (int) $request->validated('zone_id'));

        return (new DeliveryResource($sale->delivery))->response()->setStatusCode(201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $deliveries = Delivery::query()
            ->with(['sale', 'zone'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('zone_id'), fn ($q, $zoneId) => $q->where('zone_id', $zoneId))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return DeliveryResource::collection($deliveries);
    }

    public function show(Delivery $delivery): DeliveryResource
    {
        return new DeliveryResource($delivery->load('sale', 'zone'));
    }

    public function updateStatus(UpdateDeliveryStatusRequest $request, Delivery $delivery): DeliveryResource
    {
        return new DeliveryResource(
            $this->service->updateStatus($delivery, $request->validated('status')),
        );
    }
}
