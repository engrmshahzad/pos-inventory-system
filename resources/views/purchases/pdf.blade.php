<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $purchase->purchase_no }}</title>
    @php $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 3), '0'), '.'); @endphp
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0F172A; }
        .muted { color: #64748B; } .brand { font-size: 20px; font-weight: bold; color: #4F46E5; }
        table { width: 100%; border-collapse: collapse; }
        .title { font-size: 20px; font-weight: bold; text-align: right; }
        .box { border: 1px solid #E2E8F0; border-radius: 6px; padding: 10px 12px; }
        .items th { background: #EEF2FF; color: #3730A3; text-align: left; padding: 8px; font-size: 10px; text-transform: uppercase; }
        .items td { padding: 7px 8px; border-bottom: 1px solid #E2E8F0; }
        .right { text-align: right; }
        .grand td { font-size: 13px; font-weight: bold; color: #4F46E5; border-top: 2px solid #4F46E5; padding-top: 8px; }
        .sign { margin-top: 50px; } .sign td { width: 33%; text-align: center; padding-top: 6px; border-top: 1px solid #94A3B8; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width:60%; vertical-align:top">
                <div class="brand">{{ settings('business_name', config('app.name')) }}</div>
                <div><strong>{{ $purchase->branch->name }}</strong></div>
                <div class="muted">{{ $purchase->branch->address }} · {{ $purchase->branch->phone }}</div>
            </td>
            <td style="vertical-align:top">
                <div class="title">{{ $purchase->status === 'ordered' ? 'PURCHASE ORDER' : 'PURCHASE' }}</div>
                <div class="right"><strong>{{ $purchase->purchase_no }}</strong></div>
                <div class="right muted">{{ $purchase->purchase_date->format('d M Y') }}</div>
                @if ($purchase->reference_no)<div class="right muted">Supplier ref: {{ $purchase->reference_no }}</div>@endif
            </td>
        </tr>
    </table>
    <div class="box" style="margin-top:16px">
        <div class="muted" style="font-size:9px; text-transform:uppercase">Supplier</div>
        <strong>{{ $purchase->supplier->name }}</strong>{{ $purchase->supplier->company ? ' — '.$purchase->supplier->company : '' }}
        <div class="muted">{{ $purchase->supplier->phone }} {{ $purchase->supplier->address ? '· '.$purchase->supplier->address : '' }} {{ $purchase->supplier->ntn ? '· NTN '.$purchase->supplier->ntn : '' }}</div>
    </div>
    <table class="items" style="margin-top:16px">
        <thead><tr><th>#</th><th>Item</th><th class="right">Qty</th><th class="right">Unit cost</th><th class="right">Discount</th><th class="right">Tax</th><th class="right">Total</th></tr></thead>
        <tbody>
            @foreach ($purchase->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->product_name }}@if ($item->expiry_date)<br><span class="muted">Exp {{ $item->expiry_date->format('d M Y') }} {{ $item->batch_no ? '· '.$item->batch_no : '' }}</span>@endif</td>
                    <td class="right">{{ $qty($item->quantity) }}</td>
                    <td class="right">{{ money($item->unit_cost, true) }}</td>
                    <td class="right">{{ (float) $item->discount > 0 ? money($item->discount, true) : '—' }}</td>
                    <td class="right">{{ (float) $item->tax_amount > 0 ? money($item->tax_amount, true) : '—' }}</td>
                    <td class="right"><strong>{{ money($item->line_total, true) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table style="margin-top:12px">
        <tr>
            <td style="width:55%"></td>
            <td>
                <table>
                    <tr><td class="muted">Subtotal</td><td class="right">{{ money($purchase->subtotal, true) }}</td></tr>
                    @if ((float) $purchase->discount > 0)<tr><td class="muted">Discount</td><td class="right">-{{ money($purchase->discount, true) }}</td></tr>@endif
                    @if ((float) $purchase->tax_amount > 0)<tr><td class="muted">Tax</td><td class="right">{{ money($purchase->tax_amount, true) }}</td></tr>@endif
                    @if ((float) $purchase->shipping > 0)<tr><td class="muted">Shipping</td><td class="right">{{ money($purchase->shipping, true) }}</td></tr>@endif
                    <tr class="grand"><td>Grand total</td><td class="right">{{ money($purchase->grand_total, true) }}</td></tr>
                    <tr><td class="muted">Paid</td><td class="right">{{ money($purchase->paid_amount, true) }}</td></tr>
                    <tr><td><strong>Due</strong></td><td class="right"><strong>{{ money($purchase->due_amount, true) }}</strong></td></tr>
                </table>
            </td>
        </tr>
    </table>
    <table class="sign"><tr><td>Prepared by ({{ $purchase->user->name }})</td><td style="border:0"></td><td>Received by</td></tr></table>
</body>
</html>
