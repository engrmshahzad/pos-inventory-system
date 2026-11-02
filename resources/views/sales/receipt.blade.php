<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->invoice_no }}</title>
    @php
        $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 3), '0'), '.');
        $small = $width === 58;
    @endphp
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Courier New", ui-monospace, monospace; color: #000; background: #eef0f4; font-size: {{ $small ? '10px' : '12px' }}; }
        .paper { width: {{ $width }}mm; margin: 12px auto; padding: 3mm {{ $small ? '2mm' : '3mm' }}; background: #fff; }
        .center { text-align: center; } .right { text-align: right; } .bold { font-weight: 700; }
        .shop { font-size: {{ $small ? '14px' : '17px' }}; font-weight: 800; letter-spacing: .02em; }
        .muted { color: #333; }
        hr { border: 0; border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .item-name { font-weight: 700; }
        .total-row td { font-size: {{ $small ? '13px' : '15px' }}; font-weight: 800; padding-top: 3px; }
        .barcode { width: 90%; height: 34px; margin: 6px auto 2px; }
        .barcode svg { width: 100%; height: 100%; }
        .no-print { text-align: center; margin: 10px; font-family: Inter, system-ui, sans-serif; }
        .no-print button { background: #4F46E5; color: #fff; border: 0; border-radius: 8px; padding: 9px 18px; font-weight: 600; cursor: pointer; }
        @page { size: {{ $width }}mm auto; margin: 0; }
        @media print { body { background: #fff; } .paper { margin: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print"><button onclick="window.print()">Print receipt</button></div>
    <div class="paper">
        <div class="center">
            @if ((int) settings('receipt_show_logo', '1') && ($logo = settings('business_logo')))
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="" style="max-height:48px; max-width:60%; margin-bottom:4px; filter:grayscale(1)">
            @endif
            <div class="shop">{{ settings('business_name', config('app.name')) }}</div>
            <div class="bold">{{ $sale->branch->name }}</div>
            @if ($sale->branch->address)<div class="muted">{{ $sale->branch->address }}</div>@endif
            @if ($sale->branch->phone)<div class="muted">Ph: {{ $sale->branch->phone }}</div>@endif
            @if (settings('receipt_header'))<div class="muted" style="margin-top:3px">{{ settings('receipt_header') }}</div>@endif
        </div>
        <hr>
        <table>
            <tr><td>Invoice</td><td class="right bold">{{ $sale->invoice_no }}</td></tr>
            <tr><td>Date</td><td class="right">{{ fmtDate($sale->sold_at, true) }}</td></tr>
            <tr><td>Cashier</td><td class="right">{{ $sale->user->name }}</td></tr>
            <tr><td>Customer</td><td class="right">{{ $sale->customer?->name ?? 'Walk-in' }}</td></tr>
            @if ($sale->customer?->phone)<tr><td></td><td class="right">{{ $sale->customer->phone }}</td></tr>@endif
        </table>
        <hr>
        <table>
            @foreach ($sale->items as $item)
                <tr><td colspan="2" class="item-name">{{ $item->product_name }}</td></tr>
                <tr>
                    <td>{{ $qty($item->quantity) }} x {{ formatNumber($item->unit_price, floor((float) $item->unit_price) == (float) $item->unit_price ? 0 : 2) }}@if ((float) $item->discount > 0) <span class="muted">(-{{ formatNumber($item->discount) }})</span>@endif</td>
                    <td class="right">{{ formatNumber($item->line_total, floor((float) $item->line_total) == (float) $item->line_total ? 0 : 2) }}</td>
                </tr>
            @endforeach
        </table>
        <hr>
        <table>
            <tr><td>Subtotal</td><td class="right">{{ money($sale->subtotal) }}</td></tr>
            @if ((float) $sale->discount_amount > 0)
                <tr><td>Discount{{ $sale->discount_type === 'percent' ? ' ('.rtrim(rtrim(number_format((float) $sale->discount_value, 2), '0'), '.').'%)' : '' }}</td><td class="right">-{{ money($sale->discount_amount) }}</td></tr>
            @endif
            @if ((float) $sale->tax_amount > 0)<tr><td>Tax</td><td class="right">{{ money($sale->tax_amount) }}</td></tr>@endif
            @if ((float) $sale->shipping > 0)<tr><td>Other charges</td><td class="right">{{ money($sale->shipping) }}</td></tr>@endif
            <tr class="total-row"><td>TOTAL</td><td class="right">{{ money($sale->grand_total) }}</td></tr>
        </table>
        <hr>
        <table>
            @foreach ($sale->payments->where('type', 'sale') as $payment)
                <tr><td>{{ $payment->method_label }}</td><td class="right">{{ money($payment->amount) }}</td></tr>
            @endforeach
            @if ((float) $sale->change_amount > 0)<tr><td>Cash received</td><td class="right">{{ money((float) $sale->payments->where('method', 'cash')->where('type', 'sale')->sum('amount') + (float) $sale->change_amount) }}</td></tr>
                <tr class="bold"><td>Change</td><td class="right">{{ money($sale->change_amount) }}</td></tr>@endif
            @if ((float) $sale->due_amount > 0)<tr class="bold"><td>Balance due</td><td class="right">{{ money($sale->due_amount) }}</td></tr>@endif
        </table>
        <hr>
        <div class="center">
            <div>Items: {{ $sale->items->count() }} · Qty: {{ $qty($sale->items->sum('quantity')) }}</div>
            <div class="barcode">{!! $barcode !!}</div>
            <div class="bold">{{ $sale->invoice_no }}</div>
            <div style="margin-top:5px">{{ settings('receipt_footer', 'Thank you for shopping with us!') }}</div>
        </div>
    </div>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
</body>
</html>
