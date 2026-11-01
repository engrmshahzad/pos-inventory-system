{{-- Toast stack (top-right). Fire from JS: toast('success', 'Saved'). Flash: ->with('success'|'error'|'warning'|'info', '...') --}}
@php
    $flash = collect(['success', 'error', 'warning', 'info'])
        ->filter(fn ($type) => session()->has($type))
        ->map(fn ($type) => ['type' => $type, 'message' => session($type)])
        ->values();
    if ($errors->has('branch')) {
        $flash->push(['type' => 'error', 'message' => $errors->first('branch')]);
    }
@endphp

<div x-data x-init="@js($flash).forEach(t => $store.toasts.add(t.type, t.message))"
    class="pointer-events-none fixed right-4 top-4 z-[80] flex w-full max-w-sm flex-col gap-3" aria-live="polite">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-6" x-transition:enter-end="opacity-100 translate-x-0"
            class="pointer-events-auto flex items-start gap-3 rounded-xl border border-line bg-card p-4 shadow-card-hover animate-slide-in">
            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                :class="{
                    'bg-success/10 text-emerald-600': toast.type === 'success',
                    'bg-danger/10 text-red-600': toast.type === 'error',
                    'bg-warning/10 text-amber-600': toast.type === 'warning',
                    'bg-info/10 text-sky-600': toast.type === 'info',
                }">
                <x-icon name="check-circle" size="w-[18px] h-[18px]" x-show="toast.type === 'success'" />
                <x-icon name="x-circle" size="w-[18px] h-[18px]" x-show="toast.type === 'error'" />
                <x-icon name="alert-triangle" size="w-[18px] h-[18px]" x-show="toast.type === 'warning'" />
                <x-icon name="info" size="w-[18px] h-[18px]" x-show="toast.type === 'info'" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold capitalize text-heading" x-text="toast.type === 'error' ? 'Error' : toast.type"></p>
                <p class="mt-0.5 text-sm text-body" x-text="toast.message"></p>
            </div>
            <button type="button" class="rounded-md p-1 text-muted hover:text-heading" @click="$store.toasts.remove(toast.id)" aria-label="Dismiss">
                <x-icon name="x" size="w-4 h-4" />
            </button>
        </div>
    </template>
</div>
