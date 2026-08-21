<?php

namespace Database\Seeders;

use App\Domain\Enums\PriceDisplay;
use App\Domain\Enums\RoundingMode;
use App\Domain\Enums\TaxRoundingScope;
use App\Domain\Enums\TaxSourcing;
use App\Models\TaxProfile;

/**
 * The country tax profiles a tenant can select.
 *
 * These encode the rules that actually differ between jurisdictions — where tax
 * is sourced from, at what granularity it rounds, whether shelf prices include
 * it, and what an invoice must carry to be valid. They are not tenant data:
 * every tenant trading in Bangladesh is subject to the same NBR rules.
 *
 * Idempotent, so it is safe to re-run when a profile is corrected.
 */
class TaxProfileSeeder extends TransactionalSeeder
{
    protected function seed(): void
    {
        foreach ($this->profiles() as $profile) {
            TaxProfile::updateOrCreate(['code' => $profile['code']], $profile);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function profiles(): array
    {
        return [
            [
                'code' => 'GENERIC',
                'name' => 'Generic (no country rules)',
                'country_code' => null,
                'sourcing' => TaxSourcing::Origin,
                'rounding_scope' => TaxRoundingScope::Line,
                'rounding_mode' => RoundingMode::HalfUp,
                'price_display' => PriceDisplay::Exclusive,
                'tax_number_label' => 'Tax number',
                'tax_number_pattern' => null,
                'required_invoice_fields' => [],
            ],
            [
                'code' => 'BD_VAT',
                'name' => 'Bangladesh — VAT (NBR)',
                'country_code' => 'BD',
                // Over-the-counter retail: the shop's own rate applies.
                'sourcing' => TaxSourcing::Origin,
                // NBR Mushak documents round per line.
                'rounding_scope' => TaxRoundingScope::Line,
                'rounding_mode' => RoundingMode::HalfUp,
                // Bangladeshi retail quotes VAT-inclusive shelf prices.
                'price_display' => PriceDisplay::Inclusive,
                'tax_number_label' => 'BIN',
                'tax_number_pattern' => '^[0-9]{9,17}$',
                'required_invoice_fields' => ['seller_bin', 'buyer_name', 'buyer_address'],
            ],
            [
                'code' => 'IN_GST',
                'name' => 'India — GST',
                'country_code' => 'IN',
                // Intra- vs inter-state supply decides CGST+SGST against IGST,
                // which is a destination question.
                'sourcing' => TaxSourcing::Destination,
                // GST returns reconcile per tax component across the invoice.
                'rounding_scope' => TaxRoundingScope::Component,
                'rounding_mode' => RoundingMode::HalfUp,
                'price_display' => PriceDisplay::Inclusive,
                'tax_number_label' => 'GSTIN',
                'tax_number_pattern' => '^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$',
                'required_invoice_fields' => ['seller_gstin', 'buyer_gstin', 'place_of_supply', 'hsn_code'],
            ],
            [
                'code' => 'EU_VAT',
                'name' => 'European Union — VAT',
                'country_code' => null,
                // B2C distance selling is destination-taxed under the OSS regime.
                'sourcing' => TaxSourcing::Destination,
                // EU rules permit rounding the invoice tax total once.
                'rounding_scope' => TaxRoundingScope::Invoice,
                'rounding_mode' => RoundingMode::HalfUp,
                'price_display' => PriceDisplay::Inclusive,
                'tax_number_label' => 'VAT number',
                'tax_number_pattern' => '^[A-Z]{2}[0-9A-Z]{2,13}$',
                'required_invoice_fields' => ['seller_vat_number', 'buyer_vat_number', 'supply_date', 'sequential_number'],
            ],
            [
                'code' => 'US_SALES_TAX',
                'name' => 'United States — sales tax',
                'country_code' => 'US',
                // Most states are destination-sourced for remote sales.
                'sourcing' => TaxSourcing::Destination,
                'rounding_scope' => TaxRoundingScope::Invoice,
                'rounding_mode' => RoundingMode::HalfUp,
                // US prices are quoted net; tax is added at the register.
                'price_display' => PriceDisplay::Exclusive,
                'tax_number_label' => 'Seller permit no.',
                'tax_number_pattern' => null,
                'required_invoice_fields' => [],
            ],
        ];
    }
}
