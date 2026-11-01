<x-app-layout title="Print Barcodes">
    <x-page-header title="Print Barcode Labels" subtitle="Choose products and how many labels each. Opens a print-ready page."
        :breadcrumbs="['Inventory' => null, 'Products' => route('products.index'), 'Barcodes' => null]" />

    <form method="GET" action="{{ route('barcodes.print') }}" target="_blank"
        x-data="{
            items: @js($selected),
            add(p) { if (!p) return; const found = this.items.find(i => i.id == p.id); found ? found.qty++ : this.items.push({ ...p, qty: 1 }) },
            get total() { return this.items.reduce((s, i) => s + (parseInt(i.qty) || 0), 0) },
        }" class="grid gap-6 xl:grid-cols-3">

        <div class="space-y-6 xl:col-span-2">
            <x-card title="Products" :subtitle="'Search by name, SKU or scan a barcode.'">
                <select placeholder="Search or scan a product…" aria-label="Add product"
                    x-data x-tomselect="{
                        valueField: 'id', labelField: 'name', searchField: ['name', 'sku', 'barcode'],
                        load: (q, cb) => fetch(@js(route('products.search')) + '?q=' + encodeURIComponent(q)).then(r => r.json()).then(cb).catch(() => cb()),
                        render: { option: (i, e) => `<div class='flex justify-between gap-3'><div><div class='font-medium'>${e(i.name)}</div><div class='text-xs text-muted'>${e(i.sku)} · ${e(i.barcode)}</div></div><div class='text-xs font-semibold'>${e(i.price_label)}</div></div>` },
                        onChange(value) { if (value) { $dispatch('barcode-add', this.options[value]); this.clear(true); this.clearOptions() } },
                    }"></select>
                <div @barcode-add.window="add($event.detail)"></div>

                <div class="-mx-6 -mb-6 mt-5 overflow-x-auto border-t border-line">
                    <table class="data-table">
                        <thead><tr><th>Product</th><th>Barcode</th><th class="w-36">Labels</th><th class="w-12"></th></tr></thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="item.id">
                                <tr>
                                    <td>
                                        <p class="font-medium text-heading" x-text="item.name"></p>
                                        <p class="text-xs text-muted"><span x-text="item.sku"></span> · <span x-text="item.price_label"></span></p>
                                        <input type="hidden" :name="`items[${index}][id]`" :value="item.id">
                                    </td>
                                    <td class="font-mono text-xs" x-text="item.barcode"></td>
                                    <td>
                                        <div class="flex items-center rounded-lg border border-line">
                                            <button type="button" class="h-9 w-9 text-muted hover:text-heading" @click="item.qty = Math.max(1, item.qty - 1)" aria-label="Fewer">−</button>
                                            <input type="number" min="1" max="500" class="h-9 w-14 border-0 bg-transparent p-0 text-center tabular text-heading focus:ring-0" :name="`items[${index}][qty]`" x-model.number="item.qty" aria-label="Number of labels">
                                            <button type="button" class="h-9 w-9 text-muted hover:text-heading" @click="item.qty++" aria-label="More">+</button>
                                        </div>
                                    </td>
                                    <td><button type="button" class="rounded-lg p-1.5 text-muted hover:bg-danger/10 hover:text-danger" @click="items.splice(index, 1)" aria-label="Remove"><x-icon name="x" size="w-4 h-4" /></button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div x-show="!items.length"><x-empty-state icon="scan" title="No products added" description="Search above, or select products on the Products page and choose “Print barcodes”." /></div>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Label settings">
                <div class="space-y-5">
                    <div>
                        <span class="form-label">Label size</span>
                        <div class="space-y-2">
                            @foreach ($sizes as $key => $size)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line p-3 transition has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                    <input type="radio" name="size" value="{{ $key }}" class="h-4 w-4 border-line text-primary focus:ring-primary/30" @checked($loop->first)>
                                    <span class="text-sm font-medium text-heading">{{ $size['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <label class="flex items-center gap-2.5 text-sm"><input type="hidden" name="show_name" value="0"><input type="checkbox" name="show_name" value="1" class="form-checkbox" checked> Show product name</label>
                    <label class="flex items-center gap-2.5 text-sm"><input type="hidden" name="show_price" value="0"><input type="checkbox" name="show_price" value="1" class="form-checkbox" checked> Show price</label>
                </div>
            </x-card>
            <div class="card p-4">
                <p class="mb-3 text-sm text-body"><span class="font-bold tabular text-heading" x-text="total"></span> labels in total</p>
                <x-button type="submit" icon="scan" class="w-full" x-bind:disabled="!items.length">Open print preview</x-button>
            </div>
        </div>
    </form>
</x-app-layout>
