<x-app-layout :title="$adjustment->adjustment_no">
    @php [$label, $color] = $adjustment->status_badge; $canReview = $adjustment->status === 'pending' && auth()->user()->isOwner() && auth()->user()->can('adjustments.approve'); @endphp
    <x-page-header :title="$adjustment->adjustment_no" :subtitle="$adjustment->reason_label.' · '.$adjustment->branch->name.' · '.$adjustment->adjustment_date->format('d M Y')"
        :breadcrumbs="['Inventory' => null, 'Adjustments' => route('adjustments.index'), $adjustment->adjustment_no => null]">
        @if ($canReview)
            <x-button variant="ghost-danger" icon="x" @click="$dispatch('open-modal', 'reject')">Reject</x-button>
            <form method="POST" action="{{ route('adjustments.approve', $adjustment) }}" data-confirm="Approve {{ $adjustment->adjustment_no }}?" data-confirm-text="Stock at {{ $adjustment->branch->name }} will change now." data-confirm-button="Approve" data-confirm-danger="false">
                @csrf<x-button type="submit" variant="success" icon="check" loading>Approve</x-button>
            </form>
        @endif
    </x-page-header>

    @if ($adjustment->status === 'pending')
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
            <x-icon name="clock" size="w-5 h-5 shrink-0" /> <span>Waiting for owner approval. Stock has <b>not</b> changed yet.</span>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card class="xl:col-span-2" padding="p-0">
            <x-slot name="title">Items</x-slot>
            <x-slot name="actions"><x-badge :color="$color" dot>{{ $label }}</x-badge></x-slot>
            <table class="data-table">
                <thead><tr><th>Product</th><th class="text-right">Change</th><th class="text-right">In stock now</th><th class="text-right">Cost</th><th class="text-right">Value</th><th>Note</th></tr></thead>
                <tbody>
                    @foreach ($adjustment->items as $item)
                        @php $p = $item->product; $q = (float) $item->quantity; @endphp
                        <tr>
                            <td><span class="font-medium text-heading">{{ $p?->name }}</span><span class="block text-xs text-muted">{{ $p?->sku }}</span></td>
                            <td class="text-right font-semibold tabular {{ $q < 0 ? 'text-danger' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $q > 0 ? '+' : '−' }}{{ $p?->formatQty(abs($q)) }}</td>
                            <td class="text-right tabular">{{ $p?->formatQty($stock[$item->product_id] ?? 0) }}</td>
                            <td class="whitespace-nowrap text-right tabular">{{ money($item->unit_cost, true) }}</td>
                            <td class="whitespace-nowrap text-right font-semibold tabular {{ (float) $item->value < 0 ? 'text-danger' : '' }}">{{ money($item->value, true) }}</td>
                            <td class="text-muted">{{ $item->note ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="bg-subtle"><td colspan="4" class="px-4 py-3 font-semibold text-heading">Net value impact</td><td class="whitespace-nowrap px-4 py-3 text-right text-lg font-bold tabular {{ (float) $adjustment->total_value < 0 ? 'text-danger' : 'text-emerald-600' }}">{{ money($adjustment->total_value, true) }}</td><td></td></tr></tfoot>
            </table>
        </x-card>
        <x-card title="Details">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-muted">Reason</dt><dd class="font-medium text-heading">{{ $adjustment->reason_label }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">Source</dt><dd class="font-medium text-heading">{{ ['manual' => 'Manual', 'stock_count' => 'Stock count', 'expiry' => 'Expiry list'][$adjustment->source] ?? $adjustment->source }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">Created by</dt><dd class="font-medium text-heading">{{ $adjustment->creator->name }}</dd></div>
                @if ($adjustment->approver)
                    <div class="flex justify-between"><dt class="text-muted">{{ $adjustment->status === 'rejected' ? 'Rejected' : 'Approved' }} by</dt><dd class="font-medium text-heading">{{ $adjustment->approver->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">On</dt><dd class="text-heading">{{ $adjustment->approved_at?->format('d M Y, h:i A') }}</dd></div>
                @endif
                @if ($adjustment->rejection_reason)<div><dt class="text-muted">Rejection reason</dt><dd class="text-danger">{{ $adjustment->rejection_reason }}</dd></div>@endif
                @if ($adjustment->note)<div><dt class="text-muted">Note</dt><dd class="text-heading">{{ $adjustment->note }}</dd></div>@endif
            </dl>
        </x-card>
    </div>

    @if ($canReview)
        <x-modal name="reject" title="Reject adjustment" maxWidth="md">
            <form id="reject-form" method="POST" action="{{ route('adjustments.reject', $adjustment) }}">
                @csrf
                <x-input name="rejection_reason" label="Reason" placeholder="Tell the manager why" />
            </form>
            <x-slot name="footer">
                <x-button variant="outline" @click="$dispatch('close-modal', 'reject')">Cancel</x-button>
                <x-button type="submit" form="reject-form" variant="danger" icon="x" loading>Reject</x-button>
            </x-slot>
        </x-modal>
    @endif
</x-app-layout>
