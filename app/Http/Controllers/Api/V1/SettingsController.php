<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Pos\ApprovalPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateApprovalSettingsRequest;
use App\Http\Resources\TaxProfileResource;
use App\Models\Business;
use App\Services\Media\MediaService;
use App\Support\Media\BusinessMediaSlots;
use App\Support\Theme\ThemeCatalog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    use ApiResponse;

    public function showBusiness(Request $request): JsonResponse
    {
        $business = Business::findOrFail($request->user()->business_id);

        return $this->success($this->present($business));
    }

    public function retention(Request $request): JsonResponse
    {
        $business = Business::findOrFail($request->user()->business_id);

        return $this->success([
            'retention_days' => $business->audit_retention_days ?? 180,
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
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'tax_profile_id' => ['nullable', 'exists:tax_profiles,id'],
            'price_display' => ['sometimes', 'in:inclusive,exclusive'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'base_currency' => ['sometimes', 'string', 'size:3'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'date_format' => ['sometimes', 'string', 'max:20'],
            'auto_post_journals' => ['sometimes', 'boolean'],
             'theme.palette' => ['nullable', 'string', Rule::in(ThemeCatalog::PALETTES)],
             'settings' => ['nullable', 'array'],
        ]);

        $baseCurrency = $data['base_currency'] ?? null;
        unset($data['base_currency']);

        $palette = $data['theme']['palette'] ?? null;
        unset($data['theme']);

        if (! is_null($palette)) {
            $settings = array_merge($business->settings ?? [], $data['settings'] ?? []);
            $settings['theme'] = array_merge($settings['theme'] ?? [], ['palette' => $palette]);
            $data['settings'] = $settings;
        }

        $business->update($data);

        if ($baseCurrency) {
            $business->tenant->update(['base_currency' => $baseCurrency]);
        }

        return $this->success($this->present($business->fresh()));
    }

    /**
     * The till's approval thresholds. Kept behind their own endpoint rather than
     * inside the free-form `settings` blob so a partial save from any other
     * settings screen cannot silently wipe the limits.
     */
    public function approvals(Request $request): JsonResponse
    {
        $business = Business::findOrFail($request->user()->business_id);

        return $this->success(ApprovalPolicy::fromBusiness($business)->toArray());
    }

    public function updateApprovals(UpdateApprovalSettingsRequest $request): JsonResponse
    {
        $business = Business::findOrFail($request->user()->business_id);
        $policy = ApprovalPolicy::fromArray($request->validated());

        $business->update([
            'settings' => array_merge($business->settings ?? [], [ApprovalPolicy::KEY => $policy->toArray()]),
        ]);

        return $this->success($policy->toArray());
    }

    public function completeOnboarding(Request $request): JsonResponse
    {
        $business = Business::findOrFail($request->user()->business_id);
        $business->update([
            'onboarding_completed' => true,
            'onboarding_state' => array_merge($business->onboarding_state ?? [], ['dismissed' => true]),
        ]);

        return $this->success(['onboarding_completed' => true]);
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

        return $this->success(['onboarding_state' => $state]);
    }

    /**
     * Every branding slot, resolved to URLs.
     *
     * Driven off BusinessMediaSlots so the read side and the upload side cannot
     * drift: adding a slot there adds it here.
     *
     * @return array<string, array{path: string|null, url: string|null, urls: array<string, string>|null}>
     */
    protected function media(Business $business): array
    {
        $media = app(MediaService::class);
        $out = [];

        foreach (BusinessMediaSlots::ALL as $slot => $config) {
            $path = $business->{$config['column']};

            $out[$slot] = [
                'path' => $path,
                'url' => $media->url($path),
                'urls' => $media->urls($path, $config['collection']),
            ];
        }

        return $out;
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
            'address' => $business->address,
            'tax_number' => $business->tax_number,
            'tax_profile_id' => $business->tax_profile_id,
            'tax_profile' => $business->taxProfile ? new TaxProfileResource($business->taxProfile) : null,
            'price_display' => $business->price_display,
            'currency' => $business->currency,
            'timezone' => $business->timezone,
            'date_format' => $business->date_format ?? 'Y-m-d',
            'media' => $this->media($business),
            // Kept alongside `media` for the clients that already read it.
            'logo_path' => $business->logo_path,
            'auto_post_journals' => (bool) $business->auto_post_journals,
            'settings' => $business->settings ?? [],
            'onboarding_completed' => (bool) $business->onboarding_completed,
            'onboarding_state' => $business->onboarding_state ?? null,
            /** Tenant-owned colour palette. Defaults to amber server-side so callers never
             *  have to repeat the default; consumers should still treat it as nullable. */
            'theme' => ['palette' => $this->themePalette($business)],
        ];
    }

    /** Resolves the tenant's palette, falling back to the configured default. */
    protected function themePalette(Business $business): string
    {
        $palette = $business->settings['theme']['palette'] ?? null;

        return in_array($palette, ThemeCatalog::PALETTES, true) ? $palette : ThemeCatalog::DEFAULT_PALETTE;
    }
}
