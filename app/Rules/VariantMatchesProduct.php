<?php

namespace App\Rules;

use App\Models\ProductVariant;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Asserts that a `variant_id` belongs to the `product_id` submitted alongside it.
 *
 * Without this, `exists:product_variants,id` scoped only by tenant lets a request
 * name product A and a variant of product B: the sale/purchase/transfer line is
 * snapshotted against A while the stock ledger moves B's variant balance, silently
 * corrupting stock for two products at once.
 *
 * Works on both flat payloads (`variant_id` next to `product_id`) and line items
 * (`items.*.variant_id` next to `items.*.product_id`) — the sibling is resolved
 * from the attribute currently under validation.
 */
class VariantMatchesProduct implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public function __construct(protected string $productField = 'product_id') {}

    /** @param  array<string, mixed>  $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $prefix = str_contains($attribute, '.') ? Str::beforeLast($attribute, '.').'.' : '';
        $productId = Arr::get($this->data, $prefix.$this->productField);

        if ($productId === null || $productId === '') {
            return; // The product itself is missing — its own `required` rule reports that.
        }

        $matches = ProductVariant::query()
            ->whereKey($value)
            ->where('product_id', $productId)
            ->exists();

        if (! $matches) {
            $fail('The selected variant does not belong to the selected product.');
        }
    }
}
