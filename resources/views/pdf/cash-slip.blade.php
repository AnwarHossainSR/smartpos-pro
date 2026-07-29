<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 32px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; }
    table { width: 100%; border-collapse: collapse; }
    .business-name { font-size: 18px; font-weight: bold; }
    .muted { color: #6b7280; }
    .title { font-size: 16px; font-weight: bold; text-align: center; letter-spacing: 1px; margin-top: 4px; }
    .subtitle { text-align: center; color: #6b7280; margin-bottom: 14px; }
    .badge { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 9px; text-transform: uppercase; font-weight: bold; }
    .badge-open { background: #dbeafe; color: #1d4ed8; }
    .badge-closed { background: #f1f5f9; color: #334155; }
    .meta-table td { padding: 3px 0; }
    .meta-label { color: #6b7280; width: 45%; }
    .section-gap { height: 16px; }
    .section-label { text-transform: uppercase; font-size: 9px; letter-spacing: 1px; color: #6b7280; margin-bottom: 4px; }
    .summary-table { border: 1px solid #e2e8f0; }
    .summary-table td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; }
    .summary-table .label { color: #6b7280; }
    .summary-table .text-right { text-align: right; }
    .summary-table .grand td { font-weight: bold; font-size: 13px; border-top: 1px solid #1a1a1a; border-bottom: none; }
    .diff-positive { color: #15803d; }
    .diff-negative { color: #dc2626; }
    .tx-table th { background: #f8fafc; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; border-bottom: 1px solid #e2e8f0; }
    .tx-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
    .text-right { text-align: right; }
    .footer { margin-top: 30px; padding-top: 10px; border-top: 1px dashed #cbd5e1; color: #6b7280; font-size: 9px; text-align: center; }
</style>
</head>
<body>

<div class="business-name">{{ $business->name }}</div>
@if($branch)<div class="muted">{{ $branch->name }}@if($branch->address) · {{ $branch->address }}@endif</div>@endif

<div class="title">CASH REGISTER SLIP</div>
<div class="subtitle">
    {{ $session->register->name }} ·
    <span class="badge badge-{{ $session->status }}">{{ $session->status }}</span>
</div>

<table class="meta-table">
    <tr><td class="meta-label">Cashier</td><td>{{ $session->user->name ?? 'N/A' }}</td></tr>
    <tr><td class="meta-label">Opened</td><td>{{ $session->opened_at?->format('M d, Y H:i') }}</td></tr>
    @if($session->closed_at)
    <tr><td class="meta-label">Closed</td><td>{{ $session->closed_at->format('M d, Y H:i') }}</td></tr>
    @endif
</table>

<div class="section-gap"></div>
<div class="section-label">Summary</div>
<table class="summary-table">
    <tr><td class="label">Opening cash</td><td class="text-right">{{ \App\Support\Money::format((float) $session->opening_cash, $business->currency) }}</td></tr>
    <tr><td class="label">Cash in</td><td class="text-right">+{{ \App\Support\Money::format((float) $session->cash_in, $business->currency) }}</td></tr>
    <tr><td class="label">Cash out</td><td class="text-right">-{{ \App\Support\Money::format((float) $session->cash_out, $business->currency) }}</td></tr>
    <tr><td class="label">Expected cash</td><td class="text-right">{{ \App\Support\Money::format((float) $session->expected_cash, $business->currency) }}</td></tr>
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
<table class="tx-table">
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
        <tr>
            <td>{{ $tx->created_at?->format('H:i') }}</td>
            <td style="text-transform: capitalize;">Cash {{ $tx->type }}</td>
            <td>{{ $tx->reason ?? '—' }}</td>
            <td class="text-right">{{ $tx->type === 'in' ? '+' : '-' }}{{ \App\Support\Money::format((float) $tx->amount, $business->currency) }}</td>
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

<div class="footer">Generated {{ now()->format('M d, Y H:i') }}</div>

</body>
</html>
