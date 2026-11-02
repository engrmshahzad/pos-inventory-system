<div x-data="commandPalette(@js($items), @js(route('search')))"
    @open-palette.window="show()"
    @keydown.window.prevent.ctrl.k="show()"
    @keydown.window.prevent.meta.k="show()"
    @can('pos.access') @keydown.window.f2.prevent="window.location.href = @js(route('pos.index'))" @endcan
    @keydown.escape.window="open = false">
    <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center px-4 pt-[12vh]" role="dialog" aria-modal="true" aria-label="Command palette">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="open = false"></div>
        <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-line bg-card shadow-card-hover">
            <div class="flex items-center gap-3 border-b border-line px-4">
                <x-icon name="search" size="w-5 h-5 text-muted" />
                <input x-ref="input" x-model="query" @input="search()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="go()"
                    type="text" placeholder="Search pages, products, customers, invoices…" class="h-14 w-full border-0 bg-transparent px-0 text-[15px] text-heading placeholder:text-muted focus:ring-0" aria-label="Search">
                <kbd class="kbd">Esc</kbd>
            </div>
            <ul class="scrollbar-thin max-h-80 overflow-y-auto p-2">
                <template x-for="(item, i) in results" :key="item.group + item.url">
                    <li>
                        <a :href="item.url" @mouseenter="index = i"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm"
                            :class="i === index ? 'bg-primary/10 text-heading' : 'text-body'">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-subtle text-muted" :class="i === index && '!bg-primary !text-white'">
                                <x-icon name="arrow-right" size="w-4 h-4" />
                            </span>
                            <span class="min-w-0 flex-1"><span class="block truncate font-medium" x-text="item.label"></span><span class="block truncate text-xs text-muted" x-show="item.sub" x-text="item.sub"></span></span>
                            <span class="text-xs text-muted" x-text="item.group"></span>
                        </a>
                    </li>
                </template>
                <li x-show="loading" class="px-3 py-2 text-xs text-muted">Searching…</li>
                <li x-show="!loading && results.length === 0" class="px-3 py-8 text-center text-sm text-muted">Nothing matches “<span x-text="query"></span>”.</li>
            </ul>
            <div class="flex items-center gap-4 border-t border-line bg-subtle/60 px-4 py-2.5 text-xs text-muted">
                <span class="flex items-center gap-1"><kbd class="kbd">↑</kbd><kbd class="kbd">↓</kbd> navigate</span>
                <span class="flex items-center gap-1"><kbd class="kbd">↵</kbd> open</span>
                <span class="ml-auto">Type 2+ letters to search products, customers, invoices</span>
            </div>
        </div>
    </div>
</div>
