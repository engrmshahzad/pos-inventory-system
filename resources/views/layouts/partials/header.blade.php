@php $user = auth()->user(); @endphp

<header class="sticky top-0 z-30 flex h-header items-center gap-3 border-b border-line bg-card/80 px-4 backdrop-blur-md sm:px-6 lg:px-8">
    <button type="button" @click="$store.sidebar.toggle()" class="-ml-1 flex h-10 w-10 items-center justify-center rounded-lg text-body hover:bg-subtle hover:text-heading" aria-label="Toggle sidebar">
        <x-icon name="menu" size="w-5 h-5 lg:hidden" />
        <x-icon name="panel-left" size="w-5 h-5 hidden lg:block" />
    </button>

    {{-- Global search: opens the command palette --}}
    <button type="button" @click="$dispatch('open-palette')"
        class="hidden h-10 w-full max-w-xs items-center gap-2.5 rounded-lg border border-line bg-subtle px-3 text-sm text-muted transition hover:border-primary/40 md:flex">
        <x-icon name="search" size="w-4 h-4" />
        <span>Search or jump to…</span>
        <span class="ml-auto flex gap-1"><kbd class="kbd">Ctrl</kbd><kbd class="kbd">K</kbd></span>
    </button>
    <button type="button" @click="$dispatch('open-palette')" class="flex h-10 w-10 items-center justify-center rounded-lg text-body hover:bg-subtle md:hidden" aria-label="Search">
        <x-icon name="search" size="w-5 h-5" />
    </button>

    <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
        {{-- Branch switcher (owner) or static branch chip --}}
        @if ($user->isOwner())
            <x-dropdown width="w-64">
                <x-slot name="trigger">
                    <button type="button" class="flex h-10 items-center gap-2 rounded-lg border border-line bg-card px-3 text-sm font-medium text-heading transition hover:bg-subtle" aria-haspopup="true">
                        @if ($currentBranch)
                            <span class="h-2 w-2 rounded-full" style="background: {{ $currentBranch->color }}"></span>
                            <span class="hidden max-w-[150px] truncate sm:inline">{{ $currentBranch->name }}</span>
                            <span class="sm:hidden">{{ $currentBranch->code }}</span>
                        @else
                            <x-icon name="store" size="w-4 h-4 text-primary" />
                            <span class="hidden sm:inline">All Branches</span>
                            <span class="sm:hidden">All</span>
                        @endif
                        <x-icon name="chevron-down" size="w-4 h-4 text-muted" />
                    </button>
                </x-slot>
                <x-slot name="content">
                    <p class="px-3 pb-1.5 pt-2 section-label">Switch branch</p>
                    <form method="POST" action="{{ route('branch.switch') }}">
                        @csrf
                        <button name="branch" value="all" class="dropdown-item">
                            <x-icon name="store" size="w-4 h-4 text-primary" />
                            <span class="flex-1 text-left">All Branches</span>
                            @unless ($currentBranch)<x-icon name="check" size="w-4 h-4 text-primary" />@endunless
                        </button>
                        @foreach ($switchableBranches as $branch)
                            <button name="branch" value="{{ $branch->id }}" class="dropdown-item">
                                <span class="mx-1 h-2 w-2 rounded-full" style="background: {{ $branch->color }}"></span>
                                <span class="flex-1 truncate text-left">{{ $branch->name }}</span>
                                <span class="text-[11px] font-semibold text-muted">{{ $branch->code }}</span>
                                @if ($currentBranch?->id === $branch->id)<x-icon name="check" size="w-4 h-4 text-primary" />@endif
                            </button>
                        @endforeach
                    </form>
                </x-slot>
            </x-dropdown>
        @elseif ($currentBranch)
            <span class="flex h-10 items-center gap-2 rounded-lg border border-line bg-subtle px-3 text-sm font-medium text-heading" title="Your branch">
                <span class="h-2 w-2 rounded-full" style="background: {{ $currentBranch->color }}"></span>
                <span class="hidden sm:inline">{{ $currentBranch->name }}</span>
                <span class="sm:hidden">{{ $currentBranch->code }}</span>
            </span>
        @endif

        @can('pos.access')
            <x-button :href="route('pos.index')" icon="plus" class="hidden sm:inline-flex" title="New Sale (F2)">
                New Sale <kbd class="kbd !border-white/25 !bg-white/15 !text-white !shadow-none">F2</kbd>
            </x-button>
        @endcan

        {{-- Notifications (real ones arrive in Phase 5) --}}
        <x-dropdown width="w-80">
            <x-slot name="trigger">
                <button type="button" class="relative flex h-10 w-10 items-center justify-center rounded-lg text-body hover:bg-subtle hover:text-heading" aria-label="Notifications">
                    <x-icon name="bell" size="w-5 h-5" />
                    <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-danger ring-2 ring-card"></span>
                </button>
            </x-slot>
            <x-slot name="content">
                <div class="flex items-center justify-between px-3 py-2">
                    <p class="text-sm font-semibold text-heading">Notifications</p>
                    <x-badge color="primary">New</x-badge>
                </div>
                <div class="flex gap-3 rounded-lg p-3 hover:bg-subtle">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><x-icon name="sparkles" size="w-4 h-4" /></span>
                    <div>
                        <p class="text-sm font-medium text-heading">Welcome to {{ config('app.name') }}</p>
                        <p class="text-xs text-muted">Low-stock and transfer alerts will appear here.</p>
                    </div>
                </div>
            </x-slot>
        </x-dropdown>

        {{-- Dark mode --}}
        <button type="button" @click="$store.theme.toggle()" class="flex h-10 w-10 items-center justify-center rounded-lg text-body hover:bg-subtle hover:text-heading"
            :aria-label="$store.theme.dark ? 'Switch to light mode' : 'Switch to dark mode'">
            <x-icon name="moon" size="w-5 h-5" x-show="!$store.theme.dark" />
            <x-icon name="sun" size="w-5 h-5" x-show="$store.theme.dark" x-cloak />
        </button>

        {{-- User menu --}}
        <x-dropdown width="w-64">
            <x-slot name="trigger">
                <button type="button" class="flex items-center gap-2 rounded-full p-0.5 pr-0.5 transition hover:ring-4 hover:ring-primary/10 sm:pr-2" aria-label="Account menu">
                    <x-avatar :user="$user" size="sm" />
                    <x-icon name="chevron-down" size="w-4 h-4 text-muted hidden sm:block" />
                </button>
            </x-slot>
            <x-slot name="content">
                <div class="flex items-center gap-3 border-b border-line px-3 pb-3 pt-2">
                    <x-avatar :user="$user" size="md" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-heading">{{ $user->name }}</p>
                        <p class="truncate text-xs text-muted">{{ $user->email }}</p>
                    </div>
                </div>
                <div class="py-1.5">
                    <a href="{{ route('profile.edit') }}" class="dropdown-item"><x-icon name="user" size="w-4 h-4" /> My Profile</a>
                    @can('settings.manage')
                        <a href="{{ route('settings.index') }}" class="dropdown-item"><x-icon name="settings" size="w-4 h-4" /> Settings</a>
                    @endcan
                </div>
                <form method="POST" action="{{ route('logout') }}" class="border-t border-line pt-1.5">
                    @csrf
                    <button type="submit" class="dropdown-item text-danger hover:!text-danger"><x-icon name="log-out" size="w-4 h-4" /> Log out</button>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</header>
