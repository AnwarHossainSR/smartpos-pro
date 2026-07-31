<?php

namespace App\Support;

use App\Domain\Enums\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An exact money amount, held as a whole number of minor units (paisa, cents).
 *
 * Every money path in this app used to do `round($a * $b, 2)` on floats. That is
 * safe for one multiplication and unsafe for a chain of them: 0.1 + 0.2 is not
 * 0.3 in binary floating point, and a basket of 40 lines with a compound tax and
 * an invoice-level discount accumulates enough drift to book a sale one paisa
 * short and leave a phantom due balance behind. Working in integers removes the
 * question entirely — rounding happens once, where we say it happens.
 *
 * Immutable: every operation returns a new instance.
 *
 * @implements JsonSerializable
 */
final readonly class Money implements JsonSerializable, Stringable
{
    /** Minor units per major unit. Two decimal places, matching the `decimal(*,2)` columns. */
    public const SCALE = 100;

    private function __construct(public int $minor, public string $currency) {}

    /** Build from a major-unit amount (12.34), rounding half-up to the nearest minor unit. */
    public static function of(float|int|string $amount, ?string $currency = null): self
    {
        return new self(
            RoundingMode::HalfUp->round((float) $amount * self::SCALE),
            self::normaliseCurrency($currency),
        );
    }

    /** Build from a count of minor units (1234 == 12.34). */
    public static function ofMinor(int $minor, ?string $currency = null): self
    {
        return new self($minor, self::normaliseCurrency($currency));
    }

    public static function zero(?string $currency = null): self
    {
        return new self(0, self::normaliseCurrency($currency));
    }

    /** Sum of a list, defaulting to zero for an empty list. */
    public static function sum(iterable $amounts, ?string $currency = null): self
    {
        $total = self::zero($currency);

        foreach ($amounts as $amount) {
            $total = $total->plus($amount);
        }

        return $total;
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    /** Scale by a plain number (a quantity, a tax rate, a percentage factor). */
    public function multipliedBy(float|int $factor, RoundingMode $rounding = RoundingMode::HalfUp): self
    {
        return new self($rounding->round($this->minor * $factor), $this->currency);
    }

    public function dividedBy(float|int $divisor, RoundingMode $rounding = RoundingMode::HalfUp): self
    {
        if ((float) $divisor === 0.0) {
            throw new InvalidArgumentException('Cannot divide a money amount by zero.');
        }

        return new self($rounding->round($this->minor / $divisor), $this->currency);
    }

    /** A percentage of this amount — `percentage(15.0)` of 100.00 is 15.00. */
    public function percentage(float $percent, RoundingMode $rounding = RoundingMode::HalfUp): self
    {
        return $this->multipliedBy($percent / 100, $rounding);
    }

    /**
     * Split this amount across the given weights without losing or inventing a
     * single minor unit — the remainder is handed out one unit at a time to the
     * largest fractional parts. This is what makes an invoice-level discount
     * distributable over lines while the lines still sum back to the invoice.
     *
     * @param  array<int|string, float>  $weights
     * @return array<int|string, self>
     */
    public function allocate(array $weights): array
    {
        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            throw new InvalidArgumentException('Cannot allocate money across zero total weight.');
        }

        $shares = [];
        $remainders = [];
        $allocated = 0;

        foreach ($weights as $key => $weight) {
            $exact = $this->minor * ($weight / $totalWeight);
            $whole = (int) ($exact >= 0 ? floor($exact) : ceil($exact));

            $shares[$key] = $whole;
            $remainders[$key] = abs($exact - $whole);
            $allocated += $whole;
        }

        // Hand the leftover units to whichever shares were cut hardest.
        arsort($remainders);
        $leftover = $this->minor - $allocated;
        $step = $leftover >= 0 ? 1 : -1;

        foreach (array_keys($remainders) as $key) {
            if ($leftover === 0) {
                break;
            }

            $shares[$key] += $step;
            $leftover -= $step;
        }

        return array_map(fn (int $minor) => new self($minor, $this->currency), $shares);
    }

    /** Clamp at zero — a total, a due balance or a change amount is never negative. */
    public function atLeastZero(): self
    {
        return $this->minor < 0 ? self::zero($this->currency) : $this;
    }

    public function negated(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function absolute(): self
    {
        return new self(abs($this->minor), $this->currency);
    }

    public function min(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->minor <= $other->minor ? $this : $other;
    }

    public function max(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->minor >= $other->minor ? $this : $other;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor > $other->minor;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor < $other->minor;
    }

    /** Major units as a float — use only at the persistence and presentation edges. */
    public function toFloat(): float
    {
        return $this->minor / self::SCALE;
    }

    /** Major units as a fixed-point string, safe to hand straight to a decimal column. */
    public function toDecimalString(): string
    {
        return number_format($this->minor / self::SCALE, 2, '.', '');
    }

    public function jsonSerialize(): float
    {
        return $this->toFloat();
    }

    public function __toString(): string
    {
        return $this->toDecimalString();
    }

    /** Locale-aware currency formatting for server-rendered documents (invoices, slips). */
    public static function format(float $amount, string $currency): string
    {
        $formatter = new \NumberFormatter('en', \NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($amount, $currency);
    }

    public function formatted(): string
    {
        return self::format($this->toFloat(), $this->currency);
    }

    private static function normaliseCurrency(?string $currency): string
    {
        return strtoupper($currency ?? (string) config('pos.currency', 'USD'));
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot combine {$this->currency} with {$other->currency}."
            );
        }
    }
}
