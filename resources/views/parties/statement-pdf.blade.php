<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Statement</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #0F172A; }
        .muted { color: #64748B; } .brand { font-size: 18px; font-weight: bold; color: #4F46E5; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .title { font-size: 18px; font-weight: bold; text-align: right; }
        .box { border: 1px solid #E2E8F0; border-radius: 6px; padding: 10px 12px; }
        .rows th { background: #EEF2FF; color: #3730A3; text-align: left; padding: 7px; font-size: 9.5px; text-transform: uppercase; }
        .rows td { padding: 6px 7px; border-bottom: 1px solid #E2E8F0; }
        .right { text-align: right; } .bold { font-weight: bold; }
        .total td { background: #F8FAFC; font-weight: bold; }
        .closing { margin-top: 14px; text-align: right; font-size: 14px; font-weight: bold; color: #4F46E5; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width:60%">
                <div class="brand">{{ settings('business_name', config('app.name')) }}</div>
                <div class="muted">{{ settings('business_address') }}</div>
                <div class="muted">{{ settings('business_phone') }}</div>
            </td>
            <td>
                <div class="title">ACCOUNT STATEMENT</div>
                <div class="right muted">{{ $from ? $from->format('d M Y') : 'Beginning' }} — {{ $to ? $to->format('d M Y') : now()->format('d M Y') }}</div>
                <div class="right muted">Printed {{ now()->format('d M Y, h:i A') }}</div>
            </td>
        </tr>
    </table>

    <div class="box" style="margin-top:16px">
        <div class="muted" style="font-size:9px; text-transform:uppercase">{{ $kind === 'customer' ? 'Customer' : 'Supplier' }}</div>
        <div class="bold" style="font-size:13px">{{ $party->name }}{{ $party->company ?? false ? ' — '.$party->company : '' }}</div>
        <div class="muted">{{ $party->phone }} {{ $party->address ? '· '.$party->address : '' }} {{ $party->city ? '· '.$party->city : '' }}</div>
    </div>

    <table class="rows" style="margin-top:16px">
        <thead><tr><th>Date</th><th>Description</th><th>Reference</th><th class="right">Debit</th><th class="right">Credit</th><th class="right">Balance</th></tr></thead>
        <tbody>
            @if ($from)
                <tr><td>{{ $from->format('d M Y') }}</td><td colspan="4" class="bold">Balance brought forward</td><td class="right bold">{{ money($ledger['opening'], true) }}</td></tr>
            @endif
            @foreach ($ledger['entries'] as $e)
                <tr>
                    <td>{{ $e['date']->format('d M Y') }}</td>
                    <td>{{ $e['description'] }}</td>
                    <td>{{ $e['reference'] }}</td>
                    <td class="right">{{ $e['debit'] > 0 ? money($e['debit'], true) : '' }}</td>
                    <td class="right">{{ $e['credit'] > 0 ? money($e['credit'], true) : '' }}</td>
                    <td class="right">{{ money($e['balance'], true) }}</td>
                </tr>
            @endforeach
            <tr class="total"><td colspan="3">Totals</td><td class="right">{{ money($ledger['debit'], true) }}</td><td class="right">{{ money($ledger['credit'], true) }}</td><td></td></tr>
        </tbody>
    </table>

    <div class="closing">{{ $kind === 'customer' ? 'Amount due from customer' : 'Amount payable to supplier' }}: {{ money($ledger['closing'], true) }}</div>
    <p class="muted" style="margin-top:24px">This is a computer-generated statement. Please report any difference within 7 days.</p>
</body>
</html>
