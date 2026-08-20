<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Models\Sale;
use App\Services\Delivery\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    protected $service;

    public function __construct(DeliveryService $service)
    {
        $this->service = $service;
    }

    public function assign(Request $request, Sale $sale): JsonResponse
    {
        $zoneId = $request->input('zone_id');

        $sale = $this->service->assignDelivery($sale, $zoneId);

        return new DeliveryResource($sale->delivery);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Delivery::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        $deliveries = $query->with(['sale', 'zone'])->latest('id')->paginate(20);

        return new DeliveryResourceCollection($deliveries);
    }

    public function show(Request $request, Delivery $delivery): JsonResponse
    {
        return new DeliveryResource($delivery->load('sale', 'zone'));
    }

    public function updateStatus(Request $request, int $deliveryId): JsonResponse
    {
        $delivery = $this->service->updateStatus($deliveryId, $request->input('status'));

        return new DeliveryResource($delivery);
    }
}
