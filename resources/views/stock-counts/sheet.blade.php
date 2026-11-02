<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $count->count_no }} count sheet</title>
    <style>
        * { box-sizing: border-box; } body { font-family: Inter, system-ui, sans-serif; font-size: 12px; color: #0F172A; margin: 24px; }
        h1 { font-size: 18px; margin: 0; } .muted { color: #64748B; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { background: #EEF2FF; color: #3730A3; text-align: left; padding: 7px; font-size: 10px; text-transform: uppercase; }
        td { padding: 9px 7px; border-bottom: 1px solid #CBD5E1; } .box { width: 90px; border-bottom: 1px solid #0F172A; }
        .right { text-align: right; } .no-print { margin-bottom: 12px; }
        .no-print button { background: #4F46E5; color: #fff; border: 0; border-radius: 8px; padding: 9px 18px; font-weight: 600; cursor: pointer; }
        @media print { .no-print { display: none; } body { margin: 10mm; } thead { display: table-header-group; } tr { page-break-inside: avoid; } }
    </style>
</head>
<body>
    <div class="no-print"><button onclick="window.print()">Print</button></div>
    <h1>{{ settings('business_name', config('app.name')) }} — Stock count sheet</h1>
    <p class="muted">{{ $count->count_no }} · {{ $count->branch->name }} · {{ $count->category?->name ?? 'All products' }} · {{ $count->created_at->format('d M Y') }} · Counted by: ______________</p>
    <table>
        <thead><tr><th>#</th><th>Product</th><th>SKU / Barcode</th><th>Unit</th><th class="right">Counted qty</th><th>Remarks</th></tr></thead>
        <tbody>
            @foreach ($items as $item)
                <tr><td>{{ $loop->iteration }}</td><td><strong>{{ $item->product?->name }}</strong></td><td class="muted">{{ $item->product?->sku }}<br>{{ $item->product?->barcode }}</td><td>{{ $item->product?->unit?->short_name }}</td><td class="right"><div class="box" style="margin-left:auto">&nbsp;</div></td><td></td></tr>
            @endforeach
        </tbody>
    </table>
    <p class="muted" style="margin-top:12px">System quantities are deliberately not printed, so the count is not influenced by them.</p>
</body>
</html>
