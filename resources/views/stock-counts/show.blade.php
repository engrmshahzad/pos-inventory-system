<x-app-layout :title="$count->count_no">
    @php [$label, $color] = $count->status_badge; $open = $count->status === 'in_progress'; @endphp
    <x-page-header :title="$count->count_no" :subtitle="$count->branch->name.' · '.($count->category?->name ?? 'All products').' · started '.$count->created_at->format('d M Y, h:i A')"
        :breadcrumbs="['Inventory' => null, 'Stock Count' => route('stock-counts.index'), $count->count_no => null]">
        <x-button :href="route('stock-counts.sheet', $count)" target="_blank" variant="outline" icon="printer">Print count sheet</x-button>
        @if ($open)
            <form method="POST" action="{{ route('stock-counts.cancel', $count) }}" data-confirm="Cancel this count?" data-confirm-text="Entered counts are discarded. Stock is not changed.">
                @csrf<x-button type="submit" variant="ghost-danger" icon="x">Cancel count</x-button>
            </form>
        @endif
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat-card label="Products" :value="$summary['total']" icon="package" />
        <x-stat-card label="Counted" :value="$summary['counted'].' / '.$summary['total']" icon="check-circle" color="success" />
        <x-stat-card label="Differences" :value="$summary['differences']" icon="alert-triangle" color="warning" />
        <x-stat-card label="Value of differences" :value="money($summary['value'])" icon="wallet" :color="$summary['value'] < 0 ? 'danger' : 'info'" />
    </div>

    @if ($count->adjustment)
        <div class="mb-6 flex items-center gap-3 rounded-xl border border-success/30 bg-success/10 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">
            <x-icon name="check-circle" size="w-5 h-5" /> Applied {{ $count->applied_at?->format('d M Y, h:i A') }} as
            <a href="{{ route('adjustments.show', $count->adjustment) }}" class="font-semibold underline">{{ $count->adjustment->adjustment_no }}</a>
            ({{ \App\Models\StockAdjustment::STATUSES[$count->adjustment->status][0] }}).
        </div>
    @endif

    <form method="POST" action="{{ route('stock-counts.update', $count) }}"
        x-data="{
            counts: @js($items->mapWithKeys(fn ($i) => [$i->id => $i->counted_quantity === null ? '' : (float) $i->counted_quantity])),
            system: @js($items->mapWithKeys(fn ($i) => [$i->id => (float) $i->system_quantity])),
            codes: @js($items->mapWithKeys(fn ($i) => [$i->product?->barcode => $i->id])->merge($items->mapWithKeys(fn ($i) => [$i->product?->sku => $i->id]))),
            filter: 'all', search: '', scan: '',
            diff(id) { return this.counts[id] === '' || this.counts[id] === null ? null : Math.round((Number(this.counts[id]) - this.system[id]) * 1000) / 1000 },
            visible(id, name) {
                const d = this.diff(id);
                if (this.search && !name.toLowerCase().includes(this.search.toLowerCase())) return false;
                return this.filter === 'all' || (this.filter === 'pending' && d === null) || (this.filter === 'diff' && d !== null && d !== 0);
            },
            doScan() {
                const id = this.codes[this.scan.trim()] ?? this.codes[this.scan.trim().toUpperCase()];
                if (!id) { toast('warning', `${this.scan} is not in this count.`); this.scan = ''; return; }
                this.counts[id] = (Number(this.counts[id]) || 0) + 1;
                this.scan = '';
                document.getElementById('count-' + id)?.scrollIntoView({ block: 'center' });
            },
        }">
        @csrf @method('PUT')
        <x-card padding="p-0">
            <x-slot name="title">Counts</x-slot>
            <x-slot name="actions">
                <x-badge :color="$color" dot>{{ $label }}</x-badge>
            </x-slot>
            @if ($open)
                <div class="grid gap-3 border-b border-line p-4 md:grid-cols-[260px_1fr_auto]">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-primary"><x-icon name="scan" size="w-4 h-4" /></span>
                        <input type="text" x-model="scan" @keydown.enter.prevent="doScan()" placeholder="Scan item: +1 to its count" class="form-control pl-10" aria-label="Scan barcode">
                    </div>
                    <input type="search" x-model="search" placeholder="Filter by name…" class="form-control" aria-label="Filter">
                    <div class="flex rounded-lg border border-line p-1 text-xs font-semibold">
                        <button type="button" @click="filter = 'all'" class="rounded-md px-3 py-1" :class="filter === 'all' ? 'bg-primary text-white' : 'text-muted'">All</button>
                        <button type="button" @click="filter = 'pending'" class="rounded-md px-3 py-1" :class="filter === 'pending' ? 'bg-primary text-white' : 'text-muted'">Not counted</button>
                        <button type="button" @click="filter = 'diff'" class="rounded-md px-3 py-1" :class="filter === 'diff' ? 'bg-primary text-white' : 'text-muted'">Differences</button>
                    </div>
                </div>
            @endif
            <div class="scrollbar-thin max-h-[60vh] overflow-auto">
                <table class="data-table">
                    <thead><tr><th>Product</th><th class="text-right">System qty</th><th class="w-36">Counted</th><th class="text-right">Difference</th><th class="text-right">Value</th></tr></thead>
                    <tbody>
                        @foreach ($items as $item)
                            @php $p = $item->product; @endphp
                            <tr id="count-{{ $item->id }}" x-show="visible({{ $item->id }}, @js($p?->name ?? ''))">
                                <td><span class="font-medium text-heading">{{ $p?->name }}</span><span class="block text-xs text-muted">{{ $p?->sku }} · {{ $p?->barcode }}</span></td>
                                <td class="text-right tabular">{{ $p?->formatQty($item->system_quantity) }} <span class="text-xs text-muted">{{ $p?->unit?->short_name }}</span></td>
                                <td>
                                    @if ($open)
                                        <input type="number" min="0" step="{{ $p?->unit?->allow_decimal ? '0.001' : '1' }}" name="counted[{{ $item->id }}]" x-model="counts[{{ $item->id }}]"
                                            class="form-control h-9 tabular" placeholder="—" aria-label="Counted quantity">
                                    @else
                                        <span class="tabular">{{ $item->counted_quantity !== null ? $p?->formatQty($item->counted_quantity) : '—' }}</span>
                                    @endif
                                </td>
                                <td class="text-right font-semibold tabular" :class="diff({{ $item->id }}) < 0 ? 'text-danger' : (diff({{ $item->id }}) > 0 ? 'text-emerald-600' : 'text-muted')"
                                    x-text="diff({{ $item->id }}) === null ? '—' : (diff({{ $item->id }}) > 0 ? '+' : '') + diff({{ $item->id }})"></td>
                                <td class="text-right tabular" x-text="diff({{ $item->id }}) ? formatMoney(diff({{ $item->id }}) * {{ (float) $p?->purchase_price }}) : '—'"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($open)
                <x-slot name="footer">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-muted">Leave a row empty if you did not count it — empty rows are not changed. Applying creates one stock adjustment for all differences.</p>
                        <div class="flex gap-2">
                            <x-button type="submit" name="then" value="save" variant="outline" icon="check">Save counts</x-button>
                            <x-button type="submit" name="then" value="apply" icon="sliders"
                                onclick="if (!this.dataset.ok) { event.preventDefault(); confirmAction({ title: 'Apply this count?', text: 'All differences become one stock adjustment.', confirmText: 'Apply', danger: false }).then(ok => { if (ok) { this.dataset.ok = 1; this.form.requestSubmit(this) } }) }">Apply differences</x-button>
                        </div>
                    </div>
                </x-slot>
            @endif
        </x-card>
    </form>
</x-app-layout>
