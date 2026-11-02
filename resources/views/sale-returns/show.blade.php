<x-app-layout :title="$return->return_no">
    <x-page-header :title="$return->return_no" :subtitle="fmtDate($return->returned_at, true).' · '.$return->branch->name" :breadcrumbs="['Sales' => null, 'Sale Returns' => route('sale-returns.index'), $return->return_no => null]">
        <x-button :href="route('sale-returns.receipt', $return)" target="_blank" variant="outline" icon="printer">Print receipt</x-button>
        <x-button :href="route('sale-returns.create')" icon="undo">New Return</x-button>
    </x-page-header>
    <div class="grid gap-6 xl:grid-cols-3">
        <x-card class="xl:col-span-2" padding="p-0">
            <div class="grid gap-4 border-b border-line p-6 sm:grid-cols-4">
                <div><p class="section-label">Original sale</p><a href="{{ route('sales.show', $return->sale) }}" class="mt-1 block font-semibold tabular text-primary">{{ $return->sale->invoice_no }}</a></div>
                <div><p class="section-label">Customer</p><p class="mt-1 font-semibold text-heading">{{ $return->customer?->name ?? 'Walk-in' }}</p></div>
                <div><p class="section-label">Refund</p><p class="mt-1"><x-badge :color="$return->refund_method === 'adjust' ? 'info' : 'warning'">{{ $return->refund_label }}</x-badge></p></div>
                <div><p class="section-label">Processed by</p><p class="mt-1 font-semibold text-heading">{{ $return->user->name }}</p></div>
            </div>
            <table class="data-table">
                <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Refund / unit</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    @foreach ($return->items as $item)
                        <tr><td class="font-medium text-heading">{{ $item->product_name }}</td><td class="text-right tabular">{{ $item->product?->formatQty($item->quantity) }}</td><td class="text-right tabular">{{ money($item->unit_price, true) }}</td><td class="text-right font-semibold tabular text-heading">{{ money($item->total, true) }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="bg-subtle"><td colspan="3" class="px-4 py-3 font-semibold text-heading">Total refund</td><td class="px-4 py-3 text-right text-lg font-bold tabular text-primary dark:text-primary-300">{{ money($return->total, true) }}</td></tr></tfoot>
            </table>
            @if ($return->reason)<p class="border-t border-line px-6 py-4 text-sm"><span class="font-semibold text-heading">Reason:</span> {{ $return->reason }}</p>@endif
        </x-card>
        <x-card title="What happened">
            <ul class="space-y-3 text-sm">
                <li class="flex gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-success/10 text-emerald-600"><x-icon name="boxes" size="w-4 h-4" /></span><span>Items added back to <b class="text-heading">{{ $return->branch->name }}</b> stock.</span></li>
                <li class="flex gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><x-icon name="wallet" size="w-4 h-4" /></span>
                    <span>{{ $return->refund_method === 'adjust' ? money($return->total, true).' taken off '.$return->customer?->name."'s khata." : money($return->total, true).' paid back by '.\App\Models\Payment::METHODS[$return->refund_method].'.' }}</span></li>
            </ul>
        </x-card>
    </div>
</x-app-layout>
