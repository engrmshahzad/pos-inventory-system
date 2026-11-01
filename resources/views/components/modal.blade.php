@props(['name', 'title' => null, 'subtitle' => null, 'maxWidth' => 'lg', 'show' => false])
@php
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'];
@endphp
{{-- Open with: $dispatch('open-modal', 'name'); close with $dispatch('close-modal', 'name') or Esc --}}

<div x-data="{ show: @js($show) }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') show = true"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show" x-cloak
    class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center"
    role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif>
    <div x-show="show" x-transition.opacity.duration.200ms class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="show = false"></div>

    <div x-show="show"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 sm:scale-100" x-transition:leave-end="opacity-0 sm:scale-95"
        {{ $attributes->merge(['class' => 'relative w-full rounded-2xl border border-line bg-card shadow-card-hover '.($widths[$maxWidth] ?? $widths['lg'])]) }}>
        @if ($title)
            <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
                <div>
                    <h3 class="text-card-title text-heading">{{ $title }}</h3>
                    @if ($subtitle)<p class="mt-0.5 text-xs text-muted">{{ $subtitle }}</p>@endif
                </div>
                <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-subtle hover:text-heading" @click="show = false" aria-label="Close">
                    <x-icon name="x" size="w-5 h-5" />
                </button>
            </div>
        @endif
        <div class="px-6 py-5">{{ $slot }}</div>
        @isset($footer)
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-line bg-subtle/60 px-6 py-4">{{ $footer }}</div>
        @endisset
    </div>
</div>
