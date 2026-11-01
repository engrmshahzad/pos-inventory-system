<x-app-layout :title="$return->return_no">
    <x-page-header :title="$return->return_no" :subtitle="$return->returned_at->format('d M Y, h:i A').' · '.$return->branch->name" :breadcrumbs="['Purchases' => null, 'Purchase Returns' => route('purchase-returns.index'), $return->return_no => null]">
        <x-button :href="route('purchase-returns.create')" icon="rotate-ccw">New Return</x-button>
    </x-page-header>
    <x-card padding="p-0">
        <div class="grid gap-4 border-b border-line p-6 sm:grid-cols-4">
            <div><p class="section-label">Purchase</p><a href="{{ route('purchases.show', $return->purchase) }}" class="mt-1 block font-semibold tabular text-primary">{{ $return->purchase->purchase_no }}</a></div>
            <div><p class="section-label">Supplier</p><a href="{{ route('suppliers.show', $return->supplier) }}" class="mt-1 block font-semibold text-heading hover:text-primary">{{ $return->supplier->name }}</a></div>
            <div><p class="section-label">Settled by</p><p class="mt-1"><x-badge :color="$return->settle_method === 'adjust' ? 'info' : 'success'">{{ $return->settle_label }}</x-badge></p></div>
            <div><p class="section-label">Processed by</p><p class="mt-1 font-semibold text-heading">{{ $return->user->name }}</p></div>
        </div>
        <table class="data-table">
            <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Cost / unit</th><th class="text-right">Total</th></tr></thead>
            <tbody>
                @foreach ($return->items as $item)
                    <tr><td class="font-medium text-heading">{{ $item->product_name }}</td><td class="text-right tabular">{{ $item->product?->formatQty($item->quantity) }}</td><td class="text-right tabular">{{ money($item->unit_cost, true) }}</td><td class="text-right font-semibold tabular">{{ money($item->total, true) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr class="bg-subtle"><td colspan="3" class="px-4 py-3 font-semibold text-heading">Total</td><td class="px-4 py-3 text-right text-lg font-bold tabular text-primary dark:text-primary-300">{{ money($return->total, true) }}</td></tr></tfoot>
        </table>
        @if ($return->reason)<p class="border-t border-line px-6 py-4 text-sm"><span class="font-semibold text-heading">Reason:</span> {{ $return->reason }}</p>@endif
    </x-card>
</x-app-layout>
