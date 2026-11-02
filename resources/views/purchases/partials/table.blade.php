<x-table :paginator="$purchases">
    <x-slot name="head">
        <tr><th>Purchase</th><th>Date</th>@if ($showSupplier)<th>Supplier</th>@endif<th>Branch</th><th class="text-right">Items</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Due</th><th>Status</th><th>Payment</th></tr>
    </x-slot>
    @foreach ($purchases as $purchase)
        @php [$sc, $sl] = $purchase->status_badge; [$pc, $pl] = $purchase->payment_badge; @endphp
        <tr>
            <td>
                <a href="{{ route('purchases.show', $purchase) }}" class="font-semibold tabular text-heading hover:text-primary">{{ $purchase->purchase_no }}</a>
                @if ($purchase->reference_no)<span class="block text-xs text-muted">Ref {{ $purchase->reference_no }}</span>@endif
            </td>
            <td class="whitespace-nowrap">{{ fmtDate($purchase->purchase_date) }}</td>
            @if ($showSupplier)<td><span class="font-medium text-heading">{{ $purchase->supplier->name }}</span><span class="block text-xs text-muted">{{ $purchase->supplier->company }}</span></td>@endif
            <td><x-badge color="slate">{{ $purchase->branch->code }}</x-badge></td>
            <td class="text-right tabular">{{ $purchase->items_count }}</td>
            <td class="text-right font-semibold tabular text-heading">{{ money($purchase->grand_total) }}</td>
            <td class="text-right tabular">{{ money($purchase->paid_amount) }}</td>
            <td @class(['text-right tabular', 'font-semibold text-danger' => (float) $purchase->due_amount > 0 && $purchase->status === 'received'])>{{ money($purchase->due_amount) }}</td>
            <td><x-badge :color="$sc" dot>{{ $sl }}</x-badge></td>
            <td><x-badge :color="$pc">{{ $pl }}</x-badge></td>
        </tr>
    @endforeach
    @if ($purchases->isEmpty())
        <x-slot name="empty"><x-empty-state icon="truck" title="No purchases found" /></x-slot>
    @endif
</x-table>
