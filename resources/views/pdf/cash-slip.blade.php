<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 30px 34px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e2430; }
    table { width: 100%; border-collapse: collapse; }

    /* table-layout: fixed + explicit <colgroup> widths keep header and body cells locked to the
       same column boundaries — dompdf's automatic column-width pass can otherwise size a <thead>
       and <tbody> independently and misalign a right-aligned header against its own values. */
    .fixed { table-layout: fixed; }

    .accent-rule { height: 3px; background: #4f46e5; margin: 14px 0 18px; border-radius: 2px; }

    .business-name { font-size: 19px; font-weight: bold; letter-spacing: -.2px; }
    .muted { color: #64748b; }
    .small { font-size: 9.5px; }

    .doc-title { font-size: 15px; font-weight: bold; letter-spacing: 1.5px; color: #4f46e5; text-align: right; }
    .doc-sub { text-align: right; color: #64748b; margin-top: 3px; }

    .badge { display: inline-block; padding: 2px 9px; border-radius: 9px; font-size: 8.5px; text-transform: uppercase; font-weight: bold; letter-spacing: .3px; }
    .badge-open { background: #e0e7ff; color: #4338ca; }
    .badge-closed { background: #f1f5f9; color: #475569; }

    .meta-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-top: 16px; }
    .meta-card table td { padding: 2px 0; }
    .meta-label { color: #64748b; width: 45%; }

    .section-gap { height: 18px; }
    .section-label { text-transform: uppercase; font-size: 9px; letter-spacing: 1px; color: #64748b; font-weight: bold; margin-bottom: 6px; }

    .summary-table { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
    .summary-table td { padding: 7px 12px; border-bottom: 1px solid #f1f5f9; }
    .summary-table .label { color: #64748b; }
    .summary-table .text-right { text-align: right; }
    .summary-table .subtotal-row td { background: #f8fafc; font-weight: bold; }
    .summary-table .grand td { font-weight: bold; font-size: 13px; border-top: 1px solid #1e2430; border-bottom: none; padding-top: 9px; }
    .diff-positive { color: #15803d; }
    .diff-negative { color: #dc2626; }

    .tx-table { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
    .tx-table th { background: #f8fafc; text-align: left; padding: 7px 12px; font-size: 8.5px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; border-bottom: 2px solid #e2e8f0; }
    .tx-table td { padding: 7px 12px; border-bottom: 1px solid #f1f5f9; }
    .tx-table tr.alt td { background: #fafbfc; }
    .text-right { text-align: right; }
    .amount-in { color: #15803d; }
    .amount-out { color: #dc2626; }

    .footer { margin-top: 34px; padding-top: 10px; border-top: 1px dashed #cbd5e1; color: #94a3b8; font-size: 9px; text-align: center; }
</style>
</head>
<body>

<table>
    <tr>
        <td style="width: 55%; vertical-align: top;">
            <div class="business-name">{{ $business->name }}</div>
            @if($branch)<div class="muted" style="margin-top: 2px;">{{ $branch->name }}@if($branch->address) &middot; {{ $branch->address }}@endif</div>@endif
        </td>
        <td style="width: 45%; vertical-align: top;">
            <div class="doc-title">CASH REGISTER SLIP</div>
            <div class="doc-sub">{{ $session->register->name }} &middot; <span class="badge badge-{{ $session->status->value }}">{{ $session->status->label() }}</span></div>
        </td>
    </tr>
</table>
<div class="accent-rule"></div>

<div class="meta-card">
    <table>
        <tr><td class="meta-label">Cashier</td><td>{{ $session->user->name ?? 'N/A' }}</td></tr>
        <tr><td class="meta-label">Opened</td><td>{{ $session->opened_at?->format('M d, Y H:i') }}</td></tr>
        @if($session->closed_at)
        <tr><td class="meta-label">Closed</td><td>{{ $session->closed_at->format('M d, Y H:i') }}</td></tr>
        @endif
    </table>
</div>

<div class="section-gap"></div>
<div class="section-label">Summary</div>
<table class="summary-table fixed">
    <colgroup><col style="width: 65%;"><col style="width: 35%;"></colgroup>
    <tr><td class="label">Opening cash</td><td class="text-right">{{ \App\Support\Money::format((float) $session->opening_cash, $business->currency) }}</td></tr>
    <tr><td class="label">Cash in</td><td class="text-right amount-in">+{{ \App\Support\Money::format((float) $session->cash_in, $business->currency) }}</td></tr>
    <tr><td class="label">Cash out</td><td class="text-right amount-out">-{{ \App\Support\Money::format((float) $session->cash_out, $business->currency) }}</td></tr>
    <tr class="subtotal-row"><td class="label">Expected cash</td><td class="text-right">{{ \App\Support\Money::format((float) $session->expected_cash, $business->currency) }}</td></tr>
    @if($session->actual_cash !== null)
    <tr><td class="label">Actual cash counted</td><td class="text-right">{{ \App\Support\Money::format((float) $session->actual_cash, $business->currency) }}</td></tr>
    <tr class="grand">
        <td>Difference</td>
        <td class="text-right {{ (float) $session->difference >= 0 ? 'diff-positive' : 'diff-negative' }}">
            {{ (float) $session->difference >= 0 ? '+' : '' }}{{ \App\Support\Money::format((float) $session->difference, $business->currency) }}
        </td>
    </tr>
    @endif
</table>

@if($session->transactions->isNotEmpty())
<div class="section-gap"></div>
<div class="section-label">Cash movements</div>
<table class="tx-table fixed">
    <colgroup>
        <col style="width: 14%;"><col style="width: 16%;"><col style="width: 48%;"><col style="width: 22%;">
    </colgroup>
    <thead>
        <tr>
            <th>Time</th>
            <th>Type</th>
            <th>Reason</th>
            <th class="text-right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($session->transactions as $tx)
        <tr @if($loop->even) class="alt" @endif>
            <td>{{ $tx->created_at?->format('H:i') }}</td>
            <td>Cash {{ $tx->type->value }}</td>
            <td>{{ $tx->reason ?? '—' }}</td>
            @php($isIn = $tx->type === \App\Domain\Enums\CashMovementType::In)
            <td class="text-right {{ $isIn ? 'amount-in' : 'amount-out' }}">{{ $isIn ? '+' : '-' }}{{ \App\Support\Money::format((float) $tx->amount, $business->currency) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@if($session->note)
<div class="section-gap"></div>
<div class="section-label">Note</div>
<div>{{ $session->note }}</div>
@endif

<div class="footer">
    Generated {{ now()->format('M d, Y H:i') }} &middot; This is a system-generated document.
</div>

</body>
</html>
