<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly int $warehouseId,
        public readonly float $available,
        public readonly float $requested,
    ) {
        parent::__construct(sprintf(
            'Insufficient stock for product #%d in warehouse #%d: %.4f available, %.4f requested.',
            $productId, $warehouseId, $available, $requested
        ));
    }
}
