<?php

namespace App\Services\Purchasing;

use App\Models\Account;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierPaymentService
{
    public function __construct(protected JournalService $journal) {}

    public function recordPayment(Supplier $supplier, float $amount, string $method, ?string $reference = null, ?string $paidAt = null): SupplierPayment
    {
        return DB::transaction(function () use ($supplier, $amount, $method, $reference) {
            $supplier->load('tenant');

            $supplier->balance = (float) $supplier->balance - $amount;
            $supplier->save();

            $payment = $supplier->payments()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $supplier->tenant_id,
                'supplier_id' => $supplier->id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'paid_at' => Carbon::now()->startOfDay(),
                'notes' => null,
            ]);

            $business = $supplier->tenant->businesses->first();
            if ($business && $business->auto_post_journals) {
                $cashAccount = Account::where('tenant_id', $supplier->tenant_id)
                    ->where('name', 'Cash')
                    ->first();

                $apAccount = Account::where('tenant_id', $supplier->tenant_id)
                    ->where('name', 'Accounts Payable')
                    ->first();

                $lines = [];

                if ($cashAccount) {
                    $lines[] = [
                        'account_id' => $cashAccount->id,
                        'type' => 'credit',
                        'amount' => $amount,
                    ];
                }

                if ($apAccount) {
                    $lines[] = [
                        'account_id' => $apAccount->id,
                        'type' => 'debit',
                        'amount' => $amount,
                    ];
                }

                if (! empty($lines)) {
                    $this->journal->post([
                        'user_id' => null,
                        'reference' => 'AP-'.strtoupper(uniqid()),
                        'description' => "Supplier payment to {$supplier->name}",
                        'source_type' => SupplierPayment::class,
                        'source_id' => $payment->id,
                        'lines' => $lines,
                    ]);
                }
            }

            return $payment;
        });
    }

    public function listPayments(Supplier $supplier, array $filters = []): Collection
    {
        $query = $supplier->payments()->latest('paid_at');

        if (! empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $query->whereBetween('paid_at', [
                $filters['start_date'],
                $filters['end_date'],
            ]);
        }

        return $query->get()->map(function ($payment) {
            return [
                'id' => $payment->id,
                'supplier_name' => $payment->supplier->name,
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'paid_at' => $payment->paid_at?->format('Y-m-d'),
                'reference' => $payment->reference,
            ];
        });
    }

    public function statement(Supplier $supplier): Collection
    {
        $business = $supplier->tenant;
        $tenantId = $business?->tenant_id ?? $supplier->tenant_id;

        $purchases = $supplier->purchases()
            ->whereNotNull('due_amount')
            ->where('due_amount', '>', 0)
            ->get();

        $payments = $supplier->payments()
            ->whereNotNull('paid_at')
            ->latest('paid_at')
            ->get();

        // Calculate total outstanding from purchases
        $totalDue = 0;
        foreach ($purchases as $purchase) {
            $totalDue += (float) $purchase->due_amount;
        }

        // Calculate total payments received
        $totalPaid = 0;
        foreach ($payments as $payment) {
            $totalPaid += (float) $payment->amount;
        }

        // Build entries: purchases (as invoices) and payments, all sorted chronologically
        $entries = [];

        // First, add all purchase entries with their due amounts
        foreach ($purchases as $purchase) {
            $entries[] = [
                'date' => $purchase->purchased_at?->format('Y-m-d') ?? null,
                'reference' => 'PUR-'.substr($purchase->reference_number, -8),
                'type' => 'invoice',
                'amount' => (float) $purchase->due_amount,
                'total_due' => $totalDue,
                'total_paid' => $totalPaid,
            ];
        }

        // Add all payment entries
        foreach ($payments as $payment) {
            $entries[] = [
                'date' => $payment->paid_at?->format('Y-m-d'),
                'reference' => $payment->reference ?? 'Payment',
                'type' => 'payment',
                'amount' => (float) $payment->amount,
                'total_due' => $totalDue,
                'total_paid' => $totalPaid,
            ];
        }

        // Sort chronologically (oldest first)
        usort($entries, fn ($a, $b) => ($a['date'] ?? '') <=> ($b['date'] ?? ''));

        // Calculate running balance for each entry
        $runningBalance = $totalDue;
        foreach ($entries as &$entry) {
            if ($entry['type'] === 'payment') {
                $runningBalance -= (float) $entry['amount'];
            }
            $entry['running_balance'] = $runningBalance;
            unset($entry['total_due'], $entry['total_paid']);
        }

        return $this->applyBuckets($entries);
    }

    protected function applyBuckets(array $entries): Collection
    {
        $result = collect();

        foreach ($entries as $entry) {
            $amount = (float) ($entry['amount'] ?? 0);
            $balance = (float) ($entry['running_balance'] ?? 0);

            $bucket = '90+';
            if ($balance >= 0 && $balance <= 30) {
                $bucket = '0-30';
            } elseif ($balance > 30 && $balance <= 60) {
                $bucket = '31-60';
            } elseif ($balance > 60 && $balance <= 90) {
                $bucket = '61-90';
            }

            $result->push([
                'date' => $entry['date'],
                'reference' => $entry['reference'],
                'type' => $entry['type'],
                'amount' => $amount,
                'running_balance' => $balance,
                'bucket' => $bucket,
            ]);
        }

        return $result;
    }
}
