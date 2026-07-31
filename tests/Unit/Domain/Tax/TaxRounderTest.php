<?php

namespace Tests\Unit\Domain\Tax;

use App\Domain\Enums\RoundingMode;
use App\Domain\Enums\TaxRoundingScope;
use App\Domain\Enums\TaxType;
use App\Domain\Tax\LineTax;
use App\Domain\Tax\RoundedLineTax;
use App\Domain\Tax\RoundedTaxComponent;
use App\Domain\Tax\TaxComponentAmount;
use App\Domain\Tax\TaxRounder;
use App\Support\Money;
use Tests\TestCase;

class TaxRounderTest extends TestCase
{
    private TaxRounder $rounder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rounder = new TaxRounder;
    }

    private function taxComponent(string $name, float $rate, float $base, float $amount, ?int $id = 1): TaxComponentAmount
    {
        return new TaxComponentAmount($id, $name, $rate, TaxType::Percentage, false, $base, $amount);
    }

    /**
     * Three lines each owing 0.005 of tax. Rounded per line that is 0.01 × 3 =
     * 0.03; rounded once for the invoice it is 0.02. Both are correct — for
     * different jurisdictions — which is exactly why scope is configurable.
     */
    public function test_line_and_invoice_scope_can_legitimately_disagree(): void
    {
        $lines = [
            new LineTax(1.0, 1.005, [$this->taxComponent('VAT', 0.5, 1.0, 0.005)]),
            new LineTax(1.0, 1.005, [$this->taxComponent('VAT', 0.5, 1.0, 0.005)]),
            new LineTax(1.0, 1.005, [$this->taxComponent('VAT', 0.5, 1.0, 0.005)]),
        ];

        $perLine = $this->rounder->round($lines, TaxRoundingScope::Line, RoundingMode::HalfUp, 'USD');
        $perInvoice = $this->rounder->round($lines, TaxRoundingScope::Invoice, RoundingMode::HalfUp, 'USD');

        $this->assertSame(3, $perLine->taxTotal->minor);
        $this->assertSame(2, $perInvoice->taxTotal->minor);
    }

    /** The invariant every scope must hold: lines sum to the stated total. */
    public function test_line_amounts_always_sum_to_the_tax_total(): void
    {
        $lines = [
            new LineTax(33.33, 0, [$this->taxComponent('VAT', 15, 33.33, 4.9995)]),
            new LineTax(33.33, 0, [$this->taxComponent('VAT', 15, 33.33, 4.9995)]),
            new LineTax(33.34, 0, [$this->taxComponent('VAT', 15, 33.34, 5.001)]),
        ];

        foreach (TaxRoundingScope::cases() as $scope) {
            $breakdown = $this->rounder->round($lines, $scope, RoundingMode::HalfUp, 'USD');

            $summed = Money::sum(array_map(static fn (RoundedLineTax $l) => $l->tax, $breakdown->lines), 'USD');

            $this->assertSame(
                $breakdown->taxTotal->minor,
                $summed->minor,
                "lines must sum to the tax total under {$scope->value} scope",
            );
        }
    }

    /** And within a line, its components must sum to the line's own tax. */
    public function test_components_always_sum_to_their_line(): void
    {
        $lines = [
            new LineTax(100.0, 0, [
                $this->taxComponent('CGST', 9, 100.0, 9.0049, 1),
                $this->taxComponent('SGST', 9, 100.0, 9.0049, 2),
            ]),
        ];

        foreach (TaxRoundingScope::cases() as $scope) {
            $breakdown = $this->rounder->round($lines, $scope, RoundingMode::HalfUp, 'USD');
            $line = $breakdown->lineFor(0);

            $summed = Money::sum(array_map(static fn (RoundedTaxComponent $c) => $c->amount, $line->components), 'USD');

            $this->assertSame($line->tax->minor, $summed->minor, "components must sum to the line under {$scope->value}");
        }
    }

    /** Component scope rounds each tax once across the invoice, as Indian GST requires. */
    public function test_component_scope_rounds_each_component_once(): void
    {
        $lines = [
            new LineTax(10.0, 0, [$this->taxComponent('CGST', 9, 10.0, 0.904, 1), $this->taxComponent('SGST', 9, 10.0, 0.904, 2)]),
            new LineTax(10.0, 0, [$this->taxComponent('CGST', 9, 10.0, 0.904, 1), $this->taxComponent('SGST', 9, 10.0, 0.904, 2)]),
        ];

        $breakdown = $this->rounder->round($lines, TaxRoundingScope::Component, RoundingMode::HalfUp, 'USD');

        // 0.904 + 0.904 = 1.808 per component -> 1.81 each, 3.62 in total.
        $this->assertCount(2, $breakdown->componentTotals);
        $this->assertSame(181, $breakdown->componentTotals[0]->amount->minor);
        $this->assertSame(181, $breakdown->componentTotals[1]->amount->minor);
        $this->assertSame(362, $breakdown->taxTotal->minor);
    }

    public function test_component_totals_sum_to_the_tax_total(): void
    {
        $lines = [
            new LineTax(19.99, 0, [$this->taxComponent('CGST', 9, 19.99, 1.7991, 1), $this->taxComponent('SGST', 9, 19.99, 1.7991, 2)]),
            new LineTax(5.55, 0, [$this->taxComponent('CGST', 9, 5.55, 0.4995, 1), $this->taxComponent('SGST', 9, 5.55, 0.4995, 2)]),
        ];

        foreach (TaxRoundingScope::cases() as $scope) {
            $breakdown = $this->rounder->round($lines, $scope, RoundingMode::HalfUp, 'USD');

            $summed = Money::sum(array_map(fn ($t) => $t->amount, $breakdown->componentTotals), 'USD');

            $this->assertSame($breakdown->taxTotal->minor, $summed->minor, "component totals must reconcile under {$scope->value}");
        }
    }

    public function test_a_document_with_no_tax_rounds_to_zero(): void
    {
        $breakdown = $this->rounder->round(
            [LineTax::untaxed(50.0)],
            TaxRoundingScope::Line,
            RoundingMode::HalfUp,
            'USD',
        );

        $this->assertTrue($breakdown->isZero());
        $this->assertSame(5000, $breakdown->lineFor(0)->net->minor);
    }
}
