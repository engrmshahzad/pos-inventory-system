<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $payment->payment_no }}</title>
    @php $party = $payment->customer ?? $payment->supplier; @endphp
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Courier New", monospace; font-size: 12px; background: #eef0f4; color: #000; }
        .paper { width: 80mm; margin: 12px auto; padding: 4mm; background: #fff; }
        .center { text-align: center; } .right { text-align: right; } .bold { font-weight: 700; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; } table { width: 100%; border-collapse: collapse; } td { padding: 1px 0; vertical-align: top; }
        .big { font-size: 18px; font-weight: 800; }
        .no-print { text-align: center; margin: 10px; font-family: system-ui, sans-serif; } .no-print button { background: #4F46E5; color: #fff; border: 0; border-radius: 8px; padding: 9px 18px; font-weight: 600; cursor: pointer; }
        @page { size: 80mm auto; margin: 0; } @media print { body { background: #fff; } .paper { margin: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print"><button onclick="window.print()">Print</button></div>
    <div class="paper">
        <div class="center">
            <div class="bold" style="font-size:15px">{{ settings('business_name', config('app.name')) }}</div>
            <div>{{ $payment->branch->name }} · {{ $payment->branch->phone }}</div>
            <div class="bold" style="margin-top:4px">{{ $payment->is_incoming ? 'PAYMENT RECEIPT' : 'PAYMENT VOUCHER' }}</div>
        </div>
        <hr>
        <table>
            <tr><td>No</td><td class="right bold">{{ $payment->payment_no }}</td></tr>
            <tr><td>Date</td><td class="right">{{ fmtDate($payment->paid_at, true) }}</td></tr>
            <tr><td>{{ $payment->is_incoming ? 'From' : 'To' }}</td><td class="right">{{ $party?->name ?? 'Walk-in' }}</td></tr>
            <tr><td>Method</td><td class="right">{{ $payment->method_label }}</td></tr>
            @if ($payment->reference)<tr><td>Ref</td><td class="right">{{ $payment->reference }}</td></tr>@endif
        </table>
        <hr>
        <div class="center big">{{ money($payment->amount, true) }}</div>
        @if ($payment->allocations->isNotEmpty())
            <hr>
            <table>
                @foreach ($payment->allocations as $a)
                    <tr><td>{{ $a->allocatable?->invoice_no ?? $a->allocatable?->purchase_no }}</td><td class="right">{{ money($a->amount, true) }}</td></tr>
                @endforeach
            </table>
        @endif
        @if ($party)<hr><table><tr class="bold"><td>Balance now</td><td class="right">{{ money($party->balance, true) }}</td></tr></table>@endif
        <hr>
        <div class="center" style="margin-top:14px">Received by: ____________</div>
        <div class="center" style="margin-top:6px">{{ $payment->user?->name }}</div>
    </div>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
</body>
</html>
