@props([
    'variant' => 'primary',   // primary | secondary | outline | ghost | danger | success | warning
    'size' => 'md',           // sm | md | lg
    'icon' => null,
    'iconRight' => null,
    'href' => null,
    'type' => 'button',
    'loading' => false,       // true = show spinner while the parent form submits
])
@php
    $variants = [
        'primary' => 'bg-primary text-white hover:bg-primary-hover shadow-sm shadow-primary/30',
        'secondary' => 'bg-primary/10 text-primary hover:bg-primary/15 dark:text-primary-300',
        'outline' => 'border border-line bg-card text-heading hover:bg-subtle',
        'ghost' => 'text-body hover:bg-subtle hover:text-heading',
        'danger' => 'bg-danger text-white hover:bg-red-600 shadow-sm shadow-danger/30',
        'success' => 'bg-success text-white hover:bg-emerald-600 shadow-sm shadow-success/30',
        'warning' => 'bg-warning text-white hover:bg-amber-600 shadow-sm shadow-warning/30',
        'ghost-danger' => 'text-danger hover:bg-danger/10',
    ];
    $sizes = [
        'sm' => 'h-8 px-3 text-xs gap-1.5',
        'md' => 'h-btn px-4 text-sm gap-2',
        'lg' => 'h-12 px-6 text-[15px] gap-2.5',
        'icon' => 'h-btn w-10 justify-center',
        'icon-sm' => 'h-8 w-8 justify-center',
    ];
    $iconSize = in_array($size, ['sm', 'icon-sm']) ? 'w-4 h-4' : 'w-[18px] h-[18px]';
    $classes = 'group/btn relative inline-flex items-center justify-center rounded-lg font-semibold whitespace-nowrap transition duration-150 '
        .'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-primary/30 disabled:opacity-60 disabled:pointer-events-none '
        .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" :size="$iconSize" />@endif
        {{ $slot }}
        @if ($iconRight)<x-icon :name="$iconRight" :size="$iconSize" />@endif
    </a>
@else
    <button type="{{ $type }}" @if ($loading) data-loading @endif {{ $attributes->merge(['class' => $classes]) }}>
        @if ($loading)
            <span class="absolute inset-0 hidden items-center justify-center group-data-[busy]/btn:flex">
                <x-icon name="loader" size="w-4 h-4 animate-spin" />
            </span>
        @endif
        <span @class(['inline-flex items-center', 'gap-1.5' => in_array($size, ['sm']), 'gap-2' => ! in_array($size, ['sm']), 'group-data-[busy]/btn:invisible' => $loading])>
            @if ($icon)<x-icon :name="$icon" :size="$iconSize" />@endif
            {{ $slot }}
            @if ($iconRight)<x-icon :name="$iconRight" :size="$iconSize" />@endif
        </span>
    </button>
@endif
