<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum PaymentMethod: string
{
    use HasValues;

    case Cash = 'cash';
    case Card = 'card';
    case Bank = 'bank';
    case MobileBanking = 'mobile_banking';
    case Wallet = 'wallet';
    case Due = 'due';

    /** Only cash tenders move the drawer, so only these reconcile against a register session. */
    public function affectsCashDrawer(): bool
    {
        return $this === self::Cash;
    }

    /** The chart-of-accounts type money of this tender lands in. */
    public function accountType(): AccountType
    {
        return match ($this) {
            self::Cash, self::Due => AccountType::Cash,
            self::Card, self::Bank => AccountType::Bank,
            self::MobileBanking => AccountType::MobileBanking,
            self::Wallet => AccountType::Wallet,
        };
    }
}
