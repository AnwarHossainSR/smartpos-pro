<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $productId,
        public readonly string $warehouseId,
        public readonly float $available,
        public readonly float $requested,
    ) {
        parent::__construct(sprintf(
            'Insufficient stock for product #%s in warehouse #%s: %.4f available, %.4f requested.',
            $productId, $warehouseId, $available, $requested
        ));
    }
}
