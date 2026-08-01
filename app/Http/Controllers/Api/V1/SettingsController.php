<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaxProfileResource;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function showBusiness(Request $request): JsonResponse
    {
        $business = Business::findOrFail($request->user()->business_id);

        return response()->json(['data' => $this->present($business)]);
    }

    public function updateBusiness(Request $request): JsonResponse
    {
        $business = Business::findOrFail($request->user()->business_id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'tax_profile_id' => ['nullable', 'exists:tax_profiles,id'],
            'price_display' => ['sometimes', 'in:inclusive,exclusive'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'logo_path' => ['nullable', 'string', 'max:2048'],
            'settings' => ['nullable', 'array'],
        ]);

        $business->update($data);

        return response()->json(['data' => $this->present($business->fresh())]);
    }

    public function completeOnboarding(Request $request): JsonResponse
    {
        $business = Business::findOrFail($request->user()->business_id);
        $business->update(['onboarding_completed' => true]);

        return response()->json(['data' => ['onboarding_completed' => true]]);
    }

    protected function present(Business $business): array
    {
        return [
            'id' => $business->id,
            'name' => $business->name,
            'type' => $business->type,
            'legal_name' => $business->legal_name,
            'email' => $business->email,
            'phone' => $business->phone,
            'tax_number' => $business->tax_number,
            'tax_profile_id' => $business->tax_profile_id,
            'tax_profile' => $business->taxProfile ? new TaxProfileResource($business->taxProfile) : null,
            'price_display' => $business->price_display,
            'currency' => $business->currency,
            'timezone' => $business->timezone,
            'logo_path' => $business->logo_path,
            'settings' => $business->settings ?? [],
            'onboarding_completed' => (bool) $business->onboarding_completed,
        ];
    }
}
