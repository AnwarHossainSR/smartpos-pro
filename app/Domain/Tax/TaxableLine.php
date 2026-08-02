<?php

namespace App\Domain\Tax;

use App\Models\Product;

/**
 * What the engine needs to know about one line: the amount to tax (already
 * discounted), how many units it covers, and the product whose tax group
 * applies. Keeps the engine independent of whether it is taxing a sale, a
 * purchase or a quotation.
 */
final readonly class TaxableLine
{
    public function __construct(
        /** Discounted line value — net when prices are exclusive, gross when inclusive. */
        public float $amount,
        public float $quantity,
        public ?Product $product = null,
    ) {}
}
