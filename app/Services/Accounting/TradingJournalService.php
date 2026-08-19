<?php

namespace App\Services\Accounting;

use App\Domain\Enums\PurchaseStatus;
use App\Models\Account;
use App\Models\Business;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleReturn;

class TradingJournalService
{
    public function __construct(
        protected JournalService $journal,
    ) {}

    public function postSale(Sale $sale): void
    {
        $business = Business::find($sale->business_id);
        if (! $business || ! $business->auto_post_journals) {
            return;
        }

        $sale->loadMissing('payments', 'items.taxes');

        $lines = [];

        // What the till actually keeps: change handed back is not revenue, and a
        // sale tendered 100 for a 90 total would otherwise debit cash by 100 and
        // never balance.
        $change = (float) $sale->change_amount;

        foreach ($sale->payments as $payment) {
            $amount = round((float) $payment->amount, 2);
            if ($amount <= 0) {
                continue;
            }

            // Change always comes out of the cash drawer, so it nets off the cash leg.
            if ($change > 0) {
                $applied = min($change, $amount);
                $amount = round($amount - $applied, 2);
                $change = round($change - $applied, 2);
            }

            if ($amount <= 0) {
                continue;
            }

            $accountId = $this->resolvePaymentAccount($payment->method, $sale->tenant_id);
            if (! $accountId) {
                return;
            }
            $lines[] = [
                'account_id' => $accountId,
                'type' => 'debit',
                'amount' => $amount,
            ];
        }

        $due = round((float) $sale->due_amount, 2);
        if ($due > 0) {
            $arId = $this->resolveAccount('Accounts Receivable', $sale->tenant_id);
            if (! $arId) {
                return;
            }
            $lines[] = [
                'account_id' => $arId,
                'type' => 'debit',
                'amount' => $due,
            ];
        }

        // Discounts are contra-revenue: revenue is credited gross and the giveaway
        // is debited, so the books show both what was sold and what was given away
        // rather than a single netted figure nobody can audit.
        $discounts = round((float) $sale->discount_amount + (float) $sale->coupon_discount, 2);
        if ($discounts > 0) {
            $salesReturnsId = $this->resolveAccount('Sales Returns', $sale->tenant_id);
            if (! $salesReturnsId) {
                return;
            }
            $lines[] = [
                'account_id' => $salesReturnsId,
                'type' => 'debit',
                'amount' => $discounts,
            ];
        }

        $salesRevenueId = $this->resolveAccount('Sales Revenue', $sale->tenant_id);
        if (! $salesRevenueId) {
            return;
        }
        $lines[] = [
            'account_id' => $salesRevenueId,
            'type' => 'credit',
            'amount' => round((float) $sale->subtotal + (float) $sale->shipping_amount, 2),
        ];

        if ((float) $sale->tax_amount > 0) {
            $taxPayableId = $this->resolveAccount('Tax Payable', $sale->tenant_id);
            if (! $taxPayableId) {
                return;
            }
            $lines[] = [
                'account_id' => $taxPayableId,
                'type' => 'credit',
                'amount' => round((float) $sale->tax_amount, 2),
            ];
        }

        $this->journal->post([
            'user_id' => $sale->user_id,
            'reference' => 'SALE-'.$sale->invoice_number,
            'description' => "Sale #{$sale->invoice_number}",
            'source_type' => Sale::class,
            'source_id' => $sale->id,
            'lines' => $lines,
        ]);
    }

    public function postPurchase(Purchase $purchase): void
    {
        $business = Business::find($purchase->business_id);
        if (! $business || ! $business->auto_post_journals) {
            return;
        }

        // Goods can arrive in several deliveries, and `receive()` runs once per
        // delivery. The stock value only settles when the last one lands, so the
        // purchase is booked then — once, whatever the delivery count.
        if ($purchase->status !== PurchaseStatus::Received) {
            return;
        }

        $alreadyPosted = JournalEntry::where('source_type', Purchase::class)
            ->where('source_id', $purchase->id)
            ->exists();

        if ($alreadyPosted) {
            return;
        }

        $inventoryId = $this->resolveAccount('Inventory', $purchase->tenant_id);
        if (! $inventoryId) {
            return;
        }

        $lines = [];

        $lines[] = [
            'account_id' => $inventoryId,
            'type' => 'debit',
            'amount' => round((float) $purchase->subtotal, 2),
        ];

        if ((float) $purchase->tax_amount > 0) {
            $lines[] = [
                'account_id' => $inventoryId,
                'type' => 'debit',
                'amount' => round((float) $purchase->tax_amount, 2),
            ];
        }

        $totalPaid = (float) $purchase->paid_amount;
        $due = (float) $purchase->due_amount;

        if ($totalPaid > 0) {
            $cashId = $this->resolveAccount('Cash', $purchase->tenant_id);
            if (! $cashId) {
                return;
            }
            $lines[] = [
                'account_id' => $cashId,
                'type' => 'credit',
                'amount' => $totalPaid,
            ];
        }

        if ($due > 0) {
            $apId = $this->resolveAccount('Accounts Payable', $purchase->tenant_id);
            if (! $apId) {
                return;
            }
            $lines[] = [
                'account_id' => $apId,
                'type' => 'credit',
                'amount' => $due,
            ];
        }

        $this->journal->post([
            'user_id' => $purchase->user_id,
            'reference' => 'PUR-'.$purchase->reference_number,
            'description' => "Purchase #{$purchase->reference_number}",
            'source_type' => Purchase::class,
            'source_id' => $purchase->id,
            'lines' => $lines,
        ]);
    }

    public function postExpense(Expense $expense): void
    {
        $business = Business::where('tenant_id', $expense->tenant_id)->first();
        if (! $business || ! $business->auto_post_journals) {
            return;
        }

        $expenseAccount = $this->resolveExpenseAccount($expense);
        $paymentAccount = $this->resolvePaymentAccount($expense->payment_method, $expense->tenant_id);

        if (! $expenseAccount || ! $paymentAccount) {
            return;
        }

        $lines = [];

        $lines[] = [
            'account_id' => $expenseAccount,
            'type' => 'debit',
            'amount' => round((float) $expense->amount, 2),
        ];

        $lines[] = [
            'account_id' => $paymentAccount,
            'type' => 'credit',
            'amount' => round((float) $expense->amount, 2),
        ];

        $this->journal->post([
            'user_id' => $expense->user_id,
            'reference' => $expense->reference,
            'description' => $expense->note ?? "Expense #{$expense->reference}",
            'source_type' => Expense::class,
            'source_id' => $expense->id,
            'lines' => $lines,
        ]);
    }

    public function postSaleReturn(SaleReturn $return): void
    {
        $sale = Sale::find($return->sale_id);
        if (! $sale) {
            return;
        }

        $business = Business::find($sale->business_id);
        if (! $business || ! $business->auto_post_journals) {
            return;
        }

        $salesReturnsId = $this->resolveAccount('Sales Returns', $return->tenant_id);
        if (! $salesReturnsId) {
            return;
        }

        $lines = [];

        $lines[] = [
            'account_id' => $salesReturnsId,
            'type' => 'debit',
            'amount' => round((float) $return->subtotal, 2),
        ];

        if ((float) $return->tax_amount > 0) {
            $taxPayableId = $this->resolveAccount('Tax Payable', $return->tenant_id);
            if (! $taxPayableId) {
                return;
            }
            $lines[] = [
                'account_id' => $taxPayableId,
                'type' => 'debit',
                'amount' => round((float) $return->tax_amount, 2),
            ];
        }

        $refundMethod = $return->refund_method;
        if ($refundMethod && $refundMethod->movesCash()) {
            $cashId = $this->resolveAccount('Cash', $return->tenant_id);
            if (! $cashId) {
                return;
            }
            $lines[] = [
                'account_id' => $cashId,
                'type' => 'credit',
                'amount' => round((float) $return->total, 2),
            ];
        } else {
            $arId = $this->resolveAccount('Accounts Receivable', $return->tenant_id);
            if (! $arId) {
                return;
            }
            $lines[] = [
                'account_id' => $arId,
                'type' => 'credit',
                'amount' => round((float) $return->total, 2),
            ];
        }

        $this->journal->post([
            'user_id' => $return->user_id,
            'reference' => $return->reference_number,
            'description' => "Return for Sale #{$sale->invoice_number}",
            'source_type' => SaleReturn::class,
            'source_id' => $return->id,
            'lines' => $lines,
        ]);
    }

    public function postCashMovement(string $type, float $amount, string $method, string $tenantId, string $userId): void
    {
        $lines = [];

        if ($type === 'in') {
            $accountId = $this->resolvePaymentAccount($method, $tenantId);
            $cashId = $this->resolveAccount('Cash', $tenantId);
        } else {
            $cashId = $this->resolveAccount('Cash', $tenantId);
            $accountId = $this->resolvePaymentAccount($method, $tenantId);
        }

        if (! $accountId || ! $cashId) {
            return;
        }

        if ($type === 'in') {
            $lines[] = [
                'account_id' => $accountId,
                'type' => 'debit',
                'amount' => round($amount, 2),
            ];
            $lines[] = [
                'account_id' => $cashId,
                'type' => 'credit',
                'amount' => round($amount, 2),
            ];
        } else {
            $lines[] = [
                'account_id' => $cashId,
                'type' => 'debit',
                'amount' => round($amount, 2),
            ];
            $lines[] = [
                'account_id' => $accountId,
                'type' => 'credit',
                'amount' => round($amount, 2),
            ];
        }

        $this->journal->post([
            'user_id' => $userId,
            'reference' => 'CASH-'.strtoupper(uniqid()),
            'description' => 'Cash movement: '.$type,
            'lines' => $lines,
        ]);
    }

    private function resolveAccount(string $name, string $tenantId): ?string
    {
        $account = Account::where('tenant_id', $tenantId)
            ->where('name', $name)
            ->first();

        return $account?->id;
    }

    private function resolvePaymentAccount(mixed $method, string $tenantId): ?string
    {
        $value = is_string($method) ? $method : $method->value;

        $methodName = match ($value) {
            'cash' => 'Cash',
            'card', 'bank' => 'Cash',
            'mobile_banking' => 'Cash',
            'wallet' => 'Cash',
            default => 'Cash',
        };

        return $this->resolveAccount($methodName, $tenantId);
    }

    private function resolveExpenseAccount(Expense $expense): ?string
    {
        $category = $expense->category;

        if (! $category) {
            return $this->resolveAccount('Operating Expenses', $expense->tenant_id);
        }

        $mapping = [
            'Rent' => 'Rent Expense',
            'Utilities' => 'Utilities Expense',
            'Salaries' => 'Salaries Expense',
            'Marketing' => 'Marketing Expense',
        ];

        $accountName = $mapping[$category->name] ?? 'Operating Expenses';

        return $this->resolveAccount($accountName, $expense->tenant_id)
            ?? $this->resolveAccount('Operating Expenses', $expense->tenant_id);
    }
}
