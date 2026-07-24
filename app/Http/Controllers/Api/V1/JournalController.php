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
            ->when($request->from, fn ($q, $d) => $q->whereDate('entry_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('entry_date', '<=', $d))
            ->latest('id')
            ->paginate($request->integer('per_page', 25));

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

    /** Per-account ledger: chronological debit/credit lines with a running balance. */
    public function generalLedger(Request $request, Account $account): JsonResponse
    {
        $lines = JournalLine::query()
            ->where('account_id', $account->id)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->when($request->from, fn ($q, $d) => $q->whereDate('journal_entries.entry_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('journal_entries.entry_date', '<=', $d))
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
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
    public function trialBalance(Request $request): JsonResponse
    {
        $rows = Account::query()
            ->orderBy('type')->orderBy('name')
            ->get(['id', 'name', 'type', 'balance'])
            ->map(fn ($a) => [
                'account_id' => $a->id,
                'name' => $a->name,
                'type' => $a->type,
                'balance' => (float) $a->balance,
            ]);

        return response()->json([
            'data' => [
                'accounts' => $rows,
                'total' => round((float) $rows->sum('balance'), 2),
            ],
        ]);
    }
}
