<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ClockInRequest;
use App\Http\Requests\Hr\ClockOutRequest;
use App\Http\Requests\Hr\IndexShiftRequest;
use App\Http\Requests\Hr\ShiftReportRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use App\Services\Hr\ShiftService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Employee time clock. Clocking in/out always acts on the *authenticated* user —
 * there is no "clock somebody else in" endpoint, so a compromised manager account
 * cannot forge another employee's timesheet.
 */
class ShiftController extends Controller
{
    use ApiResponse;

    public function __construct(protected ShiftService $service) {}

    /** The caller's own open shift, or `null` when they are clocked out. */
    public function me(): JsonResponse
    {
        $shift = $this->service->currentShift(request()->user());

        return response()->json(['data' => $shift ? new ShiftResource($shift) : null]);
    }

    public function clockIn(ClockInRequest $request): JsonResponse
    {
        $shift = $this->service->clockIn(
            $request->user(),
            $request->validated('branch_id'),
            $request->validated('note'),
        );

        return (new ShiftResource($shift->load('branch')))->response()->setStatusCode(201);
    }

    public function clockOut(ClockOutRequest $request): ShiftResource
    {
        return new ShiftResource(
            $this->service->clockOut($request->user(), $request->validated('note')),
        );
    }

    public function index(IndexShiftRequest $request): AnonymousResourceCollection
    {
        $userId = $request->validated('user_id');

        // Without `users.view` the endpoint only ever answers for the caller, whether
        // or not they passed their own id.
        if (! $request->canSeeEveryone()) {
            $userId = $request->user()->id;
        }

        $shifts = Shift::query()
            ->with(['user:id,name', 'branch:id,name'])
            ->when($userId, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->validated('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->date('from'), fn ($q, $from) => $q->where('clock_in', '>=', $from->startOfDay()))
            ->when($request->date('to'), fn ($q, $to) => $q->where('clock_in', '<=', $to->endOfDay()))
            ->latest('clock_in')
            ->paginate((int) ($request->validated('per_page') ?? 25));

        return ShiftResource::collection($shifts);
    }

    /** Hours per user per day/week, with anything over 8h a day flagged as overtime. */
    public function report(ShiftReportRequest $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        $userId = $request->validated('user_id');

        if (! $request->canSeeEveryone()) {
            $userId = $request->user()->id;
        }

        return response()->json([
            'data' => $this->service->hoursReport(
                $from,
                $to,
                $request->validated('group_by') ?? 'day',
                $userId,
                $request->validated('branch_id'),
            ),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function range(ShiftReportRequest $request): array
    {
        $from = $request->date('from')?->startOfDay() ?? now()->startOfWeek();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();

        if ($from->diffInDays($to) > ShiftReportRequest::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'from' => ['The reporting range may not exceed '.ShiftReportRequest::MAX_RANGE_DAYS.' days.'],
            ]);
        }

        return [$from, $to];
    }
}
