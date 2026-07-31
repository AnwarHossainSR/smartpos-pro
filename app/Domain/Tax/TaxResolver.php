<?php

namespace App\Domain\Tax;

use App\Domain\Enums\TaxSourcing;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\TaxGroup;
use App\Models\TaxZone;

/**
 * Works out *which* tax applies, before anything is calculated.
 *
 * Two independent questions:
 *   - Which jurisdiction? Decided by the profile's sourcing rule — origin
 *     (the shop's address, ordinary counter retail) or destination (the
 *     customer's, for delivered goods and cross-border supply).
 *   - Which group for this product? Most specific wins: the product's own
 *     group, else its category's, else the zone's default. A product with no
 *     group anywhere is untaxed, which is a real answer, not a fallback.
 */
class TaxResolver
{
    public function contextFor(Business $business, ?Branch $branch = null, ?Customer $customer = null): TaxContext
    {
        $profile = $business->effectiveTaxProfile();

        $zone = $this->zoneFor($profile->sourcing, $branch, $customer);

        return new TaxContext(
            profile: $profile,
            // The tenant's own setting wins over the profile default: a business
            // may legitimately quote net prices in a VAT-inclusive market.
            priceDisplay: $business->price_display ?? $profile->price_display,
            currency: $business->currency ?? config('pos.currency'),
            customerExempt: (bool) ($customer?->tax_exempt ?? false),
            zoneGroup: $zone?->taxGroup,
        );
    }

    /**
     * The group that applies to this product, or null when nothing taxes it.
     *
     * `relationLoaded` guards keep this from firing a query per line on a large
     * basket; callers eager-load `taxGroup.rates` and `category.taxGroup.rates`.
     */
    public function groupFor(Product $product, TaxContext $context): ?TaxGroup
    {
        if (! $context->taxable()) {
            return null;
        }

        if ($product->tax_group_id !== null && $product->taxGroup) {
            return $product->taxGroup;
        }

        if ($product->category?->taxGroup) {
            return $product->category->taxGroup;
        }

        return $context->zoneGroup;
    }

    /**
     * The most specific active zone matching the sourcing party's address.
     * Specificity beats configuration order: a postcode rule outranks a state
     * rule outranks a country rule, and `priority` only breaks ties.
     */
    private function zoneFor(TaxSourcing $sourcing, ?Branch $branch, ?Customer $customer): ?TaxZone
    {
        $party = $sourcing === TaxSourcing::Destination ? ($customer ?? $branch) : ($branch ?? $customer);

        $zones = TaxZone::query()->where('is_active', true)->with('taxGroup.rates')->get();

        if ($zones->isEmpty()) {
            return null;
        }

        $best = null;
        $bestScore = -1;

        foreach ($zones as $zone) {
            $score = $zone->matchScore(
                $party?->country_code,
                $party?->state,
                $party?->postcode,
            );

            if ($score === null) {
                continue;
            }

            if ($score > $bestScore || ($score === $bestScore && $zone->priority > ($best?->priority ?? 0))) {
                $best = $zone;
                $bestScore = $score;
            }
        }

        return $best ?? $zones->firstWhere('is_default', true);
    }
}
