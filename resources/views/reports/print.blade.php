<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 24px 28px; }
        body { font-family: {{ $pdf ? 'DejaVu Sans' : 'Inter, system-ui' }}, sans-serif; font-size: 10px; color: #0F172A; {{ $pdf ? '' : 'margin: 24px;' }} }
        .brand { font-size: 16px; font-weight: bold; color: #4F46E5; } .muted { color: #64748B; }
        h1 { font-size: 16px; margin: 6px 0 2px; }
        .cards { margin: 12px 0; } .card { display: inline-block; border: 1px solid #E2E8F0; border-radius: 6px; padding: 6px 10px; margin: 0 6px 6px 0; }
        .card b { display: block; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #EEF2FF; color: #3730A3; text-align: left; padding: 6px; font-size: 9px; text-transform: uppercase; }
        td { padding: 5px 6px; border-bottom: 1px solid #E2E8F0; }
        .r { text-align: right; white-space: nowrap; } .neg { color: #DC2626; }
        tr.total td, tr.subtotal td, tfoot td { font-weight: bold; background: #F8FAFC; } tr.total td { background: #EEF2FF; }
        tr.indent td:first-child { padding-left: 20px; } tr.muted td { color: #64748B; font-size: 9px; } tr.heading td { font-weight: bold; text-transform: uppercase; font-size: 9px; color: #475569; }
        .no-print { margin-bottom: 12px; } .no-print button { background: #4F46E5; color: #fff; border: 0; border-radius: 8px; padding: 8px 16px; font-weight: 600; cursor: pointer; }
        @media print { .no-print { display: none; } body { margin: 0; } thead { display: table-header-group; } tr { page-break-inside: avoid; } }
    </style>
</head>
<body>
    @unless ($pdf)<div class="no-print"><button onclick="window.print()">Print</button></div>@endunless
    <div class="brand">{{ settings('business_name', config('app.name')) }}</div>
    <h1>{{ $report->title }}</h1>
    <div class="muted">{{ $subtitle }} · Printed {{ fmtDate(now(), true) }} by {{ $ctx->user->name }}</div>
    <div class="cards">@foreach ($result->cards as $c)<div class="card"><span class="muted">{{ $c[0] }}</span><b>{{ $c[1] }}</b></div>@endforeach</div>
    <table>
        <thead><tr>@foreach ($result->columns as $col)<th class="{{ \App\Reports\ReportFormat::isNumeric($col[1]) ? 'r' : '' }}">{{ $col[0] }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($result->rows as $row)
                <tr class="{{ $row['_style'] ?? '' }}">
                    @foreach ($result->columns as $key => [$label, $type])
                        @php $t = $loop->first ? $type : ($row['_type'] ?? $type); $v = $row[$key] ?? null; $num = \App\Reports\ReportFormat::isNumeric($t); @endphp
                        <td class="{{ $num ? 'r' : '' }} {{ $num && is_numeric($v) && $v < 0 && $t === 'money' ? 'neg' : '' }}">{{ ($row['_style'] ?? '') === 'heading' && ! $loop->first ? '' : \App\Reports\ReportFormat::cell($v, $t) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        @if ($result->totals && $result->rows->isNotEmpty())
            <tfoot><tr>@foreach ($result->columns as $key => [$label, $type])<td class="{{ \App\Reports\ReportFormat::isNumeric($type) ? 'r' : '' }}">{{ array_key_exists($key, $result->totals) ? ($loop->first ? $result->totals[$key] : \App\Reports\ReportFormat::cell($result->totals[$key], $type)) : '' }}</td>@endforeach</tr></tfoot>
        @endif
    </table>
    @if ($result->note)<p class="muted" style="margin-top:10px">{{ $result->note }}</p>@endif
    @unless ($pdf)<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>@endunless
</body>
</html>
