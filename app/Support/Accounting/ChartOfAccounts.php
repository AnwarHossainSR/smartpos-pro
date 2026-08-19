<?php

namespace App\Support\Accounting;

/**
 * The default chart of accounts every tenant starts with.
 *
 * `TradingJournalService` posts against accounts looked up by name, and returns
 * silently when one is missing — so a tenant provisioned without this chart has
 * automatic bookkeeping switched on and quietly recording nothing. It is created
 * at registration for that reason, not as a convenience.
 */
final class ChartOfAccounts
{
    /** @return array<int, array{account_number: string, name: string, type: string}> */
    public static function defaults(): array
    {
        return [
            ['account_number' => '1000', 'name' => 'Cash', 'type' => 'cash'],
            ['account_number' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['account_number' => '1200', 'name' => 'Inventory', 'type' => 'asset'],
            ['account_number' => '1500', 'name' => 'Fixed Assets', 'type' => 'asset'],
            ['account_number' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['account_number' => '2100', 'name' => 'Tax Payable', 'type' => 'liability'],
            ['account_number' => '3000', 'name' => "Owner's Equity", 'type' => 'equity'],
            ['account_number' => '4000', 'name' => 'Sales Revenue', 'type' => 'income'],
            ['account_number' => '4100', 'name' => 'Sales Returns', 'type' => 'income'],
            ['account_number' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense'],
            ['account_number' => '6000', 'name' => 'Operating Expenses', 'type' => 'expense'],
            ['account_number' => '6100', 'name' => 'Rent Expense', 'type' => 'expense'],
            ['account_number' => '6200', 'name' => 'Utilities Expense', 'type' => 'expense'],
            ['account_number' => '6300', 'name' => 'Salaries Expense', 'type' => 'expense'],
            ['account_number' => '6400', 'name' => 'Marketing Expense', 'type' => 'expense'],
        ];
    }
}
