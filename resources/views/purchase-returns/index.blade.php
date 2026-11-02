<x-app-layout title="Purchase Returns">
    <x-page-header title="Purchase Returns" subtitle="Goods sent back to suppliers." :breadcrumbs="['Purchases' => null, 'Purchase Returns' => null]">
        <x-button :href="route('purchase-returns.create')" icon="rotate-ccw">New Return</x-button>
    </x-page-header>
    <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-3">
        <x-stat-card label="Returns" :value="number_format($summary->count)" icon="rotate-ccw" />
        <x-stat-card label="Total value returned" :value="money($summary->total)" icon="wallet" color="warning" />
        <x-stat-card label="This month" :value="money($thisMonth)" icon="calendar" color="info" />
    </div>
    <x-table :paginator="$returns">
        <x-slot name="toolbar">
            <form method="GET" class="relative w-full lg:max-w-sm">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Return or purchase no…" class="form-control pl-10" aria-label="Search">
            </form>
        </x-slot>
        <x-slot name="head"><tr><th>Return</th><th>Date</th><th>Purchase</th><th>Supplier</th><th>Branch</th><th class="text-right">Items</th><th class="text-right">Value</th><th>Settled by</th></tr></x-slot>
        @foreach ($returns as $r)
            <tr>
                <td><a href="{{ route('purchase-returns.show', $r) }}" class="font-semibold tabular text-heading hover:text-primary">{{ $r->return_no }}</a></td>
                <td class="whitespace-nowrap">{{ fmtDate($r->returned_at) }}</td>
                <td><a href="{{ route('purchases.show', $r->purchase_id) }}" class="tabular text-primary">{{ $r->purchase->purchase_no }}</a></td>
                <td>{{ $r->supplier->name }}</td>
                <td><x-badge color="slate">{{ $r->branch->code }}</x-badge></td>
                <td class="text-right tabular">{{ $r->items_count }}</td>
                <td class="text-right font-semibold tabular text-heading">{{ money($r->total, true) }}</td>
                <td><x-badge :color="$r->settle_method === 'adjust' ? 'info' : 'success'">{{ $r->settle_label }}</x-badge></td>
            </tr>
        @endforeach
        @if ($returns->isEmpty())<x-slot name="empty"><x-empty-state icon="rotate-ccw" title="No purchase returns yet" /></x-slot>
@endif
    </x-table>
</x-app-layout>
