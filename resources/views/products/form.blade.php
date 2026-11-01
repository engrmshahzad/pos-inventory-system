@php
    $editing = $product->exists;
    $title = $editing ? 'Edit product' : 'Add product';
    $unitDecimals = $units->mapWithKeys(fn ($u) => [$u->id => $u->allow_decimal]);
    $crumbs = ['Inventory' => null, 'Products' => route('products.index')];
    if ($editing) {
        $crumbs[$product->name] = route('products.show', $product);
    }
    $crumbs[$editing ? 'Edit' : 'Add'] = null;
@endphp
<x-app-layout :title="$title">
    <x-page-header :title="$title" :subtitle="$editing ? $product->name.' · '.$product->sku : 'Add an item to the shared catalogue. Stock rows are created for every branch.'"
        :breadcrumbs="$crumbs">
        <x-button :href="$editing ? route('products.show', $product) : route('products.index')" variant="outline" icon="chevron-left">Back</x-button>
    </x-page-header>

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('products.update', $product) : route('products.store') }}"
        x-data="{
            cost: @js((float) old('purchase_price', $product->purchase_price ?? 0)),
            price: @js((float) old('sale_price', $product->sale_price ?? 0)),
            unit: @js((string) old('unit_id', $product->unit_id)),
            decimals: @js($unitDecimals),
            preview: @js($product->image_url),
            removeImage: false,
            get margin() { return this.price > 0 ? ((this.price - this.cost) / this.price * 100) : 0 },
            get profit() { return this.price - this.cost },
        }">
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="remove_image" :value="removeImage ? 1 : 0">

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-card title="Basic information">
                    <div class="space-y-5">
                        <x-input name="name" label="Product name" placeholder="e.g. Tapal Danedar 950g" :value="$product->name" required autofocus />
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-input name="sku" label="SKU" icon="file-text" :value="$product->sku" placeholder="Leave empty to auto-generate" help="Internal code, e.g. PRD-000051." />
                            <x-input name="barcode" label="Barcode" icon="scan" :value="$product->barcode" placeholder="Scan or leave empty for EAN-13" help="Scan the packet's barcode, or we create a valid EAN-13." />
                        </div>
                        <div class="grid gap-5 sm:grid-cols-3">
                            <x-select name="category_id" label="Category" placeholder="No category" :options="$categories" :value="$product->category_id" searchable />
                            <x-select name="brand_id" label="Brand" placeholder="No brand" :options="$brands->all()" :value="$product->brand_id" searchable />
                            <x-select name="unit_id" label="Unit" :options="$units->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->short_name.')'])->all()" :value="$product->unit_id" x-model="unit" required />
                        </div>
                        <x-input name="description" type="textarea" label="Description" :value="$product->description" placeholder="Optional notes, size, flavour…" />
                    </div>
                </x-card>

                <x-card title="Pricing" subtitle="Money is stored with 2 decimals, in {{ settings('currency_symbol', 'Rs') }}.">
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-input name="purchase_price" type="number" step="0.01" min="0" label="Purchase price" icon="wallet" :value="$product->purchase_price" x-model.number="cost" required />
                        <x-input name="sale_price" type="number" step="0.01" min="0" label="Sale price" icon="banknote" :value="$product->sale_price" x-model.number="price" required />
                        <x-input name="wholesale_price" type="number" step="0.01" min="0" label="Wholesale price" :value="$product->wholesale_price" placeholder="Optional" />
                        <x-input name="tax_percent" type="number" step="0.01" min="0" max="100" label="Tax %" :value="$product->tax_percent" />
                        <div class="sm:col-span-2">
                            <span class="form-label">Profit per unit</span>
                            <div class="flex h-input items-center gap-3 rounded-lg border border-line bg-subtle px-3.5">
                                <span class="font-semibold tabular" :class="profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-danger'"
                                    x-text="formatMoney(profit)"></span>
                                <x-badge color="success" x-show="margin >= 0" x-text="margin.toFixed(1) + '% margin'"></x-badge>
                                <x-badge color="danger" x-show="margin < 0" x-cloak>Selling below cost</x-badge>
                            </div>
                        </div>
                    </div>
                </x-card>

                @unless ($editing)
                    <x-card title="Opening stock" :subtitle="auth()->user()->isOwner() ? 'Optional. Quantity already on the shelf in each branch.' : 'Optional. Quantity already on the shelf in your branch.'">
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($branches as $branch)
                                <div>
                                    <label for="opening_{{ $branch->id }}" class="form-label flex items-center gap-2">
                                        <span class="h-2 w-2 rounded-full" style="background: {{ $branch->color }}"></span>{{ $branch->name }}
                                    </label>
                                    <input id="opening_{{ $branch->id }}" type="number" min="0" name="opening_stock[{{ $branch->id }}]" value="{{ old('opening_stock.'.$branch->id) }}"
                                        :step="decimals[unit] ? '0.001' : '1'" placeholder="0"
                                        @class(['form-control tabular', 'is-invalid' => $errors->has('opening_stock.'.$branch->id)])>
                                    @error('opening_stock.'.$branch->id)<p class="form-error">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>
                        <p class="form-help mt-3">Each quantity is saved as an <strong>opening</strong> stock movement, so it shows in the history.</p>
                    </x-card>
                @endunless
            </div>

            <div class="space-y-6">
                <x-card title="Product image">
                    <div class="flex flex-col items-center text-center">
                        <div class="relative flex h-40 w-40 items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-line bg-subtle">
                            <template x-if="preview"><img :src="preview" alt="" class="h-full w-full object-cover"></template>
                            <template x-if="!preview"><span class="text-muted"><x-icon name="camera" size="w-10 h-10" /></span></template>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <x-button size="sm" variant="outline" icon="upload" @click="$refs.image.click()">Upload</x-button>
                            <x-button size="sm" variant="ghost-danger" x-show="preview" @click="preview = null; removeImage = true; $refs.image.value = ''">Remove</x-button>
                        </div>
                        <p class="form-help">JPG, PNG or WEBP, max 2 MB.</p>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="hidden" x-ref="image"
                            @change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); removeImage = false }">
                        @error('image')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </x-card>

                <x-card title="Inventory settings">
                    <div class="space-y-5">
                        <x-input name="min_stock" type="number" step="0.001" min="0" label="Low-stock alert level" :value="$product->min_stock !== null ? (float) $product->min_stock : null" help="Per branch. At or below this, the item shows as Low stock." />
                        <x-toggle name="track_expiry" label="Track expiry" description="Ask for expiry date / batch on purchases (Phase 4)." :checked="$product->track_expiry" />
                        <x-toggle name="is_active" label="Active" description="Inactive products are hidden from the POS." :checked="$product->is_active ?? true" />
                    </div>
                </x-card>

                <div class="card sticky top-[86px] flex flex-col gap-2 p-4">
                    <x-button type="submit" icon="check" loading class="w-full">{{ $editing ? 'Save changes' : 'Save product' }}</x-button>
                    @unless ($editing)
                        <x-button type="submit" name="after_save" value="new" variant="outline" class="w-full">Save & add another</x-button>
                    @endunless
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
