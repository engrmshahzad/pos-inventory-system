<x-app-layout title="Expiry">
    <x-page-header title="Expiry tracking" :subtitle="'Batches from received purchases'.($currentBranch ? ' at '.$currentBranch->name : ' across all branches').' that are expired or expire within '.$days.' days.'"
        :breadcrumbs="['Inventory' => null, 'Expiry' => null]">
        <form method="GET" class="flex items-center gap-2">
            <label for="days" class="text-sm text-muted">Warn within</label>
            <select id="days" name="days" class="form-control h-10 w-28" onchange="this.form.submit()">
                @foreach ([7, 15, 30, 60, 90] as $d)<option value="{{ $d }}" @selected($days == $d)>{{ $d }} days</option>@endforeach
            </select>
        </form>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat-card label="Expired batches" :value="$expired->count()" icon="x-circle" color="danger" />
        <x-stat-card label="Expired stock value" :value="money($expired->sum('value'))" icon="wallet" color="danger" hint="Suggested qty × cost" />
        <x-stat-card label="Expiring soon" :value="$soon->count()" icon="clock" color="warning" />
        <x-stat-card label="At risk value" :value="money($soon->sum('value'))" icon="alert-triangle" color="warning" />
    </div>

    <div class="mb-4 rounded-xl border border-line bg-subtle px-4 py-3 text-xs text-body">
        <b class="text-heading">How the quantity is estimated:</b> the till does not record which batch was sold, so the suggested quantity is the smaller of the batch received and the branch's current stock. Check the shelf and edit it on the adjustment.
    </div>

    @foreach (['expired' => ['Expired', $expired, 'danger'], 'soon' => ['Expiring soon', $soon, 'warning']] as $key => [$title, $rows, $color])
        <x-card :title="$title" class="mb-6" padding="p-0">
            <x-slot name="actions"><x-badge :color="$color">{{ $rows->count() }}</x-badge></x-slot>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Product</th><th>Branch</th><th>Batch</th><th>Expiry</th><th class="text-right">Batch qty</th><th class="text-right">Branch stock</th><th class="text-right">Value at risk</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($rows as $r)
                            <tr>
                                <td><span class="font-medium text-heading">{{ $r->name }}</span><span class="block text-xs text-muted">{{ $r->sku }} · <a href="{{ route('purchases.show', $r->purchase_id) }}" class="text-primary">{{ $r->purchase_no }}</a></span></td>
                                <td><x-badge color="slate">{{ $r->branch_code }}</x-badge></td>
                                <td class="tabular">{{ $r->batch_no ?: '—' }}</td>
                                <td class="whitespace-nowrap">
                                    {{ fmtDate($r->expiry_date) }}
                                    <span @class(['block text-xs font-semibold', 'text-danger' => $r->expired, 'text-amber-600' => ! $r->expired])>{{ $r->expired ? abs($r->days_left).' days ago' : ($r->days_left === 0 ? 'Today' : 'in '.$r->days_left.' days') }}</span>
                                </td>
                                <td class="text-right tabular">{{ rtrim(rtrim(number_format((float) $r->batch_qty, 3), '0'), '.') }}</td>
                                <td class="text-right tabular">{{ rtrim(rtrim(number_format((float) $r->stock, 3), '0'), '.') }}</td>
                                <td class="text-right font-semibold tabular">{{ money($r->value) }}</td>
                                <td class="text-right">
                                    @can('stock.adjust')
                                        @if ($currentBranch?->id === $r->branch_id)
                                            <x-button size="sm" :variant="$r->expired ? 'danger' : 'outline'" icon="trash"
                                                :href="route('adjustments.create', ['reason' => 'expired', 'source' => 'expiry', 'products' => [$r->product_id => -$r->suggested], 'note' => 'Batch '.($r->batch_no ?: $r->purchase_no).' exp '.fmtDate($r->expiry_date)])">Write off</x-button>
                                        @else
                                            <span class="text-xs text-muted">Switch to {{ $r->branch_code }} to write off</span>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-empty-state icon="check-circle" :title="$key === 'expired' ? 'Nothing expired' : 'Nothing expiring soon'" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    @endforeach
</x-app-layout>
