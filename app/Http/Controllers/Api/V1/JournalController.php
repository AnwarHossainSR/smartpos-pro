<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreJournalRequest;
use App\Http\Resources\JournalEntryResource;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\Accounting\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    public function __construct(protected JournalService $journals) {}

    public function index(Request $request)
    {
        $entries = JournalEntry::query()
            ->tap(fn ($q) => $this->filterByDateRange($q, $request, 'entry_date'))
            ->latest('id')
            ->paginate($this->perPage($request, 25));

        return JournalEntryResource::collection($entries);
    }

    public function store(StoreJournalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $entry = $this->journals->post($data);

        return (new JournalEntryResource($entry))->response()->setStatusCode(201);
    }

    public function show(JournalEntry $journal): JournalEntryResource
    {
        return new JournalEntryResource($journal->load('lines'));
    }

    /**
     * Per-account ledger: chronological debit/credit lines.
     *
     * Capped at `limit` rows (oldest first) — an account that has been trading for a
     * year would otherwise return its entire history in one unbounded response.
     * Narrow with `from`/`to` to page through a long ledger.
     */
    public function generalLedger(Request $request, Account $account): JsonResponse
    {
        $lines = JournalLine::query()
            ->where('account_id', $account->id)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->tap(fn ($q) => $this->filterByDateRange($q, $request, 'journal_entries.entry_date'))
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
            ->limit($this->limit($request, 200))
            ->get(['journal_lines.type', 'journal_lines.amount', 'journal_lines.description', 'journal_entries.entry_date', 'journal_entries.reference']);

        return response()->json([
            'data' => [
                'account' => ['id' => $account->id, 'name' => $account->name, 'type' => $account->type, 'balance' => (float) $account->balance],
                'lines' => $lines->map(fn ($l) => [
                    'entry_date' => $l->entry_date,
                    'reference' => $l->reference,
                    'type' => $l->type,
                    'amount' => (float) $l->amount,
                    'description' => $l->description,
                ]),
            ],
        ]);
    }

    /** Trial balance: debit/credit totals per account. */
    /**
     * Trial balance: each account's balance placed in its normal-side column, with the
     * two column totals. A single `balance` figure isn't a trial balance — the whole
     * point of the report is that total debits and total credits agree.
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $rows = Account::query()
            ->orderBy('type')->orderBy('name')
            ->get(['id', 'name', 'type', 'account_number', 'balance'])
            ->map(function ($a) {
                $balance = round((float) $a->balance, 2);
                $isDebit = JournalService::isDebitNormal($a->type);

                return [
                    'account_id' => $a->id,
                    'name' => $a->name,
                    'account_number' => $a->account_number,
                    'type' => $a->type,
                    'normal_side' => $isDebit ? 'debit' : 'credit',
                    'debit' => $isDebit ? $balance : 0.0,
                    'credit' => $isDebit ? 0.0 : $balance,
                ];
            });

        return response()->json([
            'data' => [
                'accounts' => $rows,
                'total_debit' => round((float) $rows->sum('debit'), 2),
                'total_credit' => round((float) $rows->sum('credit'), 2),
            ],
        ]);
    }
}
