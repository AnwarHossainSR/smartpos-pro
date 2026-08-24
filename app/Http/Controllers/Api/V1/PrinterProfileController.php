<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PrinterProfile;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrinterProfileController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $profiles = PrinterProfile::query()
            ->when($request->business_id, fn ($q, $id) => $q->where('business_id', $id))
            ->get();

        return $this->success($profiles);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'connection_type' => ['required', Rule::in(['usb', 'network', 'serial'])],
            'ip_address' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'paper_width' => ['required', Rule::in([58, 80])],
            'auto_cut' => ['boolean'],
            'open_drawer' => ['boolean'],
        ]);

        $data['business_id'] = $request->user()->business_id;
        $data['auto_cut'] = $data['auto_cut'] ?? true;
        $data['open_drawer'] = $data['open_drawer'] ?? false;

        $profile = PrinterProfile::create($data);

        return $this->created($profile);
    }

    public function update(Request $request, PrinterProfile $printerProfile): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'connection_type' => ['sometimes', Rule::in(['usb', 'network', 'serial'])],
            'ip_address' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'paper_width' => ['sometimes', Rule::in([58, 80])],
            'auto_cut' => ['boolean'],
            'open_drawer' => ['boolean'],
        ]);

        $printerProfile->update($data);

        return $this->success($printerProfile);
    }

    public function destroy(PrinterProfile $printerProfile): JsonResponse
    {
        $printerProfile->delete();

        return $this->noContent();
    }
}
