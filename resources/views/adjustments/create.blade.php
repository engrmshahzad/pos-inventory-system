<x-app-layout title="New Adjustment">
    <x-page-header title="New Stock Adjustment" :subtitle="'Branch: '.($currentBranch?->name ?? 'choose one in the branch switcher')" :breadcrumbs="['Inventory' => null, 'Adjustments' => route('adjustments.index'), 'New' => null]">
        <x-button :href="route('adjustments.index')" variant="outline" icon="chevron-left">Back</x-button>
    </x-page-header>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-red-700 dark:text-red-300">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('adjustments.store') }}"
        x-data="{
            rows: @js($prefill->map(fn ($p) => array_merge($p, ['dir' => $p['qty'] < 0 ? -1 : 1, 'qty' => abs($p['qty'])]))),
            reason: @js(old('reason', $reason)),
            add(p) {
                if (!p || this.rows.find(r => r.id == p.id)) return;
                this.rows.push({ id: p.id, name: p.name, sku: p.sku, unit: p.unit, decimal: p.decimal, cost: p.cost, stock: p.stock, qty: 1, dir: this.reason === 'found' ? 1 : -1, note: '' });
            },
            value(r) { return Math.round(r.dir * r.qty * r.cost * 100) / 100 },
            get total() { return this.rows.reduce((s, r) => s + this.value(r), 0) },
            get impact() { return this.rows.reduce((s, r) => s + Math.abs(this.value(r)), 0) },
        }" @adjust-add.window="add($event.detail)">
        @csrf
        <input type="hidden" name="source" value="{{ request('source', 'manual') === 'expiry' ? 'expiry' : 'manual' }}">
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-card title="Details">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-input name="adjustment_date" type="date" label="Date" :value="today()->toDateString()" :max="today()->toDateString()" required />
                        <x-select name="reason" label="Reason" :options="$reasons" x-model="reason" required />
                        <x-input name="note" label="Note" placeholder="Optional" :value="request('note')" />
                    </div>
                </x-card>
                <x-card title="Products" subtitle="Use − to remove stock (damaged, expired, lost) and + to add (found)." padding="p-0">
                    <div class="border-b border-line p-4">
                        <select placeholder="Search product to add…" aria-label="Add product"
                            x-data x-tomselect="{
                                valueField: 'id', labelField: 'name', searchField: ['name', 'sku', 'barcode'],
                                load: (q, cb) => fetch(@js(route('products.search')) + '?q=' + encodeURIComponent(q)).then(r => r.json()).then(cb).catch(() => cb()),
                                render: { option: (i, e) => `<div class='flex justify-between gap-3'><div><div class='font-medium'>${e(i.name)}</div><div class='text-xs text-muted'>${e(i.sku)}</div></div><div class='text-xs'>${i.stock} in stock</div></div>` },
                                onChange(value) { if (value) { $dispatch('adjust-add', this.options[value]); this.clear(true); this.clearOptions() } },
                            }"></select>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Product</th><th class="text-right">In stock</th><th class="w-24">+ / −</th><th class="w-28">Qty</th><th class="text-right">Value</th><th>Note</th><th class="w-10"></th></tr></thead>
                            <tbody>
                                <template x-for="(r, i) in rows" :key="r.id">
                                    <tr>
                                        <td><input type="hidden" :name="`items[${i}][product_id]`" :value="r.id"><input type="hidden" :name="`items[${i}][quantity]`" :value="r.dir * r.qty">
                                            <p class="font-medium text-heading" x-text="r.name"></p><p class="text-xs text-muted" x-text="r.sku"></p></td>
                                        <td class="text-right tabular" x-text="`${r.stock} ${r.unit ?? ''}`"></td>
                                        <td>
                                            <div class="flex rounded-lg border border-line p-0.5 text-sm font-bold">
                                                <button type="button" @click="r.dir = -1" class="flex-1 rounded-md py-1" :class="r.dir < 0 ? 'bg-danger text-white' : 'text-muted'" aria-label="Remove stock">−</button>
                                                <button type="button" @click="r.dir = 1" class="flex-1 rounded-md py-1" :class="r.dir > 0 ? 'bg-success text-white' : 'text-muted'" aria-label="Add stock">+</button>
                                            </div>
                                        </td>
                                        <td><input type="number" min="0" :step="r.decimal ? '0.001' : '1'" x-model.number="r.qty" class="form-control h-9 tabular" aria-label="Quantity"></td>
                                        <td class="text-right font-semibold tabular" :class="value(r) < 0 ? 'text-danger' : 'text-emerald-600'" x-text="formatMoney(value(r))"></td>
                                        <td><input type="text" :name="`items[${i}][note]`" x-model="r.note" class="form-control h-9" placeholder="Optional" aria-label="Note"></td>
                                        <td><button type="button" @click="rows.splice(i, 1)" class="rounded-lg p-1.5 text-muted hover:bg-danger/10 hover:text-danger" aria-label="Remove"><x-icon name="trash" size="w-4 h-4" /></button></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <div x-show="!rows.length"><x-empty-state icon="sliders" title="No products yet" description="Search above to add products to adjust." /></div>
                    </div>
                </x-card>
            </div>
            <div>
                <div class="card sticky top-[86px] space-y-4 p-6">
                    <div><p class="text-xs font-medium uppercase tracking-wide text-muted">Net value impact</p>
                        <p class="text-3xl font-extrabold tabular" :class="total < 0 ? 'text-danger' : 'text-emerald-600'" x-text="formatMoney(total)"></p></div>
                    @if ($needsApproval)
                        <p x-show="impact > {{ $threshold }}" x-cloak class="flex items-start gap-2 rounded-lg bg-warning/10 px-3 py-2 text-xs font-medium text-amber-700 dark:text-amber-400">
                            <x-icon name="alert-triangle" size="w-4 h-4 shrink-0" /> Above {{ money($threshold) }} — this goes to the owner for approval. Stock changes after approval.
                        </p>
                        <p x-show="impact <= {{ $threshold }}" class="text-xs text-muted">Below {{ money($threshold) }} — applied immediately.</p>
                    @else
                        <p class="text-xs text-muted">As owner, your adjustments are applied immediately.</p>
                    @endif
                    <x-button type="submit" icon="check" class="w-full" loading x-bind:disabled="!rows.length || rows.some(r => !(r.qty > 0))">Save adjustment</x-button>
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
