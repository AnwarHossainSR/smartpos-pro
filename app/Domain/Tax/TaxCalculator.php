<?php

namespace App\Domain\Tax;

use App\Domain\Enums\PriceDisplay;
use App\Domain\Enums\TaxType;
use App\Models\TaxRate;

/**
 * Turns a line amount and an ordered set of tax components into exact,
 * unrounded per-component figures.
 *
 * Pure: no database, no config, no rounding policy. Everything that varies by
 * jurisdiction is passed in, which is what makes the awkward cases — compound
 * stacking, tax-inclusive pricing, per-unit fixed duties — testable in isolation
 * rather than only observable through a completed sale.
 */
class TaxCalculator
{
    /**
     * @param  float  $amount  the line's discounted value: net when prices are
     *                         quoted exclusive, gross when inclusive
     * @param  float  $quantity  units on the line — a fixed duty is charged per unit
     * @param  iterable<TaxRate>  $rates  in application order; compound components
     *                                    must follow the ones they compound on
     */
    public function forLine(float $amount, float $quantity, iterable $rates, PriceDisplay $display): LineTax
    {
        $rates = is_array($rates) ? $rates : iterator_to_array($rates);

        if ($rates === []) {
            return LineTax::untaxed(round($amount, 2));
        }

        $net = $display->isInclusive()
            ? $this->netFromGross($amount, $quantity, $rates)
            : $amount;

        $components = $this->componentsFor($net, $quantity, $rates);
        $tax = array_sum(array_map(static fn (TaxComponentAmount $c) => $c->amount, $components));

        return new LineTax(net: $net, gross: $net + $tax, components: $components);
    }

    /**
     * Apply each component in order against the net line.
     *
     * A compound component taxes the running total (net plus everything charged
     * before it), not the bare net — that is the whole point of compounding, and
     * why the pivot's `priority` is load-bearing.
     *
     * @param  array<int, TaxRate>  $rates
     * @return array<int, TaxComponentAmount>
     */
    private function componentsFor(float $net, float $quantity, array $rates): array
    {
        $running = $net;
        $components = [];

        foreach ($rates as $rate) {
            $type = $rate->type;
            $value = (float) $rate->rate;
            $isCompound = (bool) $rate->is_compound;

            if ($type === TaxType::Fixed) {
                // A per-unit duty is independent of price, so it has no percentage base.
                $base = $net;
                $amount = $value * $quantity;
            } else {
                $base = $isCompound ? $running : $net;
                $amount = $base * ($value / 100);
            }

            $components[] = new TaxComponentAmount(
                rateId: $rate->id,
                name: $rate->name,
                rate: $value,
                type: $type,
                isCompound: $isCompound,
                baseAmount: $base,
                amount: $amount,
            );

            $running += $amount;
        }

        return $components;
    }

    /**
     * Recover the net from a tax-inclusive price.
     *
     * Fixed per-unit duties are a flat part of the gross, so they come off first.
     * What remains relates to the net by a single multiplier, which is derived by
     * running the percentage components over a notional net of 1 — that handles
     * compound stacking without needing a closed-form formula per configuration.
     *
     * @param  array<int, TaxRate>  $rates
     */
    private function netFromGross(float $gross, float $quantity, array $rates): float
    {
        $fixedTotal = 0.0;
        $percentageRates = [];

        foreach ($rates as $rate) {
            if ($rate->type === TaxType::Fixed) {
                $fixedTotal += (float) $rate->rate * $quantity;
            } else {
                $percentageRates[] = $rate;
            }
        }

        $remaining = $gross - $fixedTotal;

        if ($percentageRates === []) {
            return $remaining;
        }

        $multiplier = 1.0;
        $running = 1.0;

        foreach ($percentageRates as $rate) {
            $base = $rate->is_compound ? $running : 1.0;
            $running += $base * ((float) $rate->rate / 100);
        }

        $multiplier = $running;

        // A configuration whose components cancel to zero would divide by zero;
        // treat it as fully net rather than blowing up a checkout.
        return $multiplier > 0 ? $remaining / $multiplier : $remaining;
    }
}
