<?php

namespace App\Services\Accounting;

use App\Domain\Enums\AccountType;
use App\Domain\Enums\JournalLineType;
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
    /**
     * Which side of the ledger increases an account now lives on {@see AccountType},
     * so the posting rule and anything reporting a debit/credit split (the trial balance,
     * the API's account resource) read it from one place.
     *
     * @param  array{reference?:string, entry_date?:string, description?:string, user_id?:string|null,
     *   lines: array<int, array{account_id:string, type:JournalLineType|string, amount:float, description?:string|null}>}  $data
     */
    public function post(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data) {
            // Callers may pass either the enum or its wire value; normalise once up front.
            $lines = array_map(function (array $line): array {
                $line['type'] = $line['type'] instanceof JournalLineType
                    ? $line['type']
                    : JournalLineType::from($line['type']);

                return $line;
            }, $data['lines']);

            $debit = round((float) collect($lines)->where('type', JournalLineType::Debit)->sum('amount'), 2);
            $credit = round((float) collect($lines)->where('type', JournalLineType::Credit)->sum('amount'), 2);

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
                $increasesOnDebit = $account->type->isDebitNormal();
                $delta = ($line['type'] === JournalLineType::Debit) === $increasesOnDebit ? $amount : -$amount;
                $account->increment('balance', $delta);
            }

            return $entry->load('lines');
        });
    }
}
