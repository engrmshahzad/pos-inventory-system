<x-app-layout title="Products">
    @php $actor = auth()->user(); @endphp

    <div x-data="{
            view: (() => { try { return localStorage.getItem('products-view') || 'list' } catch (e) { return 'list' } })(),
            selected: [],
            bulkAction: 'barcodes',
            setView(v) { this.view = v; try { localStorage.setItem('products-view', v) } catch (e) {} },
            toggleAll(e) { this.selected = e.target.checked ? [...document.querySelectorAll('[data-product-id]')].map(el => el.dataset.productId) : [] },
        }">

        <x-page-header title="Products" :subtitle="'Shared catalogue · stock shown for '.($currentBranch?->name ?? 'all branches (total)')" :breadcrumbs="['Inventory' => null, 'Products' => null]">
            @can('products.create')
                <x-button :href="route('products.import')" variant="outline" icon="upload">Import</x-button>
            @endcan
            <x-button :href="route('products.export', request()->query())" variant="outline" icon="download">Export</x-button>
            @can('products.create')
                <x-button :href="route('products.create')" icon="plus">Add Product</x-button>
            @endcan
        </x-page-header>

        <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
            <x-stat-card label="Total products" :value="number_format($stats['total'])" icon="package" />
            <x-stat-card label="Active" :value="number_format($stats['active'])" icon="check-circle" color="success" />
            <x-stat-card label="Low stock" :value="number_format($stats['low'])" icon="alert-triangle" color="warning" :href="route('products.index', ['stock' => 'low'])" />
            <x-stat-card label="Out of stock" :value="number_format($stats['out'])" icon="x-circle" color="danger" :href="route('products.index', ['stock' => 'out'])" />
        </div>

        {{-- Toolbar --}}
        <form method="GET" class="card mb-4 flex flex-wrap items-center gap-3 p-4">
            <div class="relative min-w-[240px] basis-full 2xl:basis-0 2xl:flex-1">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name, SKU or barcode…" class="form-control pl-10" aria-label="Search products">
            </div>
            <div class="grid flex-1 grid-cols-2 gap-3 sm:flex sm:flex-wrap 2xl:flex-none">
                <select name="category" class="form-control sm:w-48" onchange="this.form.submit()" aria-label="Category">
                    <option value="">All categories</option>
                    @foreach ($categories as $id => $name)<option value="{{ $id }}" @selected(request('category') == $id)>{{ $name }}</option>@endforeach
                </select>
                <select name="brand" class="form-control sm:w-40" onchange="this.form.submit()" aria-label="Brand">
                    <option value="">All brands</option>
                    @foreach ($brands as $id => $name)<option value="{{ $id }}" @selected(request('brand') == $id)>{{ $name }}</option>@endforeach
                </select>
                <select name="stock" class="form-control sm:w-36" onchange="this.form.submit()" aria-label="Stock status">
                    <option value="">Any stock</option>
                    <option value="in" @selected(request('stock') === 'in')>In stock</option>
                    <option value="low" @selected(request('stock') === 'low')>Low stock</option>
                    <option value="out" @selected(request('stock') === 'out')>Out of stock</option>
                </select>
                <select name="status" class="form-control sm:w-32" onchange="this.form.submit()" aria-label="Status">
                    <option value="">Any status</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
                <select name="sort" class="form-control sm:w-40" onchange="this.form.submit()" aria-label="Sort">
                    <option value="">Sort: Name</option>
                    <option value="latest" @selected(request('sort') === 'latest')>Newest first</option>
                    <option value="price" @selected(request('sort') === 'price')>Price: low to high</option>
                    <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: high to low</option>
                    <option value="stock" @selected(request('sort') === 'stock')>Stock: lowest first</option>
                </select>
            </div>
            <div class="ml-auto flex items-center gap-2">
                @if (request()->hasAny(['search', 'category', 'brand', 'stock', 'status', 'sort']))
                    <x-button :href="route('products.index')" variant="ghost" icon="x">Clear</x-button>
                @endif
                <div class="flex rounded-lg border border-line p-1" role="group" aria-label="View">
                    <button type="button" @click="setView('list')" class="flex h-8 w-9 items-center justify-center rounded-md transition" :class="view === 'list' ? 'bg-primary text-white' : 'text-muted hover:text-heading'" aria-label="List view"><x-icon name="list" size="w-4 h-4" /></button>
                    <button type="button" @click="setView('grid')" class="flex h-8 w-9 items-center justify-center rounded-md transition" :class="view === 'grid' ? 'bg-primary text-white' : 'text-muted hover:text-heading'" aria-label="Grid view"><x-icon name="layout-grid" size="w-4 h-4" /></button>
                </div>
            </div>
        </form>

        {{-- Bulk action bar --}}
        <form id="bulk-form" method="POST" action="{{ route('products.bulk') }}" x-show="selected.length" x-cloak x-transition
            class="sticky top-[78px] z-20 mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-primary/30 bg-primary/10 px-4 py-3 backdrop-blur"
            :data-confirm="bulkAction === 'delete' ? `Delete ${selected.length} product(s)?` : null">
            @csrf
            <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
            <span class="text-sm font-semibold text-heading"><span x-text="selected.length"></span> selected</span>
            <select name="action" x-model="bulkAction" class="form-control h-9 w-auto min-w-[170px] py-0" aria-label="Bulk action">
                <option value="barcodes">Print barcodes</option>
                @can('products.edit')
                    <option value="activate">Activate</option>
                    <option value="deactivate">Deactivate</option>
                @endcan
                @can('products.delete')<option value="delete">Delete</option>@endcan
            </select>
            <x-button type="submit" size="sm" icon="check">Apply</x-button>
            <button type="button" class="ml-auto text-sm font-medium text-muted hover:text-heading" @click="selected = []">Clear selection</button>
        </form>

        @if ($products->isEmpty())
            <x-card>
                <x-empty-state icon="package" title="No products found" description="Try a different filter, or add a product.">
                    @can('products.create')<x-button :href="route('products.create')" icon="plus">Add Product</x-button>@endcan
                </x-empty-state>
            </x-card>
        @else
            {{-- List view --}}
            <div x-show="view === 'list'">
                <x-table :paginator="$products" :per-page-options="[12, 24, 48, 96]">
                    <x-slot name="head">
                        <tr>
                            <th class="w-10"><input type="checkbox" class="form-checkbox" @change="toggleAll($event)" :checked="selected.length && selected.length === {{ $products->count() }}" aria-label="Select all"></th>
                            <th>Product</th><th>Category</th><th class="text-right">Cost</th><th class="text-right">Price</th>
                            <th class="text-right">{{ $currentBranch ? 'Stock' : 'Total stock' }}</th><th>Status</th><th class="text-right">Actions</th>
                        </tr>
                    </x-slot>
                    @foreach ($products as $product)
                        @php [$color, $label] = \App\Models\Product::statusBadge($product->stockStatusFor((float) $product->stock_qty, $branchCount)); @endphp
                        <tr @class(['opacity-60' => ! $product->is_active])>
                            <td><input type="checkbox" class="form-checkbox" data-product-id="{{ $product->id }}" value="{{ $product->id }}" x-model="selected" aria-label="Select {{ $product->name }}"></td>
                            <td>
                                <a href="{{ route('products.show', $product) }}" class="flex items-center gap-3">
                                    @include('products.partials.thumb', ['product' => $product])
                                    <div class="min-w-0">
                                        <p class="max-w-[280px] truncate font-semibold text-heading hover:text-primary">{{ $product->name }}</p>
                                        <p class="text-xs text-muted tabular">{{ $product->sku }} · {{ $product->barcode }}</p>
                                    </div>
                                </a>
                            </td>
                            <td>
                                @if ($product->category)
                                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background: {{ $product->category->color }}"></span>{{ $product->category->name }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                                @if ($product->brand)<p class="text-xs text-muted">{{ $product->brand->name }}</p>@endif
                            </td>
                            <td class="text-right tabular text-muted">{{ money($product->purchase_price) }}</td>
                            <td class="text-right tabular font-semibold text-heading">{{ money($product->sale_price) }}</td>
                            <td class="text-right tabular">
                                <span class="font-semibold text-heading">{{ $product->formatQty($product->stock_qty) }}</span>
                                <span class="text-xs text-muted">{{ $product->unit?->short_name }}</span>
                            </td>
                            <td>
                                <div class="flex flex-col items-start gap-1">
                                    <x-badge :color="$color" dot>{{ $label }}</x-badge>
                                    @unless ($product->is_active)<x-badge color="slate">Inactive</x-badge>@endunless
                                </div>
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end">
                                    @include('products.partials.actions', ['product' => $product])
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </div>

            {{-- Grid view --}}
            <div x-show="view === 'grid'" x-cloak>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($products as $product)
                        @php [$color, $label] = \App\Models\Product::statusBadge($product->stockStatusFor((float) $product->stock_qty, $branchCount)); @endphp
                        <div @class(['card card-hover relative flex flex-col p-4', 'opacity-60' => ! $product->is_active]) :class="selected.includes('{{ $product->id }}') && 'ring-2 ring-primary'">
                            <div class="flex items-start justify-between">
                                <input type="checkbox" class="form-checkbox" value="{{ $product->id }}" x-model="selected" aria-label="Select {{ $product->name }}">
                                @include('products.partials.actions', ['product' => $product])
                            </div>
                            <a href="{{ route('products.show', $product) }}" class="mt-2 flex flex-col items-center text-center">
                                @include('products.partials.thumb', ['product' => $product, 'size' => 'h-20 w-20 text-2xl'])
                                <p class="mt-3 line-clamp-2 min-h-[40px] font-semibold text-heading">{{ $product->name }}</p>
                                <p class="text-xs text-muted">{{ $product->sku }}</p>
                            </a>
                            <div class="mt-4 flex items-end justify-between border-t border-line pt-3">
                                <div>
                                    <p class="text-lg font-bold tabular text-heading">{{ money($product->sale_price) }}</p>
                                    <p class="text-xs text-muted">{{ $product->formatQty($product->stock_qty) }} {{ $product->unit?->short_name }} in stock</p>
                                </div>
                                <x-badge :color="$color" dot>{{ $label }}</x-badge>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex justify-end">{{ $products->onEachSide(1)->links() }}</div>
            </div>
        @endif
    </div>
</x-app-layout>
