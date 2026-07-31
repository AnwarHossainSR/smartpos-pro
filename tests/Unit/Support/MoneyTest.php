<?php

namespace Tests\Unit\Support;

use App\Domain\Enums\RoundingMode;
use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_converts_between_major_and_minor_units(): void
    {
        $this->assertSame(1234, Money::of(12.34, 'USD')->minor);
        $this->assertSame(12.34, Money::ofMinor(1234, 'USD')->toFloat());
        $this->assertSame('12.34', Money::ofMinor(1234, 'USD')->toDecimalString());
    }

    public function test_it_rounds_half_up_when_constructed_from_a_float(): void
    {
        $this->assertSame(1235, Money::of(12.345, 'USD')->minor);
        $this->assertSame(-1235, Money::of(-12.345, 'USD')->minor);
    }

    /**
     * The float path this class exists to replace: 0.1 + 0.2 !== 0.3 in binary
     * floating point, so summing prices as floats drifts. Minor units cannot.
     */
    public function test_repeated_addition_does_not_drift(): void
    {
        $total = Money::zero('USD');

        for ($i = 0; $i < 10; $i++) {
            $total = $total->plus(Money::of(0.1, 'USD'));
        }

        $this->assertSame(100, $total->minor);
        $this->assertTrue($total->equals(Money::of(1.00, 'USD')));
    }

    public function test_arithmetic_operations(): void
    {
        $ten = Money::of(10, 'USD');
        $three = Money::of(3, 'USD');

        $this->assertSame(1300, $ten->plus($three)->minor);
        $this->assertSame(700, $ten->minus($three)->minor);
        $this->assertSame(3000, $ten->multipliedBy(3)->minor);
        $this->assertSame(333, $ten->dividedBy(3)->minor);
        $this->assertSame(150, $ten->percentage(15)->minor);
        $this->assertSame(1000, $ten->negated()->absolute()->minor);
    }

    public function test_it_refuses_to_mix_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of(10, 'USD')->plus(Money::of(10, 'BDT'));
    }

    public function test_it_refuses_to_divide_by_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of(10, 'USD')->dividedBy(0);
    }

    /**
     * The property that matters: an allocation always sums back to the original.
     * Distributing a 10.00 invoice discount over three equal lines cannot produce
     * 3.33 × 3 = 9.99 and quietly lose a cent off the invoice total.
     */
    public function test_allocation_never_loses_or_invents_a_minor_unit(): void
    {
        $shares = Money::of(10, 'USD')->allocate([1, 1, 1]);

        $this->assertSame([334, 333, 333], array_map(fn (Money $m) => $m->minor, $shares));
        $this->assertSame(1000, Money::sum($shares, 'USD')->minor);
    }

    public function test_allocation_respects_weights(): void
    {
        $shares = Money::of(100, 'USD')->allocate(['a' => 70, 'b' => 30]);

        $this->assertSame(7000, $shares['a']->minor);
        $this->assertSame(3000, $shares['b']->minor);
    }

    public function test_allocation_of_a_negative_amount_sums_back(): void
    {
        $shares = Money::of(-10, 'USD')->allocate([1, 1, 1]);

        $this->assertSame(-1000, Money::sum($shares, 'USD')->minor);
    }

    public function test_it_rejects_allocation_across_zero_weight(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of(10, 'USD')->allocate([0, 0]);
    }

    public function test_comparisons_and_clamping(): void
    {
        $ten = Money::of(10, 'USD');
        $five = Money::of(5, 'USD');

        $this->assertTrue($ten->greaterThan($five));
        $this->assertTrue($five->lessThan($ten));
        $this->assertTrue($ten->max($five)->equals($ten));
        $this->assertTrue($ten->min($five)->equals($five));
        $this->assertTrue(Money::of(-5, 'USD')->atLeastZero()->isZero());
        $this->assertTrue($ten->isPositive());
        $this->assertTrue(Money::of(-1, 'USD')->isNegative());
    }

    public function test_sum_of_an_empty_list_is_zero(): void
    {
        $this->assertTrue(Money::sum([], 'USD')->isZero());
    }

    public function test_it_serialises_to_a_plain_number_for_json(): void
    {
        $this->assertSame('{"amount":12.34}', json_encode(['amount' => Money::of(12.34, 'USD')]));
    }

    #[DataProvider('roundingCases')]
    public function test_rounding_modes(RoundingMode $mode, float $value, int $expected): void
    {
        $this->assertSame($expected, $mode->round($value));
    }

    /** @return array<string, array{RoundingMode, float, int}> */
    public static function roundingCases(): array
    {
        return [
            'half up rounds .5 away from zero' => [RoundingMode::HalfUp, 2.5, 3],
            'half down rounds .5 toward zero' => [RoundingMode::HalfDown, 2.5, 2],
            'half even rounds .5 to the even neighbour' => [RoundingMode::HalfEven, 2.5, 2],
            'half even rounds 3.5 up to 4' => [RoundingMode::HalfEven, 3.5, 4],
            'up always rounds away from zero' => [RoundingMode::Up, 2.1, 3],
            'up on a negative rounds away from zero' => [RoundingMode::Up, -2.1, -3],
            'down always rounds toward zero' => [RoundingMode::Down, 2.9, 2],
            'down on a negative rounds toward zero' => [RoundingMode::Down, -2.9, -2],
        ];
    }
}
