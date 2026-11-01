<x-app-layout title="Dashboard">
    @php $user = auth()->user(); @endphp

    {{-- Hero banner --}}
    <section class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-r from-primary-600 via-primary-600 to-violet-600 p-6 text-white shadow-lg shadow-primary/20 sm:p-8">
        <div class="hero-pattern absolute inset-0 opacity-50"></div>
        <div class="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute -bottom-24 right-40 h-56 w-56 rounded-full bg-violet-300/20 blur-2xl"></div>

        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="flex items-center gap-2 text-sm text-indigo-100">
                    <x-icon name="calendar" size="w-4 h-4" /> {{ now()->format('l, d F Y') }}
                </p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $greeting }}, {{ \Illuminate\Support\Str::before($user->name, ' ') }} 👋</h1>
                <p class="mt-2 max-w-xl text-sm text-indigo-100/90">
                    You're viewing
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-0.5 font-semibold text-white">
                        <x-icon name="store" size="w-3.5 h-3.5" /> {{ $currentBranch?->name ?? 'All Branches' }}
                    </span>
                    — here's a snapshot of today.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('pos.access')
                    <a href="{{ route('pos.index') }}" class="inline-flex h-btn items-center gap-2 rounded-lg bg-white px-4 text-sm font-semibold text-primary shadow-sm transition hover:bg-indigo-50">
                        <x-icon name="shopping-cart" size="w-[18px] h-[18px]" /> New Sale
                    </a>
                @endcan
                @can('purchases.create')
                    <a href="{{ route('purchases.index') }}" class="inline-flex h-btn items-center gap-2 rounded-lg bg-white/15 px-4 text-sm font-semibold text-white ring-1 ring-white/25 backdrop-blur transition hover:bg-white/25">
                        <x-icon name="truck" size="w-[18px] h-[18px]" /> New Purchase
                    </a>
                @endcan
                @can('reports.view')
                    <a href="{{ route('reports.index') }}" class="inline-flex h-btn items-center gap-2 rounded-lg bg-white/15 px-4 text-sm font-semibold text-white ring-1 ring-white/25 backdrop-blur transition hover:bg-white/25">
                        <x-icon name="bar-chart" size="w-[18px] h-[18px]" /> Reports
                    </a>
                @endcan
            </div>
        </div>
    </section>

    {{-- KPI cards --}}
    <div @class(['mb-6 grid gap-4 sm:grid-cols-2 xl:gap-6', 'xl:grid-cols-4' => count($kpis) === 4, 'xl:grid-cols-3' => count($kpis) === 3])>
        @foreach ($kpis as $kpi)
            <x-stat-card :label="$kpi['label']" :value="$kpi['value']" :icon="$kpi['icon']" :color="$kpi['color']" :trend="$kpi['trend']"
                :sparkline="[12, 18, 14, 22, 19, 26, 24, 31]" />
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        @php
            $showProfit = $user->can('reports.profit');
            $series = [['name' => 'Sales', 'data' => $chart['sales']]];
            if ($showProfit) {
                $series[] = ['name' => 'Profit', 'data' => $chart['profit']];
            }
        @endphp
        <x-card :title="$showProfit ? 'Sales & Profit' : 'Sales'" subtitle="Last 14 days · sample data" class="xl:col-span-2" padding="px-3 pb-3 pt-2">
            <x-slot name="actions"><x-badge color="warning" dot>Demo data</x-badge></x-slot>
            <div class="h-[320px]" x-data="apexChart({
                chart: { type: 'area', height: 320 },
                series: @js($series),
                xaxis: { categories: @js($chart['labels']), axisBorder: { show: false }, axisTicks: { show: false } },
                stroke: { curve: 'smooth', width: 2.5 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: 0, stops: [0, 95] } },
                legend: { position: 'top', horizontalAlign: 'right' },
                moneyTooltip: true,
            })"></div>
        </x-card>

        <x-card title="Getting started" subtitle="Phase 1 is ready">
            <ul class="space-y-4">
                @foreach ([
                    ['building', 'Set up your branches', 'Add codes like HYD, KHI used on invoices.', 'branches.manage', 'branches.index'],
                    ['user-plus', 'Invite your staff', 'Managers and cashiers per branch.', 'users.view', 'users.index'],
                    ['package', 'Add products', 'Catalogue and opening stock — Phase 2.', 'products.view', 'products.index'],
                    ['shopping-cart', 'Start selling', 'The POS screen — Phase 3.', 'pos.access', 'pos.index'],
                ] as [$icon, $title, $text, $permission, $route])
                    @can($permission)
                        <li>
                            <a href="{{ route($route) }}" class="group flex items-start gap-3 rounded-xl p-2 -m-2 transition hover:bg-subtle">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary dark:text-primary-300"><x-icon :name="$icon" size="w-5 h-5" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-heading">{{ $title }}</span>
                                    <span class="block text-xs text-muted">{{ $text }}</span>
                                </span>
                                <x-icon name="chevron-right" size="w-4 h-4 mt-3 text-muted transition group-hover:translate-x-0.5 group-hover:text-primary" />
                            </a>
                        </li>
                    @endcan
                @endforeach
            </ul>
        </x-card>
    </div>
</x-app-layout>
