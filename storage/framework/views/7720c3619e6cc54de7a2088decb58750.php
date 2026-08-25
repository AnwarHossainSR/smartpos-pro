<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 30px 34px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e2430; }
    table { width: 100%; border-collapse: collapse; }
    .header-table td { vertical-align: top; }

    /* table-layout: fixed + explicit <colgroup> widths keep header and body cells locked to the
       same column boundaries — dompdf's automatic column-width pass can otherwise size a <thead>
       and <tbody> independently and misalign a right-aligned header against its own values. */
    .fixed { table-layout: fixed; }

    .accent-rule { height: 3px; background: #4f46e5; margin: 14px 0 18px; border-radius: 2px; }

    .business-name { font-size: 19px; font-weight: bold; letter-spacing: -.2px; }
    .muted { color: #64748b; }

    .invoice-title { font-size: 20px; font-weight: bold; text-align: right; letter-spacing: 1.5px; color: #4f46e5; }
    .meta-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-top: 10px; }
    .meta-table td { padding: 2px 0; }
    .meta-label { color: #64748b; padding-right: 8px; }

    .section-gap { height: 18px; }
    .bill-to-label { text-transform: uppercase; font-size: 9px; letter-spacing: 1px; color: #64748b; font-weight: bold; margin-bottom: 4px; }

    .items-table { margin-top: 8px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
    .items-table th { background: #f8fafc; text-align: left; padding: 7px 10px; font-size: 8.5px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; border-bottom: 2px solid #e2e8f0; }
    .items-table td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; }
    .items-table tr.alt td { background: #fafbfc; }
    .text-right { text-align: right; }

    .totals-card { width: 270px; margin-left: auto; margin-top: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; }
    .totals-table td { padding: 3px 0; }
    .totals-table .label { color: #64748b; }
    .totals-table .grand td { border-top: 1px solid #1e2430; padding-top: 7px; margin-top: 4px; font-weight: bold; font-size: 14px; color: #4f46e5; }
    .totals-table .due td { font-weight: bold; color: #dc2626; }

    .badge { display: inline-block; padding: 2px 9px; border-radius: 9px; font-size: 8.5px; text-transform: uppercase; font-weight: bold; letter-spacing: .3px; }
    .badge-paid { background: #dcfce7; color: #15803d; }
    .badge-partial { background: #fef3c7; color: #b45309; }
    .badge-due { background: #fee2e2; color: #dc2626; }

    .footer { margin-top: 34px; padding-top: 10px; border-top: 1px dashed #cbd5e1; color: #94a3b8; font-size: 9px; text-align: center; }
</style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="width: 58%;">
            <div class="business-name"><?php echo e($business->name); ?></div>
            <?php if($branch): ?>
                <div class="muted" style="margin-top: 3px;"><?php echo e($branch->name); ?></div>
                <?php if($branch->address): ?><div class="muted"><?php echo e($branch->address); ?></div><?php endif; ?>
                <?php if($branch->phone): ?><div class="muted"><?php echo e($branch->phone); ?></div><?php endif; ?>
            <?php endif; ?>
            <?php if($business->tax_number): ?><div class="muted"><?php echo e($profile->tax_number_label ?? 'Tax No'); ?>: <?php echo e($business->tax_number); ?></div><?php endif; ?>
        </td>
        <td style="width: 42%;">
            <div class="invoice-title">INVOICE</div>
            <div class="meta-card">
                <table class="meta-table">
                    <tr><td class="meta-label">Invoice #</td><td class="text-right"><?php echo e($sale->invoice_number); ?></td></tr>
                    <tr><td class="meta-label">Date</td><td class="text-right"><?php echo e($sale->sold_at?->format('M d, Y H:i')); ?></td></tr>
                    <tr><td class="meta-label">Status</td>
                        <td class="text-right">
                            <span class="badge badge-<?php echo e($sale->payment_status === 'paid' ? 'paid' : ($sale->payment_status === 'partial' ? 'partial' : 'due')); ?>">
                                <?php echo e($sale->payment_status); ?>

                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="accent-rule"></div>

<div class="bill-to-label">Billed to</div>
<div style="font-weight: bold;"><?php echo e($sale->customer->name ?? 'Walk-in customer'); ?></div>
<?php if($sale->customer?->phone): ?><div class="muted"><?php echo e($sale->customer->phone); ?></div><?php endif; ?>
<?php if($sale->customer?->tax_exempt): ?>
    <div class="muted">Tax exempt<?php echo e($sale->customer->tax_exemption_reference ? ' ('.$sale->customer->tax_exemption_reference.')' : ''); ?></div>
<?php elseif($sale->customer?->tax_number): ?>
    <div class="muted"><?php echo e($profile->tax_number_label ?? 'Tax No'); ?>: <?php echo e($sale->customer->tax_number); ?></div>
<?php endif; ?>

<table class="items-table fixed">
    <colgroup>
        <col style="width: 32%;"><col style="width: 8%;"><col style="width: 15%;">
        <col style="width: 15%;"><col style="width: 12%;"><col style="width: 18%;">
    </colgroup>
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
        <?php $__currentLoopData = $sale->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr <?php if($loop->even): ?> class="alt" <?php endif; ?>>
            <td><?php echo e($item->name); ?></td>
            <td class="text-right"><?php echo e(rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.')); ?></td>
            <td class="text-right"><?php echo e(\App\Support\Money::format((float) $item->unit_price, $business->currency)); ?></td>
            <td class="text-right"><?php echo e(\App\Support\Money::format((float) $item->discount_amount, $business->currency)); ?></td>
            <td class="text-right"><?php echo e(\App\Support\Money::format((float) $item->tax_amount, $business->currency)); ?></td>
            <td class="text-right"><?php echo e(\App\Support\Money::format((float) $item->line_total, $business->currency)); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
</table>

<?php
    // Aggregated across every line rather than shown per-line: a GST invoice
    // prints CGST and SGST as their own totals, not folded into one "Tax" figure.
    $taxComponents = $sale->items->flatMap->taxes->groupBy('name')->map(fn ($rows) => [
        'name' => $rows->first()->name,
        'rate' => (float) $rows->first()->rate,
        'is_percentage' => $rows->first()->type === \App\Domain\Enums\TaxType::Percentage,
        'amount' => (float) $rows->sum('amount'),
    ])->values();
?>

<div class="totals-card">
    <table class="totals-table">
        <tr><td class="label">Subtotal</td><td class="text-right"><?php echo e(\App\Support\Money::format((float) $sale->subtotal, $business->currency)); ?></td></tr>
        <?php if((float) $sale->discount_amount > 0): ?>
        <tr><td class="label">Discount</td><td class="text-right">-<?php echo e(\App\Support\Money::format((float) $sale->discount_amount, $business->currency)); ?></td></tr>
        <?php endif; ?>
        <?php $__empty_1 = true; $__currentLoopData = $taxComponents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $component): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
            <td class="label"><?php echo e($component['name']); ?><?php echo e($component['is_percentage'] ? ' ('.rtrim(rtrim(number_format($component['rate'], 2), '0'), '.').'%)' : ''); ?></td>
            <td class="text-right"><?php echo e(\App\Support\Money::format($component['amount'], $business->currency)); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <?php if((float) $sale->tax_amount > 0): ?>
            <tr><td class="label">Tax</td><td class="text-right"><?php echo e(\App\Support\Money::format((float) $sale->tax_amount, $business->currency)); ?></td></tr>
            <?php endif; ?>
        <?php endif; ?>
        <?php if((float) $sale->shipping_amount > 0): ?>
        <tr><td class="label">Shipping</td><td class="text-right"><?php echo e(\App\Support\Money::format((float) $sale->shipping_amount, $business->currency)); ?></td></tr>
        <?php endif; ?>
        <tr class="grand"><td>Total</td><td class="text-right"><?php echo e(\App\Support\Money::format((float) $sale->total, $business->currency)); ?></td></tr>
        <tr><td class="label">Paid</td><td class="text-right"><?php echo e(\App\Support\Money::format((float) $sale->paid_amount, $business->currency)); ?></td></tr>
        <?php if((float) $sale->change_amount > 0): ?>
        <tr><td class="label">Change</td><td class="text-right"><?php echo e(\App\Support\Money::format((float) $sale->change_amount, $business->currency)); ?></td></tr>
        <?php endif; ?>
        <?php if((float) $sale->due_amount > 0): ?>
        <tr class="due"><td>Due</td><td class="text-right"><?php echo e(\App\Support\Money::format((float) $sale->due_amount, $business->currency)); ?></td></tr>
        <?php endif; ?>
    </table>
</div>

<?php if($sale->payments->isNotEmpty()): ?>
<div class="section-gap"></div>
<div class="bill-to-label">Payments</div>
<table class="meta-table">
    <?php $__currentLoopData = $sale->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <tr>
        <td><?php echo e($payment->method->label()); ?></td>
        <td class="text-right"><?php echo e(\App\Support\Money::format((float) $payment->amount, $business->currency)); ?></td>
    </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</table>
<?php endif; ?>

<?php if(!empty($profile->required_invoice_fields)): ?>
<div class="section-gap"></div>
<div class="muted" style="font-size: 8.5px;"><?php echo e(implode(' &middot; ', $profile->required_invoice_fields)); ?></div>
<?php endif; ?>

<div class="footer">
    Served by <?php echo e($sale->cashier->name ?? 'N/A'); ?> &middot; Thank you for your business.<br>
    Generated <?php echo e(now()->format('M d, Y H:i')); ?> &middot; This is a computer-generated invoice.
</div>

</body>
</html>
<?php /**PATH D:\Workspace\own\smartpos-pro\resources\views/pdf/invoice.blade.php ENDPATH**/ ?>