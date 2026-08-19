<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    private array $accounts = [
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

    public function run(): void
    {
        foreach ($this->accounts as $account) {
            Account::updateOrCreate(
                ['tenant_id' => tenant('id'), 'account_number' => $account['account_number']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'balance' => 0,
                    'is_active' => true,
                ]
            );
        }
    }
}
