<x-app-layout title="Dashboard">
    @php
        $user = auth()->user();
        $k = $m['kpis'];
        $b = $m['balances'];
        $kpis = array_values(array_filter([
            ['Today\'s Sales', money($k['sales'][0]), 'banknote', 'primary', $k['sales'][1], route('sales.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()])],
            $canProfit ? ['Today\'s Profit', money($k['profit'][0]), 'trending-up', 'success', $k['profit'][1], null] : null,
            ['Today\'s Orders', number_format($k['orders'][0]), 'shopping-cart', 'info', $k['orders'][1], null],
            ['Today\'s Expenses', money($k['expenses'][0]), 'wallet', 'warning', $k['expenses'][1], route('expenses.index')],
        ]));
    @endphp

    {{-- Hero banner --}}
    <section class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-r from-primary-600 via-primary-600 to-violet-600 p-6 text-white shadow-lg shadow-primary/20 sm:p-8">
        <div class="hero-pattern absolute inset-0 opacity-50"></div>
        <div class="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="flex items-center gap-2 text-sm text-indigo-100"><x-icon name="calendar" size="w-4 h-4" /> {{ now()->format('l, '.settings('date_format', 'd M Y')) }}</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $greeting }}, {{ \Illuminate\Support\Str::before($user->name, ' ') }} 👋</h1>
                <p class="mt-2 max-w-xl text-sm text-indigo-100/90">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-0.5 font-semibold text-white"><x-icon name="store" size="w-3.5 h-3.5" /> {{ $currentBranch?->name ?? 'All Branches' }}</span>
                    — {{ $k['orders'][0] }} orders so far today.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('pos.access')<a href="{{ route('pos.index') }}" class="inline-flex h-btn items-center gap-2 rounded-lg bg-white px-4 text-sm font-semibold text-primary shadow-sm hover:bg-indigo-50"><x-icon name="shopping-cart" size="w-[18px] h-[18px]" /> New Sale</a>@endcan
                @can('purchases.create')<a href="{{ route('purchases.create') }}" class="inline-flex h-btn items-center gap-2 rounded-lg bg-white/15 px-4 text-sm font-semibold text-white ring-1 ring-white/25 hover:bg-white/25"><x-icon name="truck" size="w-[18px] h-[18px]" /> New Purchase</a>@endcan
                @can('reports.view')<a href="{{ route('reports.index') }}" class="inline-flex h-btn items-center gap-2 rounded-lg bg-white/15 px-4 text-sm font-semibold text-white ring-1 ring-white/25 hover:bg-white/25"><x-icon name="bar-chart" size="w-[18px] h-[18px]" /> Reports</a>@endcan
            </div>
        </div>
    </section>

    {{-- KPI rows --}}
    <div @class(['mb-4 grid grid-cols-2 gap-4 xl:gap-6', 'xl:grid-cols-4' => count($kpis) === 4, 'xl:grid-cols-3' => count($kpis) === 3])>
        @foreach ($kpis as [$label, $value, $icon, $color, $trend, $href])
            <x-stat-card :label="$label" :value="$value" :icon="$icon" :color="$color" :trend="$trend" :href="$href" />
        @endforeach
    </div>
    <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4 xl:gap-6">
        <x-stat-card label="Customer due (receivable)" :value="money($b['receivable'])" icon="users" color="danger" :href="route('customers.index', ['filter' => 'due'])" />
        <x-stat-card label="Supplier payable" :value="money($b['payable'])" icon="factory" color="warning" :href="route('suppliers.index', ['filter' => 'payable'])" />
        <x-stat-card label="Stock value (cost)" :value="money(round($b['stock_value']))" icon="boxes" color="info" :href="route('stock.index')" />
        <x-stat-card label="Low stock items" :value="number_format($b['low_stock'])" icon="alert-triangle" color="danger" :href="route('stock.index', ['stock' => 'low'])" />
    </div>

    {{-- Sales & profit --}}
    @php
        $profitSeries = fn ($set) => $canProfit ? [['name' => 'Sales', 'data' => $set['sales']], ['name' => 'Profit', 'data' => $set['profit']]] : [['name' => 'Sales', 'data' => $set['sales']]];
        $ranges = [
            '7D' => ['labels' => array_slice($m['daily']['labels'], -7), 'series' => $profitSeries(['sales' => array_slice($m['daily']['sales'], -7), 'profit' => array_slice($m['daily']['profit'], -7)])],
            '30D' => ['labels' => $m['daily']['labels'], 'series' => $profitSeries($m['daily'])],
            '12M' => ['labels' => $m['monthly']['labels'], 'series' => $profitSeries($m['monthly'])],
        ];
    @endphp
    <div class="mb-6 grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 xl:col-span-2" x-data="{ range: '30D', ranges: @js($ranges), set(r) { this.range = r; window.dispatchEvent(new CustomEvent('sales-range', { detail: this.ranges[r] })) } }">
        <x-card :title="$canProfit ? 'Sales & Profit' : 'Sales'" class="h-full" padding="px-3 pb-3 pt-2">
            <x-slot name="actions">
                <div class="flex rounded-lg border border-line p-1 text-xs font-semibold">
                    @foreach (['7D', '30D', '12M'] as $r)
                        <button type="button" @click="set('{{ $r }}')" class="rounded-md px-2.5 py-1" :class="range === '{{ $r }}' ? 'bg-primary text-white' : 'text-muted hover:text-heading'">{{ $r }}</button>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('dashboard.refresh') }}">@csrf<button class="rounded-lg p-1.5 text-muted hover:bg-subtle hover:text-heading" title="Refresh (cached 5 min, updated {{ \Illuminate\Support\Carbon::parse($m['generated_at'])->diffForHumans() }})"><x-icon name="rotate-ccw" size="w-4 h-4" /></button></form>
            </x-slot>
            <div class="h-[320px]" x-data="apexChart({
                chart: { type: 'area', height: 320 },
                series: @js($ranges['30D']['series']),
                xaxis: { categories: @js($ranges['30D']['labels']), axisBorder: { show: false }, axisTicks: { show: false }, tickAmount: 10 },
                stroke: { curve: 'smooth', width: 2.5 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: 0, stops: [0, 95] } },
                legend: { position: 'top', horizontalAlign: 'right' },
                moneyTooltip: true,
            })" @sales-range.window="chart.updateOptions({ xaxis: { categories: $event.detail.labels } }); chart.updateSeries($event.detail.series)"></div>
        </x-card>
        </div>

        <x-card title="Payment methods" subtitle="Last 30 days">
            @if ($m['methods']['values'])
                <div class="h-[300px]" x-data="apexChart({
                    chart: { type: 'donut', height: 300 }, series: @js($m['methods']['values']), labels: @js($m['methods']['labels']),
                    legend: { position: 'bottom' }, stroke: { width: 0 }, plotOptions: { pie: { donut: { size: '70%' } } },
                    tooltip: { y: { formatter: v => formatMoney(v) } },
                })"></div>
            @else
                <x-empty-state icon="coins" title="No payments yet" />
            @endif
        </x-card>
    </div>

    <div class="mb-6 grid gap-6 xl:grid-cols-3">
        @if ($m['branches'])
            <x-card title="Branch comparison" subtitle="This month" padding="px-3 pb-3 pt-2">
                <div class="h-[300px]" x-data="apexChart({
                    chart: { type: 'bar', height: 300 },
                    series: @js($canProfit ? [['name' => 'Sales', 'data' => array_column($m['branches'], 'sales')], ['name' => 'Profit', 'data' => array_column($m['branches'], 'profit')]] : [['name' => 'Sales', 'data' => array_column($m['branches'], 'sales')]]),
                    xaxis: { categories: @js(array_column($m['branches'], 'name')) },
                    plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
                    legend: { position: 'top', horizontalAlign: 'right' }, moneyTooltip: true,
                })"></div>
            </x-card>
        @endif
        <x-card title="Top 10 products" subtitle="Revenue, last 30 days" :class="$m['branches'] ? '' : 'xl:col-span-2'" padding="px-3 pb-3 pt-2">
            @if ($m['top_products']['values'])
                <div class="h-[300px]" x-data="apexChart({
                    chart: { type: 'bar', height: 300 }, series: [{ name: 'Revenue', data: @js($m['top_products']['values']) }],
                    xaxis: { categories: @js($m['top_products']['labels']), labels: { formatter: v => v >= 1000 ? Math.round(v / 1000) + 'k' : v } },
                    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '65%' } },
                    tooltip: { y: { formatter: v => formatMoney(v) } }, colors: ['#4F46E5'],
                })"></div>
            @else
                <x-empty-state icon="package" title="No sales yet" />
            @endif
        </x-card>
        <x-card title="Sales by category" subtitle="Last 30 days">
            @if ($m['categories']['values'])
                <div class="h-[300px]" x-data="apexChart({
                    chart: { type: 'donut', height: 300 }, series: @js($m['categories']['values']), labels: @js($m['categories']['labels']),
                    legend: { position: 'bottom' }, stroke: { width: 0 }, plotOptions: { pie: { donut: { size: '68%' } } },
                    tooltip: { y: { formatter: v => formatMoney(v) } },
                })"></div>
            @else
                <x-empty-state icon="folder" title="No sales yet" />
            @endif
        </x-card>
    </div>

    <div class="mb-6 grid gap-6 xl:grid-cols-3">
        <x-card title="Busiest hours" subtitle="Orders by hour, last 30 days" class="xl:col-span-2" padding="px-3 pb-3 pt-2">
            <div class="h-[260px]" x-data="apexChart({
                chart: { type: 'bar', height: 260 }, series: [{ name: 'Orders', data: @js($m['hourly']['orders']) }],
                xaxis: { categories: @js($m['hourly']['labels']) }, plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } }, colors: ['#0EA5E9'],
            })"></div>
        </x-card>
        <x-card title="Top customers" subtitle="Last 30 days" padding="p-0">
            <ul class="divide-y divide-line">
                @forelse ($m['top_customers'] as $c)
                    <li class="flex items-center gap-3 px-6 py-3">
                        <x-avatar :name="$c['name']" size="sm" />
                        <a href="{{ route('customers.show', $c['id']) }}" class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-heading hover:text-primary">{{ $c['name'] }}</span><span class="text-xs text-muted">{{ $c['orders'] }} orders{{ $c['balance'] > 0 ? ' · due '.money($c['balance']) : '' }}</span></a>
                        <span class="text-sm font-semibold tabular text-heading">{{ money($c['total']) }}</span>
                    </li>
                @empty
                    <li><x-empty-state icon="users" title="No customer sales yet" /></li>
                @endforelse
            </ul>
        </x-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Recent sales" class="xl:col-span-2" padding="p-0">
            <x-slot name="actions"><a href="{{ route('sales.index') }}" class="text-sm font-semibold text-primary">View all</a></x-slot>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Invoice</th><th>Time</th><th>Customer</th><th class="text-right">Total</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($recentSales as $s)
                            @php [$c, $l] = $s->payment_badge; @endphp
                            <tr>
                                <td><a href="{{ route('sales.show', $s) }}" class="font-semibold tabular text-heading hover:text-primary">{{ $s->invoice_no }}</a></td>
                                <td class="whitespace-nowrap text-muted">{{ $s->sold_at->diffForHumans() }}</td>
                                <td>{{ $s->customer?->name ?? 'Walk-in' }}</td>
                                <td class="text-right font-semibold tabular text-heading">{{ money($s->grand_total) }}</td>
                                <td><x-badge :color="$c" dot>{{ $l }}</x-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state icon="receipt" title="No sales yet" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
        <div class="space-y-6">
            <x-card title="Low stock" padding="p-0">
                <x-slot name="actions"><a href="{{ route('stock.index', ['stock' => 'low']) }}" class="text-sm font-semibold text-primary">All</a></x-slot>
                <ul class="divide-y divide-line">
                    @forelse ($lowStock as $row)
                        @php $pct = max(3, min(100, (float) $row->quantity / max((float) $row->product->min_stock, 1) * 100)); @endphp
                        <li class="px-6 py-3">
                            <div class="flex justify-between gap-3 text-sm"><span class="truncate font-medium text-heading">{{ $row->product->name }}</span>
                                <span class="shrink-0 font-semibold tabular {{ (float) $row->quantity <= 0 ? 'text-danger' : 'text-amber-600' }}">{{ $row->product->formatQty($row->quantity) }} / {{ $row->product->formatQty($row->product->min_stock) }} <span class="text-xs text-muted">{{ $row->branch->code }}</span></span></div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-line"><div class="h-full rounded-full {{ (float) $row->quantity <= 0 ? 'bg-danger' : 'bg-warning' }}" style="width: {{ $pct }}%"></div></div>
                        </li>
                    @empty
                        <li><x-empty-state icon="check-circle" title="All stocked up" /></li>
                    @endforelse
                </ul>
            </x-card>
            <x-card title="Incoming transfers" padding="p-0">
                <ul class="divide-y divide-line">
                    @forelse ($incoming as $t)
                        <li><a href="{{ route('transfers.show', $t) }}" class="flex items-center justify-between px-6 py-3 hover:bg-subtle">
                            <span><span class="font-semibold tabular text-heading">{{ $t->transfer_no }}</span><span class="block text-xs text-muted">{{ $t->fromBranch->code }} → {{ $t->toBranch->code }} · {{ $t->items_count }} items</span></span>
                            <x-badge color="info" dot>In transit</x-badge></a></li>
                    @empty
                        <li class="px-6 py-6 text-center text-sm text-muted">Nothing on the way.</li>
                    @endforelse
                </ul>
            </x-card>
            <x-card title="Expiring soon" padding="p-0">
                <x-slot name="actions"><a href="{{ route('expiry.index') }}" class="text-sm font-semibold text-primary">All</a></x-slot>
                <ul class="divide-y divide-line">
                    @forelse ($expiring as $e)
                        <li class="flex items-center justify-between gap-3 px-6 py-3 text-sm">
                            <span class="min-w-0"><span class="block truncate font-medium text-heading">{{ $e->name }}</span><span class="text-xs text-muted">{{ $e->branch_code }} · {{ $e->batch_no ?: 'batch' }}</span></span>
                            <span class="shrink-0 text-xs font-semibold {{ $e->expired ? 'text-danger' : 'text-amber-600' }}">{{ $e->expired ? 'Expired' : 'in '.$e->days_left.'d' }}</span>
                        </li>
                    @empty
                        <li class="px-6 py-6 text-center text-sm text-muted">Nothing expiring soon.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>
