<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $return->return_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Courier New", monospace; color: #000; background: #eef0f4; font-size: {{ $width === 58 ? '10px' : '12px' }}; }
        .paper { width: {{ $width }}mm; margin: 12px auto; padding: 3mm; background: #fff; }
        .center { text-align: center; } .right { text-align: right; } .bold { font-weight: 700; }
        hr { border: 0; border-top: 1px dashed #000; margin: 5px 0; } table { width: 100%; border-collapse: collapse; } td { padding: 1px 0; vertical-align: top; }
        .barcode { width: 90%; height: 34px; margin: 6px auto 2px; } .barcode svg { width: 100%; height: 100%; }
        .no-print { text-align: center; margin: 10px; font-family: system-ui, sans-serif; } .no-print button { background: #4F46E5; color: #fff; border: 0; border-radius: 8px; padding: 9px 18px; font-weight: 600; cursor: pointer; }
        @page { size: {{ $width }}mm auto; margin: 0; } @media print { body { background: #fff; } .paper { margin: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print"><button onclick="window.print()">Print</button></div>
    <div class="paper">
        <div class="center"><div class="bold" style="font-size:15px">{{ settings('business_name', config('app.name')) }}</div><div>{{ $return->branch->name }}</div><div class="bold" style="margin-top:4px">*** RETURN RECEIPT ***</div></div>
        <hr>
        <table>
            <tr><td>Return no</td><td class="right bold">{{ $return->return_no }}</td></tr>
            <tr><td>Original</td><td class="right">{{ $return->sale->invoice_no }}</td></tr>
            <tr><td>Date</td><td class="right">{{ fmtDate($return->returned_at, true) }}</td></tr>
            <tr><td>Customer</td><td class="right">{{ $return->customer?->name ?? 'Walk-in' }}</td></tr>
        </table>
        <hr>
        <table>
            @foreach ($return->items as $item)
                <tr><td colspan="2" class="bold">{{ $item->product_name }}</td></tr>
                <tr><td>{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} x {{ formatNumber($item->unit_price, 2) }}</td><td class="right">{{ formatNumber($item->total, 2) }}</td></tr>
            @endforeach
        </table>
        <hr>
        <table><tr class="bold" style="font-size:14px"><td>REFUND</td><td class="right">{{ money($return->total, true) }}</td></tr><tr><td>Method</td><td class="right">{{ $return->refund_label }}</td></tr></table>
        @if ($return->reason)<hr><div>Reason: {{ $return->reason }}</div>@endif
        <hr>
        <div class="center"><div class="barcode">{!! $barcode !!}</div><div>{{ $return->return_no }}</div><div style="margin-top:12px">Customer sign: ____________</div></div>
    </div>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
</body>
</html>
