<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PrinterProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrinterProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profiles = PrinterProfile::query()
            ->when($request->business_id, fn ($q, $id) => $q->where('business_id', $id))
            ->get();

        return response()->json(['data' => $profiles]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_id' => ['required', Rule::exists('businesses', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'connection_type' => ['required', Rule::in(['usb', 'network', 'serial'])],
            'ip_address' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'paper_width' => ['required', Rule::in([58, 80])],
            'auto_cut' => ['boolean'],
            'open_drawer' => ['boolean'],
        ]);

        $data['auto_cut'] = $data['auto_cut'] ?? true;
        $data['open_drawer'] = $data['open_drawer'] ?? false;

        $profile = PrinterProfile::create($data);

        return response()->json(['data' => $profile], 201);
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

        return response()->json(['data' => $printerProfile]);
    }

    public function destroy(PrinterProfile $printerProfile): JsonResponse
    {
        $printerProfile->delete();

        return response()->json(null, 204);
    }
}
