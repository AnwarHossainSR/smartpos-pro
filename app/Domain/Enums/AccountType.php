<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum AccountType: string
{
    use HasValues;

    case Cash = 'cash';
    case Bank = 'bank';
    case MobileBanking = 'mobile_banking';
    case Wallet = 'wallet';
    case Income = 'income';
    case Expense = 'expense';
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';

    /**
     * Which side of the ledger increases this account's balance.
     *
     * This is the single rule posting applies, so anything reporting a debit/credit
     * split (the trial balance, the account resource) must agree with it.
     */
    public function isDebitNormal(): bool
    {
        return match ($this) {
            self::Cash, self::Bank, self::MobileBanking, self::Wallet, self::Asset, self::Expense => true,
            self::Income, self::Liability, self::Equity => false,
        };
    }

    public function normalSide(): JournalLineType
    {
        return $this->isDebitNormal() ? JournalLineType::Debit : JournalLineType::Credit;
    }
}
