<?php

namespace App\Domain\Tax;

use App\Domain\Enums\RoundingMode;
use App\Domain\Enums\TaxRoundingScope;
use App\Support\Money;

/**
 * Decides where the fractions land.
 *
 * Rounding each line separately and rounding the invoice once give different
 * answers on a large basket, and different authorities mandate different ones —
 * NBR rounds per line, Indian GST reconciles per tax component, EU VAT permits a
 * single invoice-level rounding. Getting this wrong is not cosmetic: it is the
 * difference between a return that reconciles to the ledger and one that does
 * not.
 *
 * Whatever the scope, the output always satisfies: per-line components sum to
 * their component total, and component totals sum to the tax total. Where a
 * rounded total has to be pushed back down to the lines that produced it, the
 * remainder is distributed with {@see Money::allocate()} so no minor unit is
 * created or lost.
 */
class TaxRounder
{
    /** @param  array<int, LineTax>  $lines  unrounded, keyed by line index */
    public function round(
        array $lines,
        TaxRoundingScope $scope,
        RoundingMode $mode,
        string $currency,
    ): TaxBreakdown {
        $rounded = match ($scope) {
            TaxRoundingScope::Line => $this->perLine($lines, $mode, $currency),
            TaxRoundingScope::Component => $this->perComponent($lines, $mode, $currency),
            TaxRoundingScope::Invoice => $this->perInvoice($lines, $mode, $currency),
        };

        return new TaxBreakdown(
            lines: $rounded,
            componentTotals: $this->totals($rounded, $currency),
            taxTotal: Money::sum(array_map(static fn (RoundedLineTax $l) => $l->tax, $rounded), $currency),
        );
    }

    /**
     * @param  array<int, LineTax>  $lines
     * @return array<int, RoundedLineTax>
     */
    private function perLine(array $lines, RoundingMode $mode, string $currency): array
    {
        $result = [];

        foreach ($lines as $index => $line) {
            $components = array_map(
                fn (TaxComponentAmount $c) => $this->component($c, $this->money($c->amount, $mode, $currency), $currency),
                $line->components,
            );

            $result[$index] = new RoundedLineTax(
                net: $this->money($line->net, $mode, $currency),
                tax: Money::sum(array_map(static fn (RoundedTaxComponent $c) => $c->amount, $components), $currency),
                components: $components,
            );
        }

        return $result;
    }

    /**
     * Round each component's invoice-wide total once, then push it back across
     * the lines in proportion to their unrounded share.
     *
     * @param  array<int, LineTax>  $lines
     * @return array<int, RoundedLineTax>
     */
    private function perComponent(array $lines, RoundingMode $mode, string $currency): array
    {
        // component key => [line index => unrounded amount]
        $byComponent = [];

        foreach ($lines as $index => $line) {
            foreach ($line->components as $position => $component) {
                $byComponent[$component->key()][$index] = $component->amount;
            }
        }

        $allocated = [];

        foreach ($byComponent as $key => $shares) {
            $total = $this->money(array_sum($shares), $mode, $currency);

            $allocated[$key] = array_sum($shares) == 0.0
                ? array_map(static fn () => Money::zero($currency), $shares)
                : $total->allocate($shares);
        }

        return $this->rebuild($lines, $mode, $currency, static function (TaxComponentAmount $c, int $index) use ($allocated, $currency): Money {
            return $allocated[$c->key()][$index] ?? Money::zero($currency);
        });
    }

    /**
     * Round the invoice's tax once, then distribute it across every component of
     * every line in proportion to their unrounded amounts.
     *
     * @param  array<int, LineTax>  $lines
     * @return array<int, RoundedLineTax>
     */
    private function perInvoice(array $lines, RoundingMode $mode, string $currency): array
    {
        $weights = [];

        foreach ($lines as $index => $line) {
            foreach ($line->components as $position => $component) {
                $weights["{$index}:{$position}"] = $component->amount;
            }
        }

        $grandTotal = $this->money(array_sum($weights), $mode, $currency);

        $shares = ($weights === [] || array_sum($weights) == 0.0)
            ? array_map(static fn () => Money::zero($currency), $weights)
            : $grandTotal->allocate($weights);

        $result = [];

        foreach ($lines as $index => $line) {
            $components = [];

            foreach ($line->components as $position => $component) {
                $components[] = $this->component($component, $shares["{$index}:{$position}"], $currency);
            }

            $result[$index] = new RoundedLineTax(
                net: $this->money($line->net, $mode, $currency),
                tax: Money::sum(array_map(static fn (RoundedTaxComponent $c) => $c->amount, $components), $currency),
                components: $components,
            );
        }

        return $result;
    }

    /**
     * @param  array<int, LineTax>  $lines
     * @param  callable(TaxComponentAmount, int): Money  $amountFor
     * @return array<int, RoundedLineTax>
     */
    private function rebuild(array $lines, RoundingMode $mode, string $currency, callable $amountFor): array
    {
        $result = [];

        foreach ($lines as $index => $line) {
            $components = array_map(
                fn (TaxComponentAmount $c) => $this->component($c, $amountFor($c, $index), $currency),
                $line->components,
            );

            $result[$index] = new RoundedLineTax(
                net: $this->money($line->net, $mode, $currency),
                tax: Money::sum(array_map(static fn (RoundedTaxComponent $c) => $c->amount, $components), $currency),
                components: $components,
            );
        }

        return $result;
    }

    private function component(TaxComponentAmount $source, Money $amount, string $currency): RoundedTaxComponent
    {
        return new RoundedTaxComponent(
            rateId: $source->rateId,
            name: $source->name,
            rate: $source->rate,
            type: $source->type,
            isCompound: $source->isCompound,
            baseAmount: Money::of($source->baseAmount, $currency),
            amount: $amount,
        );
    }

    /**
     * @param  array<int, RoundedLineTax>  $lines
     * @return array<int, TaxComponentTotal>
     */
    private function totals(array $lines, string $currency): array
    {
        /** @var array<string, TaxComponentTotal> $totals */
        $totals = [];

        foreach ($lines as $line) {
            foreach ($line->components as $component) {
                $key = ($component->rateId !== null ? "id:{$component->rateId}" : "name:{$component->name}").":{$component->rate}";

                $totals[$key] = isset($totals[$key])
                    ? new TaxComponentTotal(
                        $component->rateId,
                        $component->name,
                        $component->rate,
                        $totals[$key]->baseAmount->plus($component->baseAmount),
                        $totals[$key]->amount->plus($component->amount),
                    )
                    : new TaxComponentTotal(
                        $component->rateId,
                        $component->name,
                        $component->rate,
                        $component->baseAmount,
                        $component->amount,
                    );
            }
        }

        return array_values($totals);
    }

    private function money(float $amount, RoundingMode $mode, string $currency): Money
    {
        return Money::ofMinor($mode->round($amount * Money::SCALE), $currency);
    }
}
