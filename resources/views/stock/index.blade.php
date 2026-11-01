<x-app-layout title="Stock">
    <x-page-header title="Stock" :subtitle="$matrix ? 'All branches — products as rows, branches as columns.' : 'Current stock at '.$currentBranch?->name.'.'"
        :breadcrumbs="['Inventory' => null, 'Stock' => null]">
        <x-button :href="route('stock.movements')" variant="outline" icon="clock">Stock history</x-button>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Active products" :value="number_format($stats['products'])" icon="package" />
        <x-stat-card label="Stock value (cost)" :value="money($stats['cost_value'])" icon="wallet" color="info" :hint="'At sale price: '.money($stats['sale_value'])" />
        <x-stat-card label="Low-stock items" :value="number_format($stats['low'])" icon="alert-triangle" color="warning" :href="route('stock.index', ['stock' => 'low'])" />
        <x-stat-card label="Out-of-stock items" :value="number_format($stats['out'])" icon="x-circle" color="danger" :href="route('stock.index', ['stock' => 'out'])" />
    </div>
    @if ($matrix)
        <p class="-mt-3 mb-6 text-xs text-muted">Low and out counts are per branch: one product out in two branches counts twice.</p>
    @endif

    <div class="grid gap-6 2xl:grid-cols-4">
        <div class="min-w-0 2xl:col-span-3">
            <x-table :paginator="$products">
                <x-slot name="toolbar">
                    <form method="GET" class="flex w-full flex-col gap-3 lg:flex-row lg:items-center">
                        <div class="relative flex-1 lg:max-w-xs">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search product, SKU, barcode…" class="form-control pl-10" aria-label="Search">
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <select name="category" class="form-control w-auto min-w-[150px]" onchange="this.form.submit()" aria-label="Category">
                                <option value="">All categories</option>
                                @foreach ($categories as $id => $name)<option value="{{ $id }}" @selected(request('category') == $id)>{{ $name }}</option>@endforeach
                            </select>
                            <select name="stock" class="form-control w-auto min-w-[140px]" onchange="this.form.submit()" aria-label="Stock status">
                                <option value="">Any status</option>
                                <option value="in" @selected(request('stock') === 'in')>In stock</option>
                                <option value="low" @selected(request('stock') === 'low')>Low</option>
                                <option value="out" @selected(request('stock') === 'out')>Out</option>
                            </select>
                            <select name="sort" class="form-control w-auto min-w-[160px]" onchange="this.form.submit()" aria-label="Sort">
                                <option value="">Sort: Name</option>
                                <option value="qty" @selected(request('sort') === 'qty')>Lowest quantity</option>
                                <option value="value" @selected(request('sort') === 'value')>Highest value</option>
                            </select>
                            @if (request()->hasAny(['search', 'category', 'stock', 'sort']))
                                <x-button :href="route('stock.index')" variant="ghost" icon="x">Clear</x-button>
                            @endif
                        </div>
                    </form>
                </x-slot>

                <x-slot name="head">
                    @if ($matrix)
                        <tr>
                            <th>Product</th>
                            @foreach ($branches as $branch)<th class="text-right"><span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background: {{ $branch->color }}"></span>{{ $branch->code }}</span></th>@endforeach
                            <th class="text-right">Total</th><th class="text-right">Value (cost)</th><th>Status</th>
                        </tr>
                    @else
                        <tr><th>Product</th><th>Category</th><th class="text-right">Qty</th><th class="text-right">Min</th><th class="text-right">Value (cost)</th><th class="text-right">Value (sale)</th><th>Status</th></tr>
                    @endif
                </x-slot>

                @foreach ($products as $product)
                    @php
                        $qty = (float) $product->stock_qty;
                        [$color, $label] = \App\Models\Product::statusBadge($product->stockStatusFor($qty, $branchCount));
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('products.show', $product) }}" class="flex items-center gap-3">
                                @include('products.partials.thumb', ['product' => $product, 'size' => 'h-9 w-9 text-xs'])
                                <div class="min-w-0">
                                    <p class="max-w-[240px] truncate font-medium text-heading hover:text-primary">{{ $product->name }}</p>
                                    <p class="text-xs text-muted">{{ $product->sku }}</p>
                                </div>
                            </a>
                        </td>
                        @if ($matrix)
                            @php $byBranch = $product->stocks->keyBy('branch_id'); @endphp
                            @foreach ($branches as $branch)
                                @php
                                    $q = (float) ($byBranch[$branch->id]->quantity ?? 0);
                                    $s = $product->stockStatusFor($q);
                                @endphp
                                <td class="text-right tabular">
                                    <span @class([
                                        'inline-block min-w-[44px] rounded-md px-2 py-1 font-semibold',
                                        'bg-danger/10 text-red-600 dark:text-red-400' => $s === 'out',
                                        'bg-warning/10 text-amber-600 dark:text-amber-400' => $s === 'low',
                                        'text-heading' => $s === 'in',
                                    ])>{{ $product->formatQty($q) }}</span>
                                </td>
                            @endforeach
                            <td class="text-right tabular font-bold text-heading">{{ $product->formatQty($qty) }} <span class="text-xs font-normal text-muted">{{ $product->unit?->short_name }}</span></td>
                            <td class="text-right tabular">{{ money(max(0, $qty) * (float) $product->purchase_price) }}</td>
                        @else
                            <td>
                                @if ($product->category)
                                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background: {{ $product->category->color }}"></span>{{ $product->category->name }}</span>
                                @else — @endif
                            </td>
                            <td class="text-right tabular font-bold text-heading">{{ $product->formatQty($qty) }} <span class="text-xs font-normal text-muted">{{ $product->unit?->short_name }}</span></td>
                            <td class="text-right tabular text-muted">{{ $product->formatQty($product->min_stock) }}</td>
                            <td class="text-right tabular">{{ money(max(0, $qty) * (float) $product->purchase_price) }}</td>
                            <td class="text-right tabular">{{ money(max(0, $qty) * (float) $product->sale_price) }}</td>
                        @endif
                        <td><x-badge :color="$color" dot>{{ $label }}</x-badge></td>
                    </tr>
                @endforeach

                @if ($products->isEmpty())
                    <x-slot name="empty"><x-empty-state icon="boxes" title="No products match" description="Try clearing the filters." /></x-slot>
                @endif
            </x-table>
        </div>

        {{-- Low stock list --}}
        <x-card title="Low stock" subtitle="Lowest compared to the alert level" padding="p-0" class="2xl:self-start">
            <x-slot name="actions"><x-badge color="warning">{{ $stats['low'] + $stats['out'] }}</x-badge></x-slot>
            <ul class="divide-y divide-line">
                @forelse ($lowStock as $row)
                    @php
                        $min = max((float) $row->product->min_stock, 1);
                        $pct = max(0, min(100, (float) $row->quantity / $min * 100));
                    @endphp
                    <li class="px-5 py-3.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('products.show', $row->product) }}" class="block truncate text-sm font-medium text-heading hover:text-primary">{{ $row->product->name }}</a>
                                <p class="text-xs text-muted">{{ $row->branch->code }} · min {{ $row->product->formatQty($row->product->min_stock) }}</p>
                            </div>
                            <span @class(['text-sm font-bold tabular', 'text-danger' => (float) $row->quantity <= 0, 'text-amber-600 dark:text-amber-400' => (float) $row->quantity > 0])>
                                {{ $row->product->formatQty($row->quantity) }} {{ $row->product->unit?->short_name }}
                            </span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-line">
                            <div @class(['h-full rounded-full', 'bg-danger' => $pct <= 0, 'bg-warning' => $pct > 0]) style="width: {{ max($pct, 3) }}%"></div>
                        </div>
                    </li>
                @empty
                    <li><x-empty-state icon="check-circle" title="All stocked up" description="No products are at or below their alert level." /></li>
                @endforelse
            </ul>
            @if ($lowStock->isNotEmpty())
                <x-slot name="footer">
                    @can('purchases.create')
                        <x-button :href="route('purchases.index')" icon="truck" class="w-full" title="Purchases arrive in Phase 4">Create Purchase</x-button>
                    @endcan
                    <a href="{{ route('stock.index', ['stock' => 'low']) }}" class="mt-2 block text-center text-xs font-semibold text-primary hover:text-primary-hover">View all low stock</a>
                </x-slot>
            @endif
        </x-card>
    </div>
</x-app-layout>
