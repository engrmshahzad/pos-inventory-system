@props(['align' => 'right', 'width' => 'w-56'])

<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" {{ $attributes->merge(['class' => 'relative']) }}>
    <div @click="open = !open">{{ $trigger }}</div>
    <div x-show="open" x-cloak
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="dropdown-panel {{ $width }} {{ $align === 'right' ? 'right-0 origin-top-right' : 'left-0 origin-top-left' }}"
        @click="open = false">
        {{ $content }}
    </div>
</div>
