<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $transfer->transfer_no }}</title>
    <style>
        @page { margin: 30px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0F172A; }
        .muted { color: #64748B; } .brand { font-size: 20px; font-weight: bold; color: #4F46E5; }
        table { width: 100%; border-collapse: collapse; }
        .title { font-size: 20px; font-weight: bold; text-align: right; }
        .box { border: 1px solid #E2E8F0; border-radius: 6px; padding: 10px 12px; }
        .items th { background: #EEF2FF; color: #3730A3; text-align: left; padding: 8px; font-size: 10px; text-transform: uppercase; }
        .items td { padding: 8px; border-bottom: 1px solid #E2E8F0; }
        .right { text-align: right; }
        .sign { margin-top: 60px; } .sign td { width: 30%; text-align: center; padding-top: 6px; border-top: 1px solid #94A3B8; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width:55%">
                <div class="brand">{{ settings('business_name', config('app.name')) }}</div>
                <div class="muted">{{ settings('business_phone') }}</div>
            </td>
            <td>
                <div class="title">DELIVERY CHALLAN</div>
                <div class="right"><strong>{{ $transfer->transfer_no }}</strong></div>
                <div class="right muted">Date: {{ $transfer->transfer_date->format('d M Y') }} · Status: {{ $transfer->status_badge[0] }}</div>
            </td>
        </tr>
    </table>
    <table style="margin-top:16px">
        <tr>
            <td style="width:50%; padding-right:8px"><div class="box"><div class="muted" style="font-size:9px; text-transform:uppercase">From</div><strong>{{ $transfer->fromBranch->name }} ({{ $transfer->fromBranch->code }})</strong><div class="muted">{{ $transfer->fromBranch->address }}</div><div class="muted">{{ $transfer->fromBranch->phone }}</div></div></td>
            <td style="width:50%; padding-left:8px"><div class="box"><div class="muted" style="font-size:9px; text-transform:uppercase">To</div><strong>{{ $transfer->toBranch->name }} ({{ $transfer->toBranch->code }})</strong><div class="muted">{{ $transfer->toBranch->address }}</div><div class="muted">{{ $transfer->toBranch->phone }}</div></div></td>
        </tr>
    </table>
    <table class="items" style="margin-top:16px">
        <thead><tr><th style="width:5%">#</th><th>Product</th><th>SKU</th><th class="right">Qty sent</th><th class="right">Qty received</th><th>Remarks</th></tr></thead>
        <tbody>
            @foreach ($transfer->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $item->product?->name }}</strong></td>
                    <td>{{ $item->product?->sku }}</td>
                    <td class="right">{{ $item->product?->formatQty($item->quantity) }} {{ $item->product?->unit?->short_name }}</td>
                    <td class="right">{{ $item->received_quantity !== null ? $item->product?->formatQty($item->received_quantity) : '________' }}</td>
                    <td>{{ $item->note }}{{ (float) $item->shortage > 0 ? ' Short: '.$item->product?->formatQty($item->shortage) : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if ($transfer->note)<p style="margin-top:12px"><strong>Note:</strong> {{ $transfer->note }}</p>@endif
    <table class="sign">
        <tr>
            <td>Dispatched by<br><span class="muted">{{ $transfer->sender?->name }}</span></td>
            <td style="border:0; width:5%"></td>
            <td>Driver / carrier</td>
            <td style="border:0; width:5%"></td>
            <td>Received by<br><span class="muted">{{ $transfer->receiver?->name }}</span></td>
        </tr>
    </table>
</body>
</html>
