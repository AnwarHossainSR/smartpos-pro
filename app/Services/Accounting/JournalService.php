<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Posts balanced double-entry journal entries. Total debits must equal total
 * credits; each line moves an account's balance according to its normal side
 * (debit-normal: assets & expenses; credit-normal: liabilities, income, equity).
 */
class JournalService
{
    private const DEBIT_NORMAL = ['cash', 'bank', 'mobile_banking', 'wallet', 'asset', 'expense'];

    /**
     * @param  array{reference?:string, entry_date?:string, description?:string, user_id?:int|null,
     *   lines: array<int, array{account_id:int, type:'debit'|'credit', amount:float, description?:string|null}>}  $data
     */
    public function post(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data) {
            $lines = $data['lines'];

            $debit = round((float) collect($lines)->where('type', 'debit')->sum('amount'), 2);
            $credit = round((float) collect($lines)->where('type', 'credit')->sum('amount'), 2);

            if ($debit <= 0 || $debit !== $credit) {
                throw ValidationException::withMessages([
                    'lines' => ["Journal is not balanced: debits {$debit} must equal credits {$credit}."],
                ]);
            }

            $accounts = Account::whereIn('id', collect($lines)->pluck('account_id'))
                ->lockForUpdate()->get()->keyBy('id');

            foreach ($lines as $line) {
                if (! $accounts->has($line['account_id'])) {
                    throw ValidationException::withMessages([
                        'lines' => ["Account #{$line['account_id']} does not exist in this tenant."],
                    ]);
                }
            }

            $entry = JournalEntry::create([
                'user_id' => $data['user_id'] ?? null,
                'reference' => $data['reference'] ?? 'JE-'.strtoupper(uniqid()),
                'entry_date' => $data['entry_date'] ?? Carbon::now()->toDateString(),
                'description' => $data['description'] ?? null,
                'total_debit' => $debit,
                'total_credit' => $credit,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
            ]);

            foreach ($lines as $line) {
                $amount = round((float) $line['amount'], 2);
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'type' => $line['type'],
                    'amount' => $amount,
                    'description' => $line['description'] ?? null,
                ]);

                /** @var Account $account */
                $account = $accounts->get($line['account_id']);
                $increasesOnDebit = in_array($account->type, self::DEBIT_NORMAL, true);
                $delta = ($line['type'] === 'debit') === $increasesOnDebit ? $amount : -$amount;
                $account->increment('balance', $delta);
            }

            return $entry->load('lines');
        });
    }
}
