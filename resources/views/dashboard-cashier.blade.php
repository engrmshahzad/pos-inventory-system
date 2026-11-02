<x-app-layout title="Dashboard">
    @php $user = auth()->user(); @endphp
    <section class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-r from-primary-600 to-violet-600 p-6 text-white shadow-lg shadow-primary/20 sm:p-8">
        <div class="hero-pattern absolute inset-0 opacity-50"></div>
        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-indigo-100">{{ now()->format('l, '.settings('date_format', 'd M Y')) }} · {{ $currentBranch?->name }}</p>
                <h1 class="mt-2 text-2xl font-bold text-white sm:text-3xl">{{ $greeting }}, {{ \Illuminate\Support\Str::before($user->name, ' ') }} 👋</h1>
            </div>
            <a href="{{ route('pos.index') }}" class="inline-flex h-16 items-center justify-center gap-3 rounded-2xl bg-white px-8 text-lg font-extrabold text-primary shadow-lg transition hover:scale-[1.02]">
                <x-icon name="shopping-cart" size="w-6 h-6" /> Open POS <kbd class="kbd">F2</kbd>
            </a>
        </div>
    </section>
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card label="My sales today" :value="money($stats->total)" icon="banknote" />
        <x-stat-card label="Orders today" :value="number_format($stats->orders)" icon="shopping-cart" color="info" />
        <x-stat-card label="Credit given today" :value="money($stats->credit)" icon="file-text" color="warning" />
    </div>
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="My shift">
            @if ($register)
                <p class="text-sm text-body">Open since <b class="text-heading">{{ $register->opened_at->format('h:i A') }}</b> with {{ money($register->opening_cash) }} opening cash.</p>
                <x-button :href="route('register.index')" variant="outline" icon="wallet" class="mt-4 w-full">View / close shift</x-button>
            @else
                <p class="text-sm text-muted">No open shift. You will be asked to open one when you start the POS.</p>
                <x-button :href="route('pos.index')" icon="power" class="mt-4 w-full">Open shift</x-button>
            @endif
        </x-card>
        <x-card title="My recent sales" class="lg:col-span-2" padding="p-0">
            <table class="data-table">
                <thead><tr><th>Invoice</th><th>Time</th><th>Customer</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    @forelse ($recent as $s)
                        <tr><td><a href="{{ route('sales.show', $s) }}" class="font-semibold tabular text-heading hover:text-primary">{{ $s->invoice_no }}</a></td><td>{{ $s->sold_at->format('h:i A') }}</td><td>{{ $s->customer?->name ?? 'Walk-in' }}</td><td class="text-right font-semibold tabular">{{ money($s->grand_total) }}</td></tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state icon="receipt" title="No sales yet today" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
    </div>
</x-app-layout>
