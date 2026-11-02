@php $user = auth()->user(); @endphp

{{-- Mobile overlay --}}
<div x-show="$store.sidebar.mobileOpen" x-cloak x-transition.opacity @click="$store.sidebar.mobileOpen = false"
    class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden"></div>

<aside
    class="fixed inset-y-0 left-0 z-50 flex w-sidebar -translate-x-full flex-col bg-sidebar text-slate-300 transition-[width,transform] duration-200 lg:translate-x-0 [.sidebar-collapsed_&]:lg:w-rail"
    :class="{
        'translate-x-0': $store.sidebar.mobileOpen,
        'lg:!w-rail': $store.sidebar.collapsed,
        'lg:!w-sidebar': !$store.sidebar.collapsed,
    }"
    aria-label="Main navigation">

    {{-- Brand --}}
    <div class="flex h-header shrink-0 items-center gap-3 border-b border-white/5 px-5" :class="$store.sidebar.collapsed && 'lg:justify-center lg:px-0'">
        <x-logo />
        <div class="min-w-0" :class="$store.sidebar.collapsed && 'lg:hidden'">
            <p class="truncate text-[15px] font-bold tracking-tight text-white">{{ settings('business_name', config('app.name')) }}</p>
            <p class="truncate text-[11px] text-slate-400">Inventory & POS</p>
        </div>
        <button type="button" class="ml-auto rounded-lg p-1.5 text-slate-400 hover:bg-white/5 hover:text-white lg:hidden" @click="$store.sidebar.mobileOpen = false" aria-label="Close menu">
            <x-icon name="x" size="w-5 h-5" />
        </button>
    </div>

    {{-- Menu --}}
    <nav class="scrollbar-thin flex-1 overflow-y-auto overflow-x-hidden px-3 py-5">
        @foreach ($menu as $group => $items)
            <div class="mb-5 last:mb-0">
                <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500" :class="$store.sidebar.collapsed && 'lg:hidden'">{{ $group }}</p>
                <div class="mx-auto mb-2 hidden h-px w-8 bg-white/10" :class="$store.sidebar.collapsed && 'lg:block'"></div>
                <ul class="space-y-1">
                    @foreach ($items as $item)
                        <li>
                            <a href="{{ $item['url'] }}"
                                @class([
                                    'group relative flex h-10 items-center gap-3 rounded-lg px-3 text-[13.5px] font-medium transition duration-150',
                                    'bg-white/[.07] text-white' => $item['is_active'],
                                    'text-slate-400 hover:bg-white/[.04] hover:text-slate-100' => ! $item['is_active'],
                                ])
                                :class="$store.sidebar.collapsed && 'lg:justify-center lg:px-0'"
                                @if ($item['is_active']) aria-current="page" @endif
                                x-bind:title="$store.sidebar.collapsed ? @js($item['label']) : null">
                                @if ($item['is_active'])
                                    <span class="absolute -left-3 top-1/2 h-6 w-[3px] -translate-y-1/2 rounded-r-full bg-primary-400"></span>
                                @endif
                                <x-icon :name="$item['icon']" @class(['w-[18px] h-[18px] shrink-0', 'text-primary-300' => $item['is_active']]) size="" />
                                <span class="truncate" :class="$store.sidebar.collapsed && 'lg:hidden'">{{ $item['label'] }}</span>
                                @isset($item['kbd'])
                                    <span class="ml-auto rounded border border-white/10 px-1.5 py-px text-[10px] font-semibold text-slate-500" :class="$store.sidebar.collapsed && 'lg:hidden'">{{ $item['kbd'] }}</span>
                                @endisset
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    {{-- User mini-card --}}
    <div class="shrink-0 border-t border-white/5 p-3">
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl p-2 transition hover:bg-white/[.04]"
            :class="$store.sidebar.collapsed && 'lg:justify-center'">
            <x-avatar :user="$user" size="sm" class="!ring-white/10" />
            <div class="min-w-0 flex-1" :class="$store.sidebar.collapsed && 'lg:hidden'">
                <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
                <p class="truncate text-xs capitalize text-slate-400">{{ $user->role_name }}{{ $user->branch ? ' · '.$user->branch->code : '' }}</p>
            </div>
            <x-icon name="chevrons-up-down" size="w-4 h-4 text-slate-500" ::class="$store.sidebar.collapsed && 'lg:hidden'" />
        </a>
    </div>
</aside>
