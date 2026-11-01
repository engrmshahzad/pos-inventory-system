@props([
    'label',
    'value',
    'icon' => 'bar-chart',
    'color' => 'primary',     // primary | success | warning | danger | info
    'trend' => null,          // e.g. 12.5 or -3.2
    'trendLabel' => 'vs yesterday',
    'sparkline' => null,      // array of numbers
    'href' => null,
    'hint' => null,           // small text under the value when there is no trend
])
@php
    $tints = [
        'primary' => 'bg-primary/10 text-primary dark:text-primary-300',
        'success' => 'bg-success/10 text-emerald-600 dark:text-emerald-400',
        'warning' => 'bg-warning/10 text-amber-600 dark:text-amber-400',
        'danger' => 'bg-danger/10 text-red-600 dark:text-red-400',
        'info' => 'bg-info/10 text-sky-600 dark:text-sky-400',
    ];
    $hex = ['primary' => '#4F46E5', 'success' => '#10B981', 'warning' => '#F59E0B', 'danger' => '#EF4444', 'info' => '#0EA5E9'][$color] ?? '#4F46E5';
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['card relative overflow-hidden p-5', 'card-hover' => $href]) }}>
    <div class="flex items-start gap-3">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $tints[$color] ?? $tints['primary'] }}">
            <x-icon :name="$icon" size="w-6 h-6" />
        </div>
        <div class="min-w-0 flex-1">
            @if ($sparkline)
                <div class="ml-auto h-10 w-full max-w-[96px]" x-data="apexChart({
                    chart: { type: 'area', height: 40, sparkline: { enabled: true } },
                    series: [{ name: @js($label), data: @js($sparkline) }],
                    stroke: { curve: 'smooth', width: 2 },
                    colors: ['{{ $hex }}'],
                    fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: 0 } },
                    tooltip: { enabled: false },
                })"></div>
            @endif
        </div>
        @if ($trend !== null)
            <span @class([
                'inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold tabular',
                'bg-success/10 text-emerald-600 dark:text-emerald-400' => $trend >= 0,
                'bg-danger/10 text-red-600 dark:text-red-400' => $trend < 0,
            ])>
                {{ $trend >= 0 ? '▲' : '▼' }} {{ number_format(abs($trend), 1) }}%
            </span>
        @endif
    </div>
    <p class="mt-4 text-[13px] font-medium text-muted">{{ $label }}</p>
    <p class="mt-1 whitespace-nowrap text-[24px] font-bold leading-8 tabular text-heading 2xl:text-kpi">{{ $value }}</p>
    @if ($trend !== null)
        <p class="mt-1 text-xs text-muted">{{ $trendLabel }}</p>
    @elseif ($hint)
        <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
    @endif
</{{ $tag }}>
