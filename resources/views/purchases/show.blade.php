<x-app-layout :title="$purchase->purchase_no">
    @php [$sc, $sl] = $purchase->status_badge; [$pc, $pl] = $purchase->payment_badge; $received = $purchase->status === 'received'; @endphp
    <x-page-header :title="$purchase->purchase_no" :subtitle="$purchase->purchase_date->format('d M Y').' · '.$purchase->supplier->name.' · '.$purchase->branch->name"
        :breadcrumbs="['Purchases' => route('purchases.index'), $purchase->purchase_no => null]">
        <x-button :href="route('purchases.pdf', $purchase)" target="_blank" variant="outline" icon="file-text">A4 PDF</x-button>
        @if (! $received)
            @can('purchases.edit')
                <form method="POST" action="{{ route('purchases.destroy', $purchase) }}" data-confirm="Delete order {{ $purchase->purchase_no }}?">
                    @csrf @method('DELETE')
                    <x-button type="submit" variant="ghost-danger" icon="trash">Delete</x-button>
                </form>
                <form method="POST" action="{{ route('purchases.receive', $purchase) }}" data-confirm="Mark as received?" data-confirm-text="Stock will be added to {{ $purchase->branch->name }}." data-confirm-button="Receive" data-confirm-danger="false">
                    @csrf
                    <x-button type="submit" variant="success" icon="check" loading>Mark as received</x-button>
                </form>
            @endcan
        @else
            @if ((float) $purchase->due_amount > 0)
                @can('payments.create')<x-button :href="route('payments.create', ['type' => 'pay', 'supplier' => $purchase->supplier_id])" variant="success" icon="banknote">Pay supplier</x-button>@endcan
            @endif
            @can('purchase_returns.create')<x-button :href="route('purchase-returns.create', ['purchase' => $purchase->purchase_no])" variant="outline" icon="rotate-ccw">Return items</x-button>@endcan
        @endif
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card overflow-hidden xl:col-span-2">
            <div class="grid gap-4 border-b border-line p-6 sm:grid-cols-4">
                <div><p class="section-label">Supplier</p><a href="{{ route('suppliers.show', $purchase->supplier) }}" class="mt-1 block font-semibold text-heading hover:text-primary">{{ $purchase->supplier->name }}</a><p class="text-sm text-muted">{{ $purchase->supplier->company }}</p></div>
                <div><p class="section-label">Supplier ref</p><p class="mt-1 font-semibold text-heading">{{ $purchase->reference_no ?: '—' }}</p></div>
                <div><p class="section-label">Status</p><div class="mt-1 flex gap-1.5"><x-badge :color="$sc" dot>{{ $sl }}</x-badge><x-badge :color="$pc">{{ $pl }}</x-badge></div>
                    @if ($purchase->received_at)<p class="mt-1 text-xs text-muted">Received {{ $purchase->received_at->format('d M, h:i A') }}</p>@endif</div>
                <div><p class="section-label">Entered by</p><p class="mt-1 font-semibold text-heading">{{ $purchase->user->name }}</p></div>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Cost</th><th class="text-right">Disc.</th><th class="text-right">Tax</th><th class="text-right">Total</th>@if ($received)<th class="text-right">Returned</th>@endif</tr></thead>
                    <tbody>
                        @foreach ($purchase->items as $item)
                            @php $line = $lines->first(fn ($l) => $l['item']->id === $item->id); @endphp
                            <tr>
                                <td>
                                    <span class="font-medium text-heading">{{ $item->product_name }}</span>
                                    <span class="block text-xs text-muted">
                                        {{ $item->product?->sku }}
                                        @if ($item->new_sale_price !== null) · new price {{ money($item->new_sale_price) }} @endif
                                        @if ($item->expiry_date) · exp {{ $item->expiry_date->format('d M Y') }} @endif
                                        @if ($item->batch_no) · batch {{ $item->batch_no }} @endif
                                    </span>
                                </td>
                                <td class="text-right tabular">{{ $item->product?->formatQty($item->quantity) }} <span class="text-xs text-muted">{{ $item->product?->unit?->short_name }}</span></td>
                                <td class="text-right tabular">{{ money($item->unit_cost, true) }}</td>
                                <td class="text-right tabular text-muted">{{ (float) $item->discount > 0 ? money($item->discount, true) : '—' }}</td>
                                <td class="text-right tabular text-muted">{{ (float) $item->tax_amount > 0 ? money($item->tax_amount, true) : '—' }}</td>
                                <td class="text-right font-semibold tabular text-heading">{{ money($item->line_total, true) }}</td>
                                @if ($received)<td class="text-right tabular {{ ($line['returned'] ?? 0) > 0 ? 'text-warning' : 'text-muted' }}">{{ $item->product?->formatQty($line['returned'] ?? 0) }}</td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end border-t border-line p-6">
                <dl class="w-full max-w-xs space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd class="tabular">{{ money($purchase->subtotal, true) }}</dd></div>
                    @if ((float) $purchase->discount > 0)<div class="flex justify-between"><dt class="text-muted">Discount</dt><dd class="tabular">-{{ money($purchase->discount, true) }}</dd></div>@endif
                    @if ((float) $purchase->tax_amount > 0)<div class="flex justify-between"><dt class="text-muted">Tax</dt><dd class="tabular">{{ money($purchase->tax_amount, true) }}</dd></div>@endif
                    @if ((float) $purchase->shipping > 0)<div class="flex justify-between"><dt class="text-muted">Shipping</dt><dd class="tabular">{{ money($purchase->shipping, true) }}</dd></div>@endif
                    <div class="flex justify-between border-t border-line pt-2 text-base font-bold"><dt class="text-heading">Grand total</dt><dd class="tabular text-primary dark:text-primary-300">{{ money($purchase->grand_total, true) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Paid</dt><dd class="tabular text-emerald-600 dark:text-emerald-400">{{ money($purchase->paid_amount, true) }}</dd></div>
                    <div class="flex justify-between font-semibold"><dt class="text-heading">Due</dt><dd class="tabular {{ (float) $purchase->due_amount > 0 ? 'text-danger' : '' }}">{{ money($purchase->due_amount, true) }}</dd></div>
                </dl>
            </div>
            @if ($purchase->note)<p class="border-t border-line px-6 py-4 text-sm"><span class="font-semibold text-heading">Note:</span> {{ $purchase->note }}</p>@endif
        </div>

        <div class="space-y-6">
            <x-card title="Payments" padding="p-0">
                <ul class="divide-y divide-line">
                    @foreach ($purchase->payments as $p)
                        <li class="flex items-center justify-between gap-3 px-6 py-3.5"><div><a href="{{ route('payments.show', $p) }}" class="font-medium text-heading hover:text-primary">{{ $p->payment_no }}</a><p class="text-xs text-muted">{{ $p->paid_at->format('d M Y') }} · {{ $p->method_label }} · at purchase</p></div><span class="font-semibold tabular">{{ money($p->amount, true) }}</span></li>
                    @endforeach
                    @foreach ($purchase->allocations as $a)
                        <li class="flex items-center justify-between gap-3 px-6 py-3.5"><div><a href="{{ route('payments.show', $a->payment_id) }}" class="font-medium text-heading hover:text-primary">{{ $a->payment->payment_no }}</a><p class="text-xs text-muted">{{ $a->payment->paid_at->format('d M Y') }} · {{ $a->payment->method_label }}</p></div><span class="font-semibold tabular">{{ money($a->amount, true) }}</span></li>
                    @endforeach
                    @if ($purchase->payments->isEmpty() && $purchase->allocations->isEmpty())
                        <li><x-empty-state icon="wallet" title="Nothing paid yet" /></li>
                    @endif
                </ul>
            </x-card>
            @if ($purchase->returns->isNotEmpty())
                <x-card title="Returns" padding="p-0">
                    <ul class="divide-y divide-line">
                        @foreach ($purchase->returns as $r)
                            <li class="flex items-center justify-between px-6 py-3.5"><div><a href="{{ route('purchase-returns.show', $r) }}" class="font-medium text-heading hover:text-primary">{{ $r->return_no }}</a><p class="text-xs text-muted">{{ $r->returned_at->format('d M Y') }} · {{ $r->settle_label }}</p></div><span class="font-semibold tabular text-warning">{{ money($r->total, true) }}</span></li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
