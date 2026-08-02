<?php

namespace Tests\Unit\Domain\Tax;

use App\Domain\Enums\PriceDisplay;
use App\Domain\Enums\TaxType;
use App\Domain\Tax\TaxCalculator;
use App\Models\TaxRate;
use Tests\TestCase;

class TaxCalculatorTest extends TestCase
{
    private TaxCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TaxCalculator;
    }

    /** Unsaved models: the calculator is pure and must never touch the database. */
    private function rate(float $rate, bool $compound = false, TaxType $type = TaxType::Percentage, string $name = 'Tax'): TaxRate
    {
        $model = new TaxRate(['name' => $name, 'rate' => $rate, 'type' => $type, 'is_compound' => $compound]);
        $model->id = (int) ($rate * 100) + ($compound ? 1 : 0);

        return $model;
    }

    public function test_no_rates_means_no_tax(): void
    {
        $result = $this->calculator->forLine(100.0, 1, [], PriceDisplay::Exclusive);

        $this->assertSame(100.0, $result->net);
        $this->assertSame(100.0, $result->gross);
        $this->assertSame([], $result->components);
    }

    public function test_a_single_exclusive_rate_is_added_on_top(): void
    {
        $result = $this->calculator->forLine(100.0, 1, [$this->rate(15)], PriceDisplay::Exclusive);

        $this->assertSame(100.0, $result->net);
        $this->assertEqualsWithDelta(15.0, $result->taxTotal(), 0.0001);
        $this->assertEqualsWithDelta(115.0, $result->gross, 0.0001);
    }

    /**
     * The inclusive case is the one naive code gets wrong: 115.00 at 15% is not
     * 115 × 0.15 of tax, it is 15.00 extracted from the 115.
     */
    public function test_a_single_inclusive_rate_is_extracted_from_the_price(): void
    {
        $result = $this->calculator->forLine(115.0, 1, [$this->rate(15)], PriceDisplay::Inclusive);

        $this->assertEqualsWithDelta(100.0, $result->net, 0.0001);
        $this->assertEqualsWithDelta(15.0, $result->taxTotal(), 0.0001);
        $this->assertEqualsWithDelta(115.0, $result->gross, 0.0001);
    }

    public function test_multiple_non_compound_rates_share_the_same_base(): void
    {
        $result = $this->calculator->forLine(
            100.0, 1,
            [$this->rate(9, name: 'CGST'), $this->rate(9, name: 'SGST')],
            PriceDisplay::Exclusive,
        );

        $this->assertCount(2, $result->components);
        // Both charge on 100, not 100 then 109.
        $this->assertEqualsWithDelta(100.0, $result->components[0]->baseAmount, 0.0001);
        $this->assertEqualsWithDelta(100.0, $result->components[1]->baseAmount, 0.0001);
        $this->assertEqualsWithDelta(18.0, $result->taxTotal(), 0.0001);
    }

    /** A compound component taxes the running total, not the bare net. */
    public function test_a_compound_rate_stacks_on_the_one_before_it(): void
    {
        $result = $this->calculator->forLine(
            100.0, 1,
            [$this->rate(5, name: 'GST'), $this->rate(10, compound: true, name: 'PST')],
            PriceDisplay::Exclusive,
        );

        $this->assertEqualsWithDelta(5.0, $result->components[0]->amount, 0.0001);
        // 10% of 105, not of 100.
        $this->assertEqualsWithDelta(105.0, $result->components[1]->baseAmount, 0.0001);
        $this->assertEqualsWithDelta(10.5, $result->components[1]->amount, 0.0001);
        $this->assertEqualsWithDelta(115.5, $result->gross, 0.0001);
    }

    /** The inclusive path must invert compounding, not just divide by the rate sum. */
    public function test_inclusive_pricing_inverts_a_compound_stack(): void
    {
        $rates = [$this->rate(5, name: 'GST'), $this->rate(10, compound: true, name: 'PST')];

        $result = $this->calculator->forLine(115.5, 1, $rates, PriceDisplay::Inclusive);

        $this->assertEqualsWithDelta(100.0, $result->net, 0.0001);
        $this->assertEqualsWithDelta(15.5, $result->taxTotal(), 0.0001);
    }

    /** Round-trip property: taxing the gross of an exclusive line recovers its net. */
    public function test_inclusive_and_exclusive_are_inverses(): void
    {
        $rates = [$this->rate(9, name: 'CGST'), $this->rate(9, name: 'SGST')];

        $exclusive = $this->calculator->forLine(249.99, 3, $rates, PriceDisplay::Exclusive);
        $inclusive = $this->calculator->forLine($exclusive->gross, 3, $rates, PriceDisplay::Inclusive);

        $this->assertEqualsWithDelta(249.99, $inclusive->net, 0.0001);
        $this->assertEqualsWithDelta($exclusive->taxTotal(), $inclusive->taxTotal(), 0.0001);
    }

    /** A fixed duty is per unit, so quantity scales it while price does not. */
    public function test_a_fixed_rate_is_charged_per_unit(): void
    {
        $result = $this->calculator->forLine(
            100.0, 4,
            [$this->rate(2.5, type: TaxType::Fixed, name: 'Eco fee')],
            PriceDisplay::Exclusive,
        );

        $this->assertEqualsWithDelta(10.0, $result->taxTotal(), 0.0001);
        $this->assertEqualsWithDelta(110.0, $result->gross, 0.0001);
    }

    /** Inclusive pricing must strip the flat duty before inverting the percentages. */
    public function test_a_fixed_rate_comes_off_an_inclusive_price_before_percentages(): void
    {
        $rates = [
            $this->rate(2.0, type: TaxType::Fixed, name: 'Deposit'),
            $this->rate(10, name: 'VAT'),
        ];

        // 100 net + 2 deposit = 102, +10% VAT on 100 = 112.
        $result = $this->calculator->forLine(112.0, 1, $rates, PriceDisplay::Inclusive);

        $this->assertEqualsWithDelta(100.0, $result->net, 0.0001);
        $this->assertEqualsWithDelta(12.0, $result->taxTotal(), 0.0001);
    }

    public function test_a_zero_rate_produces_a_component_with_no_amount(): void
    {
        $result = $this->calculator->forLine(100.0, 1, [$this->rate(0, name: 'Zero-rated')], PriceDisplay::Exclusive);

        $this->assertCount(1, $result->components);
        $this->assertEqualsWithDelta(0.0, $result->taxTotal(), 0.0001);
        // Distinct from "untaxed": the invoice still has to show the zero-rated line.
        $this->assertSame('Zero-rated', $result->components[0]->name);
    }
}
