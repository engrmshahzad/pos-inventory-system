<x-app-layout title="Sale Returns">
    <x-page-header title="Sale Returns" subtitle="Goods brought back by customers." :breadcrumbs="['Sales' => null, 'Sale Returns' => null]">
        <x-button :href="route('sale-returns.create')" icon="undo">New Return</x-button>
    </x-page-header>
    <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat-card label="Returns" :value="number_format($summary->count)" icon="undo" />
        <x-stat-card label="Total returned" :value="money($summary->total)" icon="wallet" color="warning" />
        <x-stat-card label="Adjusted in khata" :value="money($summary->adjusted)" icon="file-text" color="info" />
        <x-stat-card label="Returned today" :value="money($today)" icon="calendar" color="danger" />
    </div>
    <x-table :paginator="$returns">
        <x-slot name="toolbar">
            <form method="GET" class="relative w-full lg:max-w-sm">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Return or invoice no…" class="form-control pl-10" aria-label="Search">
            </form>
        </x-slot>
        <x-slot name="head"><tr><th>Return</th><th>Date</th><th>Invoice</th><th>Customer</th><th>Branch</th><th class="text-right">Items</th><th class="text-right">Amount</th><th>Refund</th><th>By</th></tr></x-slot>
        @foreach ($returns as $r)
            <tr>
                <td><a href="{{ route('sale-returns.show', $r) }}" class="font-semibold tabular text-heading hover:text-primary">{{ $r->return_no }}</a></td>
                <td class="whitespace-nowrap">{{ fmtDate($r->returned_at, true) }}</td>
                <td><a href="{{ route('sales.show', $r->sale_id) }}" class="tabular text-primary hover:text-primary-hover">{{ $r->sale->invoice_no }}</a></td>
                <td>{{ $r->customer?->name ?? 'Walk-in' }}</td>
                <td><x-badge color="slate">{{ $r->branch->code }}</x-badge></td>
                <td class="text-right tabular">{{ $r->items_count }}</td>
                <td class="text-right font-semibold tabular text-heading">{{ money($r->total, true) }}</td>
                <td><x-badge :color="$r->refund_method === 'adjust' ? 'info' : 'warning'">{{ $r->refund_label }}</x-badge></td>
                <td>{{ $r->user->name }}</td>
            </tr>
        @endforeach
        @if ($returns->isEmpty())<x-slot name="empty"><x-empty-state icon="undo" title="No returns yet"><x-button :href="route('sale-returns.create')" icon="undo">New Return</x-button></x-empty-state></x-slot>
@endif
    </x-table>
</x-app-layout>
