@props(['title' => null, 'subtitle' => null, 'padding' => 'p-6', 'hover' => false])

<div {{ $attributes->class(['card min-w-0', 'card-hover' => $hover]) }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
            <div class="min-w-0">
                @if ($title)<h3 class="text-card-title text-heading">{{ $title }}</h3>@endif
                @if ($subtitle)<p class="mt-0.5 text-xs text-muted">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $padding }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-line px-6 py-4">{{ $footer }}</div>
    @endisset
</div>
