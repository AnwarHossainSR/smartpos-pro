<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Pos\ApprovalPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateApprovalSettingsRequest;
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

    public function retention(Request $request): JsonResponse
    {
        $business = Business::findOrFail($request->user()->business_id);

        return response()->json([
            'data' => [
                'retention_days' => $business->audit_retention_days ?? 180,
            ],
        ]);
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
            'base_currency' => ['sometimes', 'string', 'size:3'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'logo_path' => ['nullable', 'string', 'max:2048'],
            'settings' => ['nullable', 'array'],
        ]);

        $baseCurrency = $data['base_currency'] ?? null;
        unset($data['base_currency']);

        $business->update($data);

        if ($baseCurrency) {
            $business->tenant->update(['base_currency' => $baseCurrency]);
        }

        return response()->json(['data' => $this->present($business->fresh())]);
    }

    /**
     * The till's approval thresholds. Kept behind their own endpoint rather than
     * inside the free-form `settings` blob so a partial save from any other
     * settings screen cannot silently wipe the limits.
     */
    public function approvals(Request $request): JsonResponse
    {
        $business = Business::findOrFail($request->user()->business_id);

        return response()->json(['data' => ApprovalPolicy::fromBusiness($business)->toArray()]);
    }

    public function updateApprovals(UpdateApprovalSettingsRequest $request): JsonResponse
    {
        $business = Business::findOrFail($request->user()->business_id);
        $policy = ApprovalPolicy::fromArray($request->validated());

        $business->update([
            'settings' => array_merge($business->settings ?? [], [ApprovalPolicy::KEY => $policy->toArray()]),
        ]);

        return response()->json(['data' => $policy->toArray()]);
    }

    public function completeOnboarding(Request $request): JsonResponse
    {
        $business = Business::findOrFail($request->user()->business_id);
        $business->update([
            'onboarding_completed' => true,
            'onboarding_state' => array_merge($business->onboarding_state ?? [], ['dismissed' => true]),
        ]);

        return response()->json(['data' => ['onboarding_completed' => true]]);
    }

    public function recordOnboardingStep(Request $request): JsonResponse
    {
        $data = $request->validate([
            'step' => ['required', 'string', 'in:business_profile,first_product,tax_setup,register_sale'],
            'completed' => ['required', 'boolean'],
        ]);

        $business = Business::findOrFail($request->user()->business_id);

        $state = $business->onboarding_state ?? [];
        $state[$data['step']] = $data['completed'];

        $stepOrder = ['business_profile', 'first_product', 'tax_setup', 'register_sale'];
        $completedIndex = -1;
        foreach ($stepOrder as $i => $s) {
            if (! empty($state[$s])) {
                $completedIndex = $i;
            }
        }
        $state['current_step'] = $completedIndex + 1;

        $business->update(['onboarding_state' => $state]);

        return response()->json(['data' => ['onboarding_state' => $state]]);
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
            'onboarding_state' => $business->onboarding_state ?? null,
        ];
    }
}
