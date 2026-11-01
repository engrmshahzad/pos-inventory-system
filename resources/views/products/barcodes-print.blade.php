<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Barcode labels</title>
    @php
        $a4 = $size['key'] === 'a4';
        $w = $size['width'];
        $h = $size['height'];
    @endphp
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Inter, system-ui, -apple-system, sans-serif; color: #000; background: #F5F7FB; }
        .toolbar { position: sticky; top: 0; display: flex; gap: 12px; align-items: center; justify-content: space-between; padding: 12px 20px; background: #0F172A; color: #fff; font-size: 14px; }
        .toolbar button { background: #4F46E5; color: #fff; border: 0; border-radius: 8px; padding: 9px 18px; font-weight: 600; cursor: pointer; }
        .sheet { margin: 20px auto; background: #fff; box-shadow: 0 1px 3px rgba(15,23,42,.1); }
        @if ($a4)
            .sheet { width: 210mm; min-height: 297mm; padding: 10.7mm 4.7mm; display: grid; grid-template-columns: repeat(5, {{ $w }}mm); grid-auto-rows: {{ $h }}mm; column-gap: 2.5mm; }
        @else
            .sheet { width: {{ $w }}mm; padding: 0; }
            .label { page-break-after: always; break-after: page; }
        @endif
        .label { width: {{ $w }}mm; height: {{ $h }}mm; padding: 1.2mm 1.6mm; display: flex; flex-direction: column; align-items: center; justify-content: center; overflow: hidden; text-align: center; }
        .name { font-size: {{ $w >= 50 ? '8.5pt' : '7pt' }}; font-weight: 600; line-height: 1.15; max-height: 2.3em; overflow: hidden; width: 100%; }
        .bars { width: 100%; height: {{ $a4 ? '8mm' : '10mm' }}; margin: 0.6mm 0 0.3mm; }
        .bars svg { width: 100%; height: 100%; display: block; }
        .code { font-size: 6.5pt; letter-spacing: .08em; font-variant-numeric: tabular-nums; }
        .price { font-size: {{ $w >= 50 ? '10pt' : '8.5pt' }}; font-weight: 800; margin-top: 0.3mm; }
        @page { size: {{ $a4 ? 'A4' : $w.'mm '.$h.'mm' }}; margin: 0; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>{{ $labels->count() }} labels · {{ $size['label'] }}</span>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <div class="sheet">
        @foreach ($labels as $label)
            <div class="label">
                @if ($showName)<div class="name">{{ $label['product']->name }}</div>@endif
                <div class="bars">{!! $label['svg'] !!}</div>
                <div class="code">{{ $label['product']->barcode }}</div>
                @if ($showPrice)<div class="price">{{ money($label['product']->sale_price) }}</div>@endif
            </div>
        @endforeach
    </div>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
