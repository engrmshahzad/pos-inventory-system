@props(['name', 'title' => null, 'titleText' => null, 'subtitle' => null, 'show' => false])
{{-- titleText: optional Alpine expression for a dynamic title, e.g. "editing ? 'Edit' : 'New'" --}}
{{-- Right slide-over (480px). Open with $dispatch('open-drawer', 'name'). --}}

<div x-data="{ show: @js($show) }"
    x-on:open-drawer.window="if ($event.detail === '{{ $name }}') show = true"
    x-on:close-drawer.window="if ($event.detail === '{{ $name }}') show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show" x-cloak class="fixed inset-0 z-[60]" role="dialog" aria-modal="true">
    <div x-show="show" x-transition.opacity.duration.200ms class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="show = false"></div>

    <div x-show="show"
        x-transition:enter="transform transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
        {{ $attributes->merge(['class' => 'absolute inset-y-0 right-0 flex w-full max-w-[480px] flex-col bg-card shadow-drawer']) }}>
        <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-5">
            <div>
                <h3 class="text-card-title text-heading" @if ($titleText) x-text="{{ $titleText }}" @endif>{{ $title }}</h3>
                @if ($subtitle)<p class="mt-0.5 text-xs text-muted">{{ $subtitle }}</p>@endif
            </div>
            <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-subtle hover:text-heading" @click="show = false" aria-label="Close">
                <x-icon name="x" size="w-5 h-5" />
            </button>
        </div>
        {{ $slot }}
    </div>
</div>
