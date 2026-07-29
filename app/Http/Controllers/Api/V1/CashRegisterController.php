<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CashRegisterSessionResource;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Services\Accounting\CashRegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashRegisterController extends Controller
{
    public function __construct(protected CashRegisterService $service) {}

    public function index(Request $request): JsonResponse
    {
        $registers = CashRegister::query()
            ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
            ->with('sessions')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'branch_id' => $r->branch_id,
                'is_active' => (bool) $r->is_active,
                'has_open_session' => $r->sessions->firstWhere('status', 'open') !== null,
            ]);

        return response()->json(['data' => $registers]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $register = CashRegister::create($data);

        return response()->json(['data' => $register->only('id', 'name', 'branch_id')], 201);
    }

    public function open(Request $request, CashRegister $cashRegister): CashRegisterSessionResource
    {
        $data = $request->validate(['opening_cash' => ['required', 'numeric', 'min:0']]);

        $session = $this->service->open($cashRegister, (float) $data['opening_cash'], $request->user());

        return new CashRegisterSessionResource($session);
    }

    public function currentSession(CashRegister $cashRegister): JsonResponse
    {
        $session = $cashRegister->openSession();

        return response()->json(['data' => $session ? new CashRegisterSessionResource($session) : null]);
    }

    public function movement(Request $request, CashRegisterSession $session): CashRegisterSessionResource
    {
        $data = $request->validate([
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $session = $this->service->movement($session, $data['type'], (float) $data['amount'], $data['reason'] ?? null, $request->user());

        return new CashRegisterSessionResource($session);
    }

    public function close(Request $request, CashRegisterSession $session): CashRegisterSessionResource
    {
        $data = $request->validate([
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $session = $this->service->close($session, (float) $data['actual_cash'], $data['note'] ?? null, $request->user());

        return new CashRegisterSessionResource($session->load('transactions'));
    }

    public function show(CashRegisterSession $session): CashRegisterSessionResource
    {
        return new CashRegisterSessionResource($session->load('transactions'));
    }

    /** Streams the cash register slip inline by default; `?download=1` forces a download. */
    public function slip(Request $request, CashRegisterSession $session)
    {
        $pdf = $this->service->sessionSlipPdf($session);
        $filename = "cash-slip-{$session->id}.pdf";

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
