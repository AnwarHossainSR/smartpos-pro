<?php

namespace App\Domain\Tax;

use App\Models\Product;
use App\Models\TaxGroup;

/**
 * The one entry point every money path uses to tax a document.
 *
 * Resolution, calculation and rounding are separate collaborators so each can be
 * tested on its own, but no caller should have to know that: sales, returns,
 * purchases and quotations all hand this a set of lines and get back a
 * {@see TaxBreakdown} whose figures already reconcile.
 */
class TaxEngine
{
    public function __construct(
        protected TaxResolver $resolver,
        protected TaxCalculator $calculator,
        protected TaxRounder $rounder,
    ) {}

    /**
     * @param  array<int, TaxableLine>  $lines
     */
    public function taxDocument(array $lines, TaxContext $context): TaxBreakdown
    {
        $computed = [];

        foreach ($lines as $index => $line) {
            $group = $line->product !== null
                ? $this->resolver->groupFor($line->product, $context)
                : null;

            $rates = $group?->orderedRates() ?? collect();

            $computed[$index] = $rates->isEmpty()
                ? LineTax::untaxed($line->amount)
                : $this->calculator->forLine($line->amount, $line->quantity, $rates->all(), $context->priceDisplay);
        }

        return $this->rounder->round(
            $computed,
            $context->roundingScope(),
            $context->roundingMode(),
            $context->currency,
        );
    }

    /**
     * Re-tax a single amount under an explicit set of components — used by
     * returns, which must reverse the tax that was *originally charged* rather
     * than recompute it at today's rates.
     *
     * @param  array<int, TaxComponentAmount>  $original
     */
    public function proportionOf(array $original, float $ratio, TaxContext $context): array
    {
        return array_map(
            static fn (TaxComponentAmount $c) => $c->withAmount($c->amount * $ratio),
            $original,
        );
    }

    /** Relations the engine reads per line; eager-load these to avoid N+1 on a large basket. */
    public const PRODUCT_RELATIONS = ['taxGroup.rates', 'category.taxGroup.rates'];

    public function resolver(): TaxResolver
    {
        return $this->resolver;
    }

    /** Convenience for callers holding only a product collection. */
    public function groupFor(Product $product, TaxContext $context): ?TaxGroup
    {
        return $this->resolver->groupFor($product, $context);
    }
}
