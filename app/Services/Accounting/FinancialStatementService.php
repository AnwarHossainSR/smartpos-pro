<?php

namespace App\Services\Accounting;

use App\Domain\Enums\AccountType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialStatementService
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * These two statements are built with the query builder, not Eloquent, so the
     * TenantScope global scope does not apply — the tenant filter has to be explicit
     * or one business's journal would be aggregated into another's P&L.
     */
    private function tenantId(): ?string
    {
        return $this->tenant->id();
    }

    public function profitLoss(string $from, string $to): array
    {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        $lines = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.entry_date', '>=', $fromDate)
            ->where('journal_entries.entry_date', '<=', $toDate)
            ->where('journal_lines.tenant_id', $this->tenantId())
            ->select(
                'accounts.id as account_id',
                'accounts.name as account_name',
                'accounts.type as account_type',
                'accounts.account_number',
                DB::raw('coalesce(sum(journal_lines.debit), 0) as total_debit'),
                DB::raw('coalesce(sum(journal_lines.credit), 0) as total_credit')
            )
            ->groupBy('accounts.id', 'accounts.name', 'accounts.type', 'accounts.account_number')
            ->get();

        $revenue = $this->buildPnlSection($lines, AccountType::Income, true);
        $cogs = $this->buildPnlSection($lines, AccountType::Expense, false, '5', true);
        $expenses = $this->buildPnlSection($lines, AccountType::Expense, false, null, true);

        $revenueTotal = round($revenue->sum('amount'), 2);
        $cogsTotal = round($cogs->sum('amount'), 2);
        $expensesTotal = round($expenses->sum('amount'), 2);
        $grossProfit = round($revenueTotal - $cogsTotal, 2);
        $netProfit = round($grossProfit - $expensesTotal, 2);

        return [
            'from' => $from,
            'to' => $to,
            'revenue' => $revenue->values()->all(),
            'revenue_total' => $revenueTotal,
            'cogs' => $cogs->values()->all(),
            'cogs_total' => $cogsTotal,
            'gross_profit' => $grossProfit,
            'expenses' => $expenses->values()->all(),
            'expenses_total' => $expensesTotal,
            'net_profit' => $netProfit,
        ];
    }

    public function balanceSheet(string $asOf): array
    {
        $asOfDate = Carbon::parse($asOf)->endOfDay();

        $lines = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.entry_date', '<=', $asOfDate)
            ->where('journal_lines.tenant_id', $this->tenantId())
            ->select(
                'accounts.id as account_id',
                'accounts.name as account_name',
                'accounts.type as account_type',
                DB::raw('coalesce(sum(journal_lines.debit), 0) as total_debit'),
                DB::raw('coalesce(sum(journal_lines.credit), 0) as total_credit')
            )
            ->groupBy('accounts.id', 'accounts.name', 'accounts.type')
            ->get();

        $assets = $this->buildBalanceSheetSection($lines, [
            AccountType::Cash, AccountType::Bank, AccountType::MobileBanking,
            AccountType::Wallet, AccountType::Asset,
        ]);

        $liabilities = $this->buildBalanceSheetSection($lines, [AccountType::Liability]);

        $equity = $this->buildBalanceSheetSection($lines, [AccountType::Equity]);

        $revenueTotal = $this->calculateTypeTotal($lines, AccountType::Income, false);
        $expenseTotal = $this->calculateTypeTotal($lines, AccountType::Expense, true);
        $retainedEarnings = round($revenueTotal - $expenseTotal, 2);

        $assetsTotal = round($assets->sum('amount'), 2);
        $liabilitiesTotal = round($liabilities->sum('amount'), 2);
        $equityTotal = round($equity->sum('amount'), 2);
        $totalLiabilitiesEquity = round($liabilitiesTotal + $equityTotal + $retainedEarnings, 2);

        return [
            'as_of' => $asOf,
            'assets' => $assets->values()->all(),
            'assets_total' => $assetsTotal,
            'liabilities' => $liabilities->values()->all(),
            'liabilities_total' => $liabilitiesTotal,
            'equity' => $equity->values()->all(),
            'equity_total' => $equityTotal,
            'retained_earnings' => $retainedEarnings,
            'total_liabilities_equity' => $totalLiabilitiesEquity,
        ];
    }

    private function buildPnlSection(
        Collection $lines,
        AccountType $type,
        bool $isCreditNormal,
        ?string $accountNumberPrefix = null,
        bool $excludeOther = false,
    ): Collection {
        return $lines->filter(function ($line) use ($type, $accountNumberPrefix, $excludeOther) {
            if ($line->account_type !== $type->value) {
                return false;
            }

            if ($accountNumberPrefix !== null) {
                return str_starts_with($line->account_number ?? '', $accountNumberPrefix);
            }

            if ($excludeOther && $accountNumberPrefix === null) {
                return ! str_starts_with($line->account_number ?? '', '5');
            }

            return true;
        })->map(function ($line) use ($isCreditNormal) {
            $amount = $isCreditNormal
                ? round((float) $line->total_credit - (float) $line->total_debit, 2)
                : round((float) $line->total_debit - (float) $line->total_credit, 2);

            return [
                'account_id' => $line->account_id,
                'account' => $line->account_name,
                'amount' => $amount,
            ];
        })->filter(fn ($item) => $item['amount'] > 0);
    }

    private function buildBalanceSheetSection(Collection $lines, array $types): Collection
    {
        return $lines->filter(fn ($line) => in_array($line->account_type, array_column($types, 'value')))
            ->map(function ($line) {
                $type = AccountType::from($line->account_type);
                $amount = $type->isDebitNormal()
                    ? round((float) $line->total_debit - (float) $line->total_credit, 2)
                    : round((float) $line->total_credit - (float) $line->total_debit, 2);

                return [
                    'account_id' => $line->account_id,
                    'account' => $line->account_name,
                    'amount' => $amount,
                ];
            })->filter(fn ($item) => $item['amount'] > 0);
    }

    private function calculateTypeTotal(Collection $lines, AccountType $type, bool $isDebitNormal): float
    {
        return round((float) $lines->filter(fn ($line) => $line->account_type === $type->value)
            ->sum(fn ($line) => $isDebitNormal
                ? (float) $line->total_debit - (float) $line->total_credit
                : (float) $line->total_credit - (float) $line->total_debit), 2);
    }
}
