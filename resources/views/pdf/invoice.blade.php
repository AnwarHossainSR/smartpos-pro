<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 32px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; }
    table { width: 100%; border-collapse: collapse; }
    .header-table td { vertical-align: top; }
    .business-name { font-size: 18px; font-weight: bold; }
    .muted { color: #6b7280; }
    .invoice-title { font-size: 20px; font-weight: bold; text-align: right; letter-spacing: 1px; }
    .meta-table td { padding: 1px 0; }
    .meta-label { color: #6b7280; padding-right: 8px; }
    .section-gap { height: 18px; }
    .bill-to-label { text-transform: uppercase; font-size: 9px; letter-spacing: 1px; color: #6b7280; margin-bottom: 3px; }
    .items-table { margin-top: 6px; border: 1px solid #e2e8f0; }
    .items-table th { background: #f8fafc; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; border-bottom: 1px solid #e2e8f0; }
    .items-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
    .text-right { text-align: right; }
    .totals-table { width: 260px; margin-left: auto; margin-top: 10px; }
    .totals-table td { padding: 3px 0; }
    .totals-table .label { color: #6b7280; }
    .totals-table .grand td { border-top: 1px solid #1a1a1a; padding-top: 6px; font-weight: bold; font-size: 13px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; text-transform: uppercase; font-weight: bold; }
    .badge-paid { background: #dcfce7; color: #15803d; }
    .badge-partial { background: #fef3c7; color: #b45309; }
    .badge-due { background: #fee2e2; color: #dc2626; }
    .footer { margin-top: 30px; padding-top: 10px; border-top: 1px dashed #cbd5e1; color: #6b7280; font-size: 9px; text-align: center; }
</style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="width: 60%;">
            <div class="business-name">{{ $business->name }}</div>
            @if($branch)
                <div class="muted">{{ $branch->name }}</div>
                @if($branch->address)<div class="muted">{{ $branch->address }}</div>@endif
                @if($branch->phone)<div class="muted">{{ $branch->phone }}</div>@endif
            @endif
            @if($business->tax_number)<div class="muted">Tax No: {{ $business->tax_number }}</div>@endif
        </td>
        <td style="width: 40%;">
            <div class="invoice-title">INVOICE</div>
            <table class="meta-table" style="margin-top: 8px;">
                <tr><td class="meta-label text-right">Invoice #</td><td class="text-right">{{ $sale->invoice_number }}</td></tr>
                <tr><td class="meta-label text-right">Date</td><td class="text-right">{{ $sale->sold_at?->format('M d, Y H:i') }}</td></tr>
                <tr><td class="meta-label text-right">Status</td>
                    <td class="text-right">
                        <span class="badge badge-{{ $sale->payment_status === 'paid' ? 'paid' : ($sale->payment_status === 'partial' ? 'partial' : 'due') }}">
                            {{ $sale->payment_status }}
                        </span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="section-gap"></div>

<div class="bill-to-label">Billed to</div>
<div>{{ $sale->customer->name ?? 'Walk-in customer' }}</div>
@if($sale->customer?->phone)<div class="muted">{{ $sale->customer->phone }}</div>@endif

<table class="items-table">
    <thead>
        <tr>
            <th>Item</th>
            <th class="text-right">Qty</th>
            <th class="text-right">Unit price</th>
            <th class="text-right">Discount</th>
            <th class="text-right">Tax</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sale->items as $item)
        <tr>
            <td>{{ $item->name }}</td>
            <td class="text-right">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
            <td class="text-right">{{ \App\Support\Money::format((float) $item->unit_price, $business->currency) }}</td>
            <td class="text-right">{{ \App\Support\Money::format((float) $item->discount_amount, $business->currency) }}</td>
            <td class="text-right">{{ \App\Support\Money::format((float) $item->tax_amount, $business->currency) }}</td>
            <td class="text-right">{{ \App\Support\Money::format((float) $item->line_total, $business->currency) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<table class="totals-table">
    <tr><td class="label">Subtotal</td><td class="text-right">{{ \App\Support\Money::format((float) $sale->subtotal, $business->currency) }}</td></tr>
    @if((float) $sale->discount_amount > 0)
    <tr><td class="label">Discount</td><td class="text-right">-{{ \App\Support\Money::format((float) $sale->discount_amount, $business->currency) }}</td></tr>
    @endif
    @if((float) $sale->tax_amount > 0)
    <tr><td class="label">Tax</td><td class="text-right">{{ \App\Support\Money::format((float) $sale->tax_amount, $business->currency) }}</td></tr>
    @endif
    @if((float) $sale->shipping_amount > 0)
    <tr><td class="label">Shipping</td><td class="text-right">{{ \App\Support\Money::format((float) $sale->shipping_amount, $business->currency) }}</td></tr>
    @endif
    <tr class="grand"><td>Total</td><td class="text-right">{{ \App\Support\Money::format((float) $sale->total, $business->currency) }}</td></tr>
    <tr><td class="label">Paid</td><td class="text-right">{{ \App\Support\Money::format((float) $sale->paid_amount, $business->currency) }}</td></tr>
    @if((float) $sale->change_amount > 0)
    <tr><td class="label">Change</td><td class="text-right">{{ \App\Support\Money::format((float) $sale->change_amount, $business->currency) }}</td></tr>
    @endif
    @if((float) $sale->due_amount > 0)
    <tr><td class="label">Due</td><td class="text-right">{{ \App\Support\Money::format((float) $sale->due_amount, $business->currency) }}</td></tr>
    @endif
</table>

@if($sale->payments->isNotEmpty())
<div class="section-gap"></div>
<div class="bill-to-label">Payments</div>
<table class="meta-table">
    @foreach($sale->payments as $payment)
    <tr>
        <td style="text-transform: capitalize;">{{ str_replace('_', ' ', $payment->method) }}</td>
        <td class="text-right">{{ \App\Support\Money::format((float) $payment->amount, $business->currency) }}</td>
    </tr>
    @endforeach
</table>
@endif

<div class="footer">
    Served by {{ $sale->cashier->name ?? 'N/A' }} · Thank you for your business.
</div>

</body>
</html>
