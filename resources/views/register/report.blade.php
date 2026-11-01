<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Shift report #{{ $register->id }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Courier New", monospace; font-size: 12px; background: #eef0f4; color: #000; }
        .paper { width: 80mm; margin: 12px auto; padding: 4mm; background: #fff; }
        .center { text-align: center; } .bold { font-weight: 700; }
        h1 { font-size: 15px; } hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; } td { padding: 1px 0; } td:last-child { text-align: right; }
        .big td { font-size: 14px; font-weight: 800; }
        .no-print { text-align: center; margin: 10px; font-family: system-ui, sans-serif; }
        .no-print button { background: #4F46E5; color: #fff; border: 0; border-radius: 8px; padding: 9px 18px; font-weight: 600; cursor: pointer; }
        @page { size: 80mm auto; margin: 0; }
        @media print { body { background: #fff; } .paper { margin: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print"><button onclick="window.print()">Print report</button></div>
    <div class="paper">
        <div class="center">
            <h1>{{ settings('business_name', config('app.name')) }}</h1>
            <div class="bold">{{ $register->branch->name }}</div>
            <div>SHIFT REPORT #{{ $register->id }}</div>
        </div>
        <hr>
        <table>
            <tr><td>Cashier</td><td>{{ $register->user->name }}</td></tr>
            <tr><td>Opened</td><td>{{ $register->opened_at->format('d/m/Y h:i A') }}</td></tr>
            <tr><td>Closed</td><td>{{ $register->closed_at?->format('d/m/Y h:i A') ?? 'OPEN' }}</td></tr>
        </table>
        <hr>
        <table>
            <tr><td>Invoices</td><td>{{ $summary['sales_count'] }}</td></tr>
            <tr><td>Total sales</td><td>{{ money($summary['sales_total'], true) }}</td></tr>
            <tr><td>Credit given</td><td>{{ money($summary['credit_given'], true) }}</td></tr>
            <tr><td>Due received</td><td>{{ money($summary['received_due'], true) }}</td></tr>
        </table>
        <hr>
        <div class="bold">By method</div>
        <table>
            @foreach ($summary['methods'] as $m)<tr><td>{{ $m['label'] }}</td><td>{{ money($m['total'], true) }}</td></tr>@endforeach
        </table>
        <hr>
        <table>
            <tr><td>Opening cash</td><td>{{ money($summary['opening_cash'], true) }}</td></tr>
            <tr><td>+ Cash taken</td><td>{{ money($summary['cash_in'], true) }}</td></tr>
            <tr><td>- Cash refunds</td><td>{{ money($summary['cash_refunds'], true) }}</td></tr>
            <tr class="big"><td>Expected</td><td>{{ money($register->expected_cash ?? $summary['expected_cash'], true) }}</td></tr>
            @if ($register->counted_cash !== null)
                <tr class="big"><td>Counted</td><td>{{ money($register->counted_cash, true) }}</td></tr>
                <tr class="big"><td>Difference</td><td>{{ money($register->difference, true) }}</td></tr>
            @endif
        </table>
        @if ($register->closing_note)<hr><div>Note: {{ $register->closing_note }}</div>@endif
        <hr>
        <div class="center" style="margin-top:14px">Signature: ______________</div>
    </div>
</body>
</html>
