<x-app-layout title="New Purchase">
    @php
        // Rebuild rows after a validation error so nothing typed is lost.
        $oldItems = collect(old('items', []));
        $oldProducts = $oldItems->isNotEmpty() ? \App\Models\Product::with('unit')->whereIn('id', $oldItems->pluck('product_id'))->get()->keyBy('id') : collect();
        $rows = $oldItems->filter(fn ($i) => $oldProducts->has($i['product_id'] ?? null))->map(function ($i) use ($oldProducts) {
            $p = $oldProducts[$i['product_id']];
            return ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'unit' => $p->unit?->short_name, 'decimal' => (bool) $p->unit?->allow_decimal,
                'track_expiry' => (bool) $p->track_expiry, 'price' => (float) $p->sale_price, 'qty' => (float) ($i['quantity'] ?? 1),
                'cost' => (float) ($i['unit_cost'] ?? $p->purchase_price), 'discount' => (float) ($i['discount'] ?? 0), 'tax' => (float) ($i['tax_percent'] ?? 0),
                'update_price' => ! empty($i['update_price']), 'new_price' => $i['new_sale_price'] ?? $p->sale_price,
                'expiry' => $i['expiry_date'] ?? '', 'batch' => $i['batch_no'] ?? '', 'key' => uniqid()];
        })->values();
        $initialSupplier = old('supplier_id') ? \App\Models\Supplier::find(old('supplier_id')) : $supplier;
    @endphp

    <x-page-header title="New Purchase" subtitle="Record goods bought from a supplier. Received purchases add stock immediately."
        :breadcrumbs="['Purchases' => route('purchases.index'), 'New' => null]">
        <x-button :href="route('purchases.index')" variant="outline" icon="chevron-left">Back</x-button>
    </x-page-header>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-red-700 dark:text-red-300">
            <p class="font-semibold">Please fix the following:</p>
            <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('purchases.store') }}"
        x-data="{
            rows: @js($rows),
            discount: @js((float) old('discount', 0)),
            shipping: @js((float) old('shipping', 0)),
            paid: @js((float) old('paid_amount', 0)),
            status: @js(old('status', 'received')),
            scan: '',
            r2(n) { return Math.round((Number(n) + Number.EPSILON) * 100) / 100 },
            lineNet(r) { return this.r2(Math.max(0, r.qty * r.cost - (Number(r.discount) || 0))) },
            lineTax(r) { return this.r2(this.lineNet(r) * (Number(r.tax) || 0) / 100) },
            get subtotal() { return this.r2(this.rows.reduce((s, r) => s + this.lineNet(r), 0)) },
            get tax() { return this.r2(this.rows.reduce((s, r) => s + this.lineTax(r), 0)) },
            get total() { return this.r2(this.subtotal - Math.min(Number(this.discount) || 0, this.subtotal) + this.tax + (Number(this.shipping) || 0)) },
            get due() { return this.r2(this.total - (Number(this.paid) || 0)) },
            add(p) {
                if (!p) return;
                const row = this.rows.find(r => r.id == p.id);
                if (row) { row.qty = this.r2(Number(row.qty) + 1); return; }
                this.rows.push({ id: p.id, name: p.name, sku: p.sku, unit: p.unit, decimal: p.decimal, track_expiry: p.track_expiry, price: p.price,
                    qty: 1, cost: p.cost, discount: 0, tax: 0, update_price: false, new_price: p.price, expiry: '', batch: '', key: Date.now() + Math.random() });
            },
            async scanCode() {
                const code = this.scan.trim();
                if (!code) return;
                const res = await fetch(@js(route('products.search')) + '?q=' + encodeURIComponent(code), { headers: { Accept: 'application/json' } });
                const list = await res.json();
                const hit = list.find(p => p.barcode === code || p.sku === code.toUpperCase());
                hit ? this.add(hit) : toast('warning', `No product with barcode ${code}.`);
                this.scan = '';
            },
        }" @purchase-add.window="add($event.detail)">
        @csrf

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-card title="Purchase details">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="form-label" for="supplier_id">Supplier <span class="text-danger">*</span></label>
                            <select id="supplier_id" name="supplier_id" placeholder="Search supplier…" required
                                x-data x-tomselect="{
                                    valueField: 'id', labelField: 'name', searchField: ['name', 'company', 'phone'], preload: 'focus',
                                    load: (q, cb) => fetch(@js(route('suppliers.search')) + '?q=' + encodeURIComponent(q)).then(r => r.json()).then(cb).catch(() => cb()),
                                    render: { option: (i, e) => `<div class='flex justify-between gap-3'><div><div class='font-medium'>${e(i.name)}</div><div class='text-xs text-muted'>${e(i.company ?? '')} ${e(i.phone ?? '')}</div></div><div class='text-xs font-semibold'>Payable ${e(i.balance_label)}</div></div>` },
                                }">
                                @if ($initialSupplier)<option value="{{ $initialSupplier->id }}" selected>{{ $initialSupplier->name }}</option>@endif
                            </select>
                            @can('suppliers.create')<p class="form-help">New supplier? <a href="{{ route('suppliers.index') }}" class="font-semibold text-primary">Add it on the Suppliers page</a>.</p>@endcan
                        </div>
                        @if (auth()->user()->isOwner())
                            <x-select name="branch_id" label="Branch (stock goes here)" :options="$branches->pluck('name', 'id')->all()" :value="$defaultBranch" placeholder="Choose branch" required />
                        @else
                            <div><span class="form-label">Branch</span><div class="form-control flex items-center bg-subtle">{{ $branches->first()->name }}</div></div>
                        @endif
                        <x-input name="purchase_date" type="date" label="Purchase date" :value="today()->toDateString()" :max="today()->toDateString()" required />
                        <x-input name="reference_no" label="Supplier invoice / ref no" placeholder="e.g. INV-88213" />
                        <div>
                            <span class="form-label">Status</span>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-line px-3 py-2.5 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                    <input type="radio" name="status" value="received" x-model="status" class="h-4 w-4 border-line text-primary"> <span><b class="text-heading">Received</b><span class="block text-xs text-muted">Add stock now</span></span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-line px-3 py-2.5 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                    <input type="radio" name="status" value="ordered" x-model="status" class="h-4 w-4 border-line text-primary"> <span><b class="text-heading">Ordered</b><span class="block text-xs text-muted">Not arrived yet</span></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </x-card>

                <x-card title="Items" padding="p-0">
                    <div class="grid gap-3 border-b border-line p-4 sm:grid-cols-[1fr_240px]">
                        <select placeholder="Search product to add…" aria-label="Add product"
                            x-data x-tomselect="{
                                valueField: 'id', labelField: 'name', searchField: ['name', 'sku', 'barcode'],
                                load: (q, cb) => fetch(@js(route('products.search')) + '?q=' + encodeURIComponent(q)).then(r => r.json()).then(cb).catch(() => cb()),
                                render: { option: (i, e) => `<div class='flex justify-between gap-3'><div><div class='font-medium'>${e(i.name)}</div><div class='text-xs text-muted'>${e(i.sku)} · stock ${i.stock}</div></div><div class='text-xs'>Cost ${e(String(i.cost))}</div></div>` },
                                onChange(value) { if (value) { $dispatch('purchase-add', this.options[value]); this.clear(true); this.clearOptions() } },
                            }"></select>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="scan" size="w-4 h-4" /></span>
                            <input type="text" x-model="scan" @keydown.enter.prevent="scanCode()" placeholder="Scan barcode + Enter" class="form-control pl-10" aria-label="Scan barcode">
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Product</th><th class="w-24">Qty</th><th class="w-28">Unit cost</th><th class="w-24">Discount</th><th class="w-20">Tax %</th><th class="text-right">Total</th><th class="w-10"></th></tr></thead>
                            <tbody>
                                <template x-for="(r, i) in rows" :key="r.key">
                                    <tr class="animate-slide-in align-top">
                                        <td>
                                            <input type="hidden" :name="`items[${i}][product_id]`" :value="r.id">
                                            <p class="font-medium text-heading" x-text="r.name"></p>
                                            <p class="text-xs text-muted" x-text="`${r.sku} · ${r.unit ?? ''}`"></p>
                                            <label class="mt-2 flex items-center gap-2 text-xs text-body">
                                                <input type="checkbox" :name="`items[${i}][update_price]`" value="1" x-model="r.update_price" class="form-checkbox !h-3.5 !w-3.5">
                                                New sale price
                                                <input type="number" step="0.01" min="0" :name="`items[${i}][new_sale_price]`" x-model.number="r.new_price" x-show="r.update_price"
                                                    class="h-7 w-24 rounded border-line bg-card px-2 py-0 text-xs tabular focus:border-primary focus:ring-primary/20" aria-label="New sale price">
                                                <span x-show="!r.update_price" class="text-muted" x-text="`(now ${r.price})`"></span>
                                            </label>
                                            <div x-show="r.track_expiry" class="mt-2 flex flex-wrap gap-2">
                                                <input type="date" :name="`items[${i}][expiry_date]`" x-model="r.expiry" class="h-8 rounded border-line bg-card px-2 py-0 text-xs focus:border-primary focus:ring-primary/20" aria-label="Expiry date" title="Expiry date">
                                                <input type="text" :name="`items[${i}][batch_no]`" x-model="r.batch" placeholder="Batch no" class="h-8 w-28 rounded border-line bg-card px-2 py-0 text-xs focus:border-primary focus:ring-primary/20" aria-label="Batch number">
                                            </div>
                                        </td>
                                        <td><input type="number" :step="r.decimal ? '0.001' : '1'" min="0" :name="`items[${i}][quantity]`" x-model.number="r.qty" class="form-control h-9 px-2 tabular" aria-label="Quantity"></td>
                                        <td><input type="number" step="0.01" min="0" :name="`items[${i}][unit_cost]`" x-model.number="r.cost" class="form-control h-9 px-2 tabular" aria-label="Unit cost"></td>
                                        <td><input type="number" step="0.01" min="0" :name="`items[${i}][discount]`" x-model.number="r.discount" class="form-control h-9 px-2 tabular" aria-label="Discount"></td>
                                        <td><input type="number" step="0.01" min="0" max="100" :name="`items[${i}][tax_percent]`" x-model.number="r.tax" class="form-control h-9 px-2 tabular" aria-label="Tax percent"></td>
                                        <td class="text-right font-semibold tabular text-heading" x-text="formatMoney(lineNet(r) + lineTax(r))"></td>
                                        <td><button type="button" @click="rows.splice(i, 1)" class="rounded-lg p-1.5 text-muted hover:bg-danger/10 hover:text-danger" aria-label="Remove row"><x-icon name="trash" size="w-4 h-4" /></button></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <div x-show="!rows.length"><x-empty-state icon="package" title="No items yet" description="Search a product or scan its barcode above." /></div>
                    </div>
                </x-card>
            </div>

            {{-- Totals --}}
            <div>
                <div class="card sticky top-[86px] p-6">
                    <h3 class="text-card-title text-heading">Summary</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">Items</dt><dd class="tabular text-heading" x-text="rows.length"></dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd class="tabular text-heading" x-text="formatMoney(subtotal)"></dd></div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted">Discount</dt><dd><input type="number" step="0.01" min="0" name="discount" x-model.number="discount" class="form-control h-9 w-28 text-right tabular" aria-label="Bill discount"></dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Tax</dt><dd class="tabular text-heading" x-text="formatMoney(tax)"></dd></div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted">Shipping / freight</dt><dd><input type="number" step="0.01" min="0" name="shipping" x-model.number="shipping" class="form-control h-9 w-28 text-right tabular" aria-label="Shipping"></dd></div>
                        <div class="flex items-end justify-between border-t border-dashed border-line pt-3"><dt class="font-semibold text-heading">Grand total</dt><dd class="text-2xl font-extrabold tabular text-primary dark:text-primary-300" x-text="formatMoney(total)"></dd></div>
                    </dl>
                    <div class="mt-5 space-y-3 rounded-xl bg-subtle p-4">
                        <div class="flex items-center justify-between gap-3">
                            <label for="paid_amount" class="text-sm font-medium text-heading">Paid now</label>
                            <div class="flex gap-1">
                                <button type="button" @click="paid = total" class="rounded-md px-2 text-xs font-semibold text-primary hover:bg-primary/10">Full</button>
                                <input id="paid_amount" type="number" step="0.01" min="0" name="paid_amount" x-model.number="paid" class="form-control h-9 w-28 text-right tabular">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2" x-show="paid > 0">
                            <select name="payment_method" class="form-control h-9 py-0" aria-label="Payment method">
                                @foreach ($methods as $k => $l)<option value="{{ $k }}" @selected(old('payment_method') === $k)>{{ $l }}</option>@endforeach
                            </select>
                            <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" placeholder="Ref / cheque no" class="form-control h-9" aria-label="Payment reference">
                        </div>
                        <div class="flex justify-between text-sm"><span class="text-muted">Due to supplier</span><span class="font-bold tabular" :class="due > 0 ? 'text-danger' : 'text-emerald-600'" x-text="formatMoney(due)"></span></div>
                        <p x-show="due < 0" class="text-xs font-medium text-danger">Paid cannot be more than the total.</p>
                    </div>
                    <x-input name="note" label="Note" placeholder="Optional" class="mt-4" />
                    <x-button type="submit" icon="check" class="mt-5 w-full" loading x-bind:disabled="!rows.length || due < 0">
                        <span x-text="status === 'received' ? 'Save & add stock' : 'Save purchase order'"></span>
                    </x-button>
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
