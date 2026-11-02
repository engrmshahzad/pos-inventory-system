<x-app-layout title="New Transfer">
    @php $owner = auth()->user()->isOwner(); @endphp
    <x-page-header title="New Stock Transfer" :subtitle="$owner ? 'Move stock between branches. Save as a request for the sending branch, or send it now.' : 'Send stock from your branch to another branch.'"
        :breadcrumbs="['Inventory' => null, 'Transfers' => route('transfers.index'), 'New' => null]">
        <x-button :href="route('transfers.index')" variant="outline" icon="chevron-left">Back</x-button>
    </x-page-header>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-red-700 dark:text-red-300">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('transfers.store') }}"
        x-data="{
            from: @js((string) old('from_branch_id', $from)),
            to: @js((string) old('to_branch_id', $to)),
            rows: @js($prefill),
            stock: {},
            scan: '',
            async refreshStock() {
                if (!this.from || !this.rows.length) return;
                for (const r of this.rows) {
                    const list = await fetch(@js(route('products.search')) + '?branch=' + this.from + '&q=' + encodeURIComponent(r.sku)).then(x => x.json()).catch(() => []);
                    const hit = list.find(p => p.id === r.id);
                    this.stock[r.id] = hit ? hit.stock : 0;
                }
            },
            add(p) {
                if (!p) return;
                const row = this.rows.find(r => r.id == p.id);
                this.stock[p.id] = p.stock;
                if (row) { row.qty = Number(row.qty) + 1; return; }
                this.rows.push({ id: p.id, name: p.name, sku: p.sku, unit: p.unit, decimal: p.decimal, qty: 1, note: '' });
            },
            async scanCode() {
                const code = this.scan.trim(); if (!code) return;
                const list = await fetch(@js(route('products.search')) + '?branch=' + this.from + '&q=' + encodeURIComponent(code)).then(r => r.json()).catch(() => []);
                const hit = list.find(p => p.barcode === code || p.sku === code.toUpperCase());
                hit ? this.add(hit) : toast('warning', `No product with barcode ${code}.`);
                this.scan = '';
            },
            over(r) { return this.stock[r.id] !== undefined && Number(r.qty) > this.stock[r.id] },
            get invalid() { return !this.rows.length || !this.to || this.from === this.to || this.rows.some(r => this.over(r) || !(r.qty > 0)) },
        }" x-init="refreshStock(); $watch('from', () => refreshStock())" @transfer-add.window="add($event.detail)">
        @csrf
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-card title="Route">
                    <div class="grid items-end gap-4 sm:grid-cols-[1fr_auto_1fr]">
                        @if ($owner)
                            <x-select name="from_branch_id" label="From (sends)" :options="$branches->pluck('name', 'id')->all()" placeholder="Choose branch" x-model="from" required />
                        @else
                            <div><span class="form-label">From (sends)</span><div class="form-control flex items-center bg-subtle">{{ auth()->user()->branch->name }}</div></div>
                        @endif
                        <span class="mb-2.5 hidden h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary sm:flex"><x-icon name="arrow-right" size="w-4 h-4" /></span>
                        <x-select name="to_branch_id" label="To (receives)" :options="$branches->pluck('name', 'id')->all()" placeholder="Choose branch" x-model="to" required />
                    </div>
                    <p x-show="from && to && from === to" x-cloak class="form-error">The receiving branch must be different.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <x-input name="transfer_date" type="date" label="Date" :value="today()->toDateString()" :max="today()->toDateString()" required />
                        <x-input name="note" label="Note" placeholder="e.g. Weekend demand at KHI" />
                    </div>
                </x-card>

                <x-card title="Items" padding="p-0">
                    <div class="grid gap-3 border-b border-line p-4 sm:grid-cols-[1fr_240px]">
                        <select placeholder="Search product to add…" aria-label="Add product"
                            x-data x-tomselect="{
                                valueField: 'id', labelField: 'name', searchField: ['name', 'sku', 'barcode'],
                                load: function (q, cb) { const from = document.querySelector('[name=from_branch_id]')?.value ?? ''; fetch(@js(route('products.search')) + '?branch=' + from + '&q=' + encodeURIComponent(q)).then(r => r.json()).then(cb).catch(() => cb()) },
                                render: { option: (i, e) => `<div class='flex justify-between gap-3'><div><div class='font-medium'>${e(i.name)}</div><div class='text-xs text-muted'>${e(i.sku)}</div></div><div class='text-xs font-semibold'>${i.stock} ${e(i.unit ?? '')} available</div></div>` },
                                onChange(value) { if (value) { $dispatch('transfer-add', this.options[value]); this.clear(true); this.clearOptions() } },
                            }"></select>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="scan" size="w-4 h-4" /></span>
                            <input type="text" x-model="scan" @keydown.enter.prevent="scanCode()" placeholder="Scan barcode + Enter" class="form-control pl-10" aria-label="Scan barcode">
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Product</th><th class="text-right">Available</th><th class="w-32">Qty to send</th><th>Note</th><th class="w-10"></th></tr></thead>
                            <tbody>
                                <template x-for="(r, i) in rows" :key="r.id">
                                    <tr class="animate-slide-in">
                                        <td><input type="hidden" :name="`items[${i}][product_id]`" :value="r.id"><p class="font-medium text-heading" x-text="r.name"></p><p class="text-xs text-muted" x-text="r.sku"></p></td>
                                        <td class="text-right tabular" :class="over(r) ? 'font-semibold text-danger' : ''" x-text="stock[r.id] === undefined ? '…' : `${stock[r.id]} ${r.unit ?? ''}`"></td>
                                        <td>
                                            <input type="number" min="0" :step="r.decimal ? '0.001' : '1'" :name="`items[${i}][quantity]`" x-model.number="r.qty" class="form-control h-9 tabular" :class="over(r) && 'is-invalid'" aria-label="Quantity">
                                            <p x-show="over(r)" class="form-error">More than available</p>
                                        </td>
                                        <td><input type="text" :name="`items[${i}][note]`" x-model="r.note" class="form-control h-9" placeholder="Optional" aria-label="Line note"></td>
                                        <td><button type="button" @click="rows.splice(i, 1)" class="rounded-lg p-1.5 text-muted hover:bg-danger/10 hover:text-danger" aria-label="Remove"><x-icon name="trash" size="w-4 h-4" /></button></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <div x-show="!rows.length"><x-empty-state icon="arrow-left-right" title="No items yet" description="Search or scan products to send." /></div>
                    </div>
                </x-card>
            </div>
            <div>
                <div class="card sticky top-[86px] space-y-4 p-6">
                    <h3 class="text-card-title text-heading">Summary</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">Products</dt><dd class="font-semibold tabular text-heading" x-text="rows.length"></dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Total qty</dt><dd class="font-semibold tabular text-heading" x-text="rows.reduce((s, r) => s + (Number(r.qty) || 0), 0)"></dd></div>
                    </dl>
                    <div class="rounded-xl bg-subtle p-4 text-xs text-body">
                        <p><b class="text-heading">Send now:</b> stock leaves the sending branch immediately and the receiving branch is notified.</p>
                        <p class="mt-2"><b class="text-heading">Save draft{{ $owner ? ' / request' : '' }}:</b> nothing moves yet.{{ $owner ? ' The sending branch is asked to send it.' : '' }}</p>
                    </div>
                    <x-button type="submit" name="action" value="send" icon="truck" class="w-full" x-bind:disabled="invalid">Send now</x-button>
                    <x-button type="submit" name="action" value="draft" variant="outline" class="w-full" x-bind:disabled="invalid">{{ $owner ? 'Save as request' : 'Save draft' }}</x-button>
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
