{{-- Shared by the product page (history tab) and the stock movements page. --}}
<x-table :paginator="$movements">
    <x-slot name="head">
        <tr>
            <th>Date</th>
            @if ($showProduct)<th>Product</th>@endif
            <th>Branch</th><th>Type</th><th class="text-right">Change</th><th class="text-right">Balance</th><th>Reference</th><th>By</th>
        </tr>
    </x-slot>
    @foreach ($movements as $movement)
        @php $p = $movement->product; @endphp
        <tr>
            <td class="whitespace-nowrap">
                <p class="text-heading">{{ fmtDate($movement->created_at) }}</p>
                <p class="text-xs text-muted tabular">{{ $movement->created_at->format('h:i A') }}</p>
            </td>
            @if ($showProduct)
                <td>
                    @if ($p)
                        <a href="{{ route('products.show', $p) }}" class="font-medium text-heading hover:text-primary">{{ $p->name }}</a>
                        <p class="text-xs text-muted">{{ $p->sku }}</p>
                    @endif
                </td>
            @endif
            <td><x-badge color="slate">{{ $movement->branch?->code }}</x-badge></td>
            <td><x-badge :color="$movement->type_color">{{ $movement->type_label }}</x-badge></td>
            <td @class(['text-right tabular font-semibold', 'text-emerald-600 dark:text-emerald-400' => $movement->quantity > 0, 'text-danger' => $movement->quantity < 0])>
                {{ $movement->quantity > 0 ? '+' : '−' }}{{ $p ? $p->formatQty(abs((float) $movement->quantity)) : abs((float) $movement->quantity) }}
            </td>
            <td class="text-right tabular text-heading">{{ $p ? $p->formatQty($movement->balance_after) : $movement->balance_after }}</td>
            <td>
                <p class="text-heading tabular">{{ $movement->reference_no ?? '—' }}</p>
                @if ($movement->note)<p class="text-xs text-muted">{{ $movement->note }}</p>@endif
            </td>
            <td class="whitespace-nowrap">{{ $movement->user?->name ?? 'System' }}</td>
        </tr>
    @endforeach
    @if ($movements->isEmpty())
        <x-slot name="empty"><x-empty-state icon="clock" title="No stock movements" description="Purchases, sales, transfers and adjustments will appear here." /></x-slot>
    @endif
</x-table>
