{{--
    End-of-day Z-report, laid out for an 80mm thermal roll (226.77pt wide — the paper
    size is set in ZReportService::pdf()). Everything is a single column: a receipt
    printer has no room for a real table, so label/amount pairs use a two-cell row and
    sections are separated by the dashed rules a cashier already recognises from a till
    receipt. Deliberately plain — no colour, no rounded panels: thermal paper is
    monochrome and dithered fills print as grey mud.
--}}
@php
    $currency = $report['currency'];
    $fmt = fn ($amount) => \App\Support\Money::format((float) $amount, $currency);
    $totals = $report['totals'];
    $cash = $report['cash_sessions'];
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 8pt 10pt; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.5pt; color: #000; line-height: 1.35; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    td { vertical-align: top; }

    .center { text-align: center; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
    .muted { color: #444; }

    .title { font-size: 11pt; font-weight: bold; letter-spacing: .5pt; }
    .doc-title { font-size: 9pt; font-weight: bold; letter-spacing: 1.5pt; margin-top: 3pt; }

    /* A dashed rule reads as a tear line on a receipt; a solid one marks a total. */
    .rule { border-top: 1px dashed #000; margin: 5pt 0; height: 0; }
    .rule-solid { border-top: 1px solid #000; margin: 4pt 0; height: 0; }

    .section { font-size: 7pt; font-weight: bold; letter-spacing: .8pt; margin-bottom: 2pt; }
    .row td { padding: 1pt 0; }
    .grand td { font-size: 9pt; font-weight: bold; padding-top: 3pt; }
    .sub { font-size: 6.5pt; color: #444; }
    .footer { margin-top: 8pt; font-size: 6.5pt; color: #444; }
</style>
</head>
<body>

<div class="center">
    <div class="title">{{ $report['business']['name'] }}</div>
    @if($report['branch'])
        <div class="muted">{{ $report['branch']['name'] }}</div>
    @else
        <div class="muted">All branches</div>
    @endif
    <div class="doc-title">Z-REPORT</div>
    <div class="muted">{{ \Illuminate\Support\Carbon::parse($report['date'])->format('D, M d, Y') }}</div>
</div>

<div class="rule"></div>

<div class="section">SALES</div>
<table>
    <colgroup><col style="width: 58%;"><col style="width: 42%;"></colgroup>
    <tr class="row"><td>Transactions</td><td class="right">{{ $totals['transactions'] }}</td></tr>
    <tr class="row"><td>Gross sales</td><td class="right">{{ $fmt($totals['gross_sales']) }}</td></tr>
    <tr class="row"><td>Discounts</td><td class="right">-{{ $fmt($totals['discounts']) }}</td></tr>
    <tr class="row"><td>Tax</td><td class="right">{{ $fmt($totals['tax']) }}</td></tr>
    @if((float) $totals['shipping'] != 0.0)
        <tr class="row"><td>Shipping</td><td class="right">{{ $fmt($totals['shipping']) }}</td></tr>
    @endif
    <tr class="grand"><td>NET SALES</td><td class="right">{{ $fmt($totals['net_sales']) }}</td></tr>
    <tr class="row"><td class="sub">Average basket</td><td class="right sub">{{ $fmt($totals['average_basket']) }}</td></tr>
    <tr class="row"><td class="sub">Paid / due</td><td class="right sub">{{ $fmt($totals['amount_paid']) }} / {{ $fmt($totals['amount_due']) }}</td></tr>
</table>

<div class="rule"></div>

<div class="section">PAYMENT METHODS</div>
<table>
    <colgroup><col style="width: 44%;"><col style="width: 14%;"><col style="width: 42%;"></colgroup>
    @forelse($report['payment_methods'] as $method)
        <tr class="row">
            <td>{{ $method['label'] }}</td>
            <td class="right muted">{{ $method['count'] }}</td>
            <td class="right">{{ $fmt($method['amount']) }}</td>
        </tr>
    @empty
        <tr class="row"><td colspan="3" class="muted">No tender taken.</td></tr>
    @endforelse
</table>

<div class="rule"></div>

<div class="section">TAX COLLECTED</div>
<table>
    <colgroup><col style="width: 58%;"><col style="width: 42%;"></colgroup>
    @forelse($report['taxes']['components'] as $component)
        <tr class="row">
            <td>{{ $component['name'] }} <span class="muted">{{ rtrim(rtrim(number_format($component['rate'], 2, '.', ''), '0'), '.') }}%</span></td>
            <td class="right">{{ $fmt($component['amount']) }}</td>
        </tr>
    @empty
        <tr class="row"><td colspan="2" class="muted">No tax charged.</td></tr>
    @endforelse
    <tr class="row bold"><td>Total tax</td><td class="right">{{ $fmt($report['taxes']['total']) }}</td></tr>
</table>

<div class="rule"></div>

<div class="section">CASH RECONCILIATION</div>
<table>
    <colgroup><col style="width: 58%;"><col style="width: 42%;"></colgroup>
    @if($cash['count'] === 0)
        <tr class="row"><td colspan="2" class="muted">No register session for this day.</td></tr>
    @else
        <tr class="row"><td>Sessions</td><td class="right">{{ $cash['count'] }}</td></tr>
        <tr class="row"><td>Opening cash</td><td class="right">{{ $fmt($cash['opening_cash']) }}</td></tr>
        <tr class="row"><td>Cash in</td><td class="right">+{{ $fmt($cash['cash_in']) }}</td></tr>
        <tr class="row"><td>Cash out</td><td class="right">-{{ $fmt($cash['cash_out']) }}</td></tr>
        <tr class="row bold"><td>Expected cash</td><td class="right">{{ $fmt($cash['expected_cash']) }}</td></tr>
        <tr class="row"><td>Actual counted</td><td class="right">{{ $cash['actual_cash'] === null ? 'not counted' : $fmt($cash['actual_cash']) }}</td></tr>
        @if($cash['difference'] !== null)
            <tr class="grand">
                <td>DIFFERENCE</td>
                <td class="right">{{ (float) $cash['difference'] > 0 ? '+' : '' }}{{ $fmt($cash['difference']) }}</td>
            </tr>
        @endif
    @endif
</table>

@if($cash['count'] > 0)
    <div class="rule-solid"></div>
    <table>
        <colgroup><col style="width: 58%;"><col style="width: 42%;"></colgroup>
        @foreach($cash['sessions'] as $session)
            <tr class="row">
                <td class="sub">{{ $session['register'] }}@if($session['cashier']) &middot; {{ $session['cashier'] }}@endif</td>
                <td class="right sub">{{ $session['difference'] === null ? $session['status'] : $fmt($session['difference']) }}</td>
            </tr>
        @endforeach
    </table>
@endif

<div class="rule"></div>

<div class="section">CORRECTIONS</div>
<table>
    <colgroup><col style="width: 58%;"><col style="width: 42%;"></colgroup>
    <tr class="row"><td>Voided sales ({{ $report['voids']['count'] }})</td><td class="right">{{ $fmt($report['voids']['amount']) }}</td></tr>
    <tr class="row"><td>Returns ({{ $report['returns']['count'] }})</td><td class="right">{{ $fmt($report['returns']['amount']) }}</td></tr>
</table>

<div class="rule"></div>

<table>
    <colgroup><col style="width: 40%;"><col style="width: 60%;"></colgroup>
    <tr class="row"><td class="sub">First invoice</td><td class="right sub">{{ $report['invoices']['first'] ?? '—' }}</td></tr>
    <tr class="row"><td class="sub">Last invoice</td><td class="right sub">{{ $report['invoices']['last'] ?? '—' }}</td></tr>
</table>

<div class="rule"></div>

<div class="center footer">
    Generated {{ \Illuminate\Support\Carbon::parse($report['generated_at'])->format('M d, Y H:i') }}<br>
    System-generated end-of-day report.
</div>

</body>
</html>
