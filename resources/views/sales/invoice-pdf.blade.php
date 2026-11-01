<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->invoice_no }}</title>
    @php $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 3), '0'), '.'); @endphp
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0F172A; }
        .muted { color: #64748B; }
        .brand { font-size: 20px; font-weight: bold; color: #4F46E5; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .title { font-size: 22px; font-weight: bold; letter-spacing: 1px; text-align: right; }
        .meta td { padding: 2px 0; }
        .box { border: 1px solid #E2E8F0; border-radius: 6px; padding: 10px 12px; }
        .items th { background: #EEF2FF; color: #3730A3; text-align: left; padding: 8px; font-size: 10px; text-transform: uppercase; }
        .items td { padding: 8px; border-bottom: 1px solid #E2E8F0; }
        .right { text-align: right; }
        .totals td { padding: 4px 8px; }
        .grand td { font-size: 14px; font-weight: bold; color: #4F46E5; border-top: 2px solid #4F46E5; padding-top: 8px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .paid { background: #D1FAE5; color: #047857; } .partial { background: #FEF3C7; color: #B45309; } .due { background: #FEE2E2; color: #B91C1C; }
        .footer { margin-top: 30px; text-align: center; color: #64748B; font-size: 10px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width:60%">
                <div class="brand">{{ settings('business_name', config('app.name')) }}</div>
                <div><strong>{{ $sale->branch->name }}</strong></div>
                <div class="muted">{{ $sale->branch->address }}</div>
                <div class="muted">{{ $sale->branch->phone }} {{ $sale->branch->email ? '· '.$sale->branch->email : '' }}</div>
            </td>
            <td>
                <div class="title">INVOICE</div>
                <table class="meta">
                    <tr><td class="muted right">Invoice no</td><td class="right"><strong>{{ $sale->invoice_no }}</strong></td></tr>
                    <tr><td class="muted right">Date</td><td class="right">{{ $sale->sold_at->format('d M Y, h:i A') }}</td></tr>
                    <tr><td class="muted right">Status</td><td class="right"><span class="badge {{ $sale->payment_status }}">{{ strtoupper($sale->payment_status) }}</span></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top:18px">
        <tr>
            <td style="width:50%; padding-right:8px">
                <div class="box">
                    <div class="muted" style="font-size:9px; text-transform:uppercase">Bill to</div>
                    <strong>{{ $sale->customer?->name ?? 'Walk-in Customer' }}</strong>
                    @if ($sale->customer)<div class="muted">{{ $sale->customer->phone }}</div><div class="muted">{{ $sale->customer->address }}</div>@endif
                </div>
            </td>
            <td style="width:50%; padding-left:8px">
                <div class="box">
                    <div class="muted" style="font-size:9px; text-transform:uppercase">Served by</div>
                    <strong>{{ $sale->user->name }}</strong>
                    <div class="muted">{{ $sale->branch->code }} · {{ $sale->branch->city }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top:18px">
        <thead><tr><th style="width:5%">#</th><th>Item</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Discount</th><th class="right">Total</th></tr></thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $item->product_name }}</strong><br><span class="muted">{{ $item->sku }}</span></td>
                    <td class="right">{{ $qty($item->quantity) }}</td>
                    <td class="right">{{ money($item->unit_price, true) }}</td>
                    <td class="right">{{ (float) $item->discount > 0 ? money($item->discount, true) : '—' }}</td>
                    <td class="right"><strong>{{ money($item->line_total, true) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top:12px">
        <tr>
            <td style="width:55%; vertical-align:top">
                <div class="muted" style="font-size:9px; text-transform:uppercase; margin-bottom:4px">Payments</div>
                @forelse ($sale->payments as $p)
                    <div>{{ $p->paid_at->format('d M Y') }} · {{ $p->method_label }} · <strong>{{ money($p->amount, true) }}</strong></div>
                @empty
                    <div class="muted">No payment received</div>
                @endforelse
                <div style="margin-top:14px"><img src="{{ $barcode }}" style="height:34px; width:200px"></div>
            </td>
            <td style="width:45%">
                <table class="totals">
                    <tr><td class="muted">Subtotal</td><td class="right">{{ money($sale->subtotal, true) }}</td></tr>
                    @if ((float) $sale->discount_amount > 0)<tr><td class="muted">Discount</td><td class="right">-{{ money($sale->discount_amount, true) }}</td></tr>@endif
                    @if ((float) $sale->tax_amount > 0)<tr><td class="muted">Tax</td><td class="right">{{ money($sale->tax_amount, true) }}</td></tr>@endif
                    @if ((float) $sale->shipping > 0)<tr><td class="muted">Other charges</td><td class="right">{{ money($sale->shipping, true) }}</td></tr>@endif
                    <tr class="grand"><td>Grand total</td><td class="right">{{ money($sale->grand_total, true) }}</td></tr>
                    <tr><td class="muted">Paid</td><td class="right">{{ money($sale->paid_amount, true) }}</td></tr>
                    <tr><td><strong>Balance due</strong></td><td class="right"><strong>{{ money($sale->due_amount, true) }}</strong></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">{{ settings('receipt_footer', 'Thank you for your business!') }}</div>
</body>
</html>
