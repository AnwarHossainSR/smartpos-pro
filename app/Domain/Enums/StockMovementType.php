<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum StockMovementType: string
{
    use HasValues;

    case Sale = 'sale';
    case Purchase = 'purchase';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case SaleReturn = 'sale_return';
    case PurchaseReturn = 'purchase_return';
    case Opening = 'opening';

    /** The direction this movement type always implies. */
    public function direction(): StockDirection
    {
        return match ($this) {
            self::Purchase, self::AdjustmentIn, self::TransferIn, self::SaleReturn, self::Opening => StockDirection::In,
            self::Sale, self::AdjustmentOut, self::TransferOut, self::PurchaseReturn => StockDirection::Out,
        };
    }

    /** Manual corrections are the ones worth auditing and reconciling against a stocktake. */
    public function isManualAdjustment(): bool
    {
        return in_array($this, [self::AdjustmentIn, self::AdjustmentOut], true);
    }
}
