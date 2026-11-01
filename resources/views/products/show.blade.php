<x-app-layout :title="$product->name">
    @php
        $branchCount = max($stockRows->count(), 1);
        [$statusColor, $statusLabel] = \App\Models\Product::statusBadge($product->stockStatusFor($totalQty, $branchCount));
        $unit = $product->unit?->short_name;
    @endphp

    <x-page-header :title="$product->name" :subtitle="$product->sku.' · '.$product->barcode"
        :breadcrumbs="['Inventory' => null, 'Products' => route('products.index'), $product->name => null]">
        <x-button :href="route('barcodes.index', ['ids' => [$product->id]])" variant="outline" icon="scan">Print barcode</x-button>
        @can('products.edit')
            <x-button :href="route('products.edit', $product)" icon="pencil">Edit</x-button>
        @endcan
    </x-page-header>

    {{-- Summary --}}
    <div class="card mb-6 flex flex-col gap-6 p-6 md:flex-row md:items-center">
        @include('products.partials.thumb', ['product' => $product, 'size' => 'h-24 w-24 text-3xl'])
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <x-badge :color="$statusColor" dot>{{ $statusLabel }}</x-badge>
                <x-badge :color="$product->is_active ? 'success' : 'slate'">{{ $product->is_active ? 'Active' : 'Inactive' }}</x-badge>
                @if ($product->category)<x-badge color="primary">{{ $product->category->full_name }}</x-badge>@endif
                @if ($product->brand)<x-badge color="violet">{{ $product->brand->name }}</x-badge>@endif
                @if ($product->track_expiry)<x-badge color="warning" icon="calendar">Tracks expiry</x-badge>@endif
            </div>
            @if ($product->description)<p class="mt-3 max-w-2xl text-sm text-body">{{ $product->description }}</p>@endif
        </div>
        <dl class="grid grid-cols-2 gap-x-8 gap-y-3 sm:grid-cols-4 md:grid-cols-2 xl:grid-cols-4">
            <div><dt class="text-xs text-muted">Sale price</dt><dd class="text-xl font-bold tabular text-heading">{{ money($product->sale_price) }}</dd></div>
            <div><dt class="text-xs text-muted">Cost</dt><dd class="text-xl font-bold tabular text-heading">{{ money($product->purchase_price) }}</dd></div>
            <div><dt class="text-xs text-muted">Margin</dt><dd @class(['text-xl font-bold tabular', 'text-emerald-600 dark:text-emerald-400' => $product->margin >= 0, 'text-danger' => $product->margin < 0])>{{ number_format($product->margin, 1) }}%</dd></div>
            <div><dt class="text-xs text-muted">{{ auth()->user()->isOwner() ? 'Total stock' : 'Your stock' }}</dt><dd class="text-xl font-bold tabular text-heading">{{ $product->formatQty($totalQty) }} <span class="text-sm font-medium text-muted">{{ $unit }}</span></dd></div>
        </dl>
    </div>

    <div x-data="{ tab: @js($tab) }">
        <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
            @foreach (['overview' => 'Overview', 'stock' => 'Stock by Branch', 'history' => 'Stock History', 'sales' => 'Sales'] as $key => $label)
                <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                    class="-mb-px whitespace-nowrap border-b-2 pb-3 text-sm font-semibold transition"
                    :class="tab === '{{ $key }}' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-heading'">{{ $label }}</button>
            @endforeach
        </div>

        {{-- Overview --}}
        <div x-show="tab === 'overview'" class="grid gap-6 lg:grid-cols-2">
            <x-card title="Product details">
                <dl class="divide-y divide-line text-sm">
                    @foreach ([
                        'SKU' => $product->sku,
                        'Barcode' => $product->barcode,
                        'Category' => $product->category?->full_name ?? '—',
                        'Brand' => $product->brand?->name ?? '—',
                        'Unit' => $product->unit ? $product->unit->name.' ('.$product->unit->short_name.')' : '—',
                        'Low-stock level' => $product->formatQty($product->min_stock).' '.$unit.' per branch',
                        'Created' => $product->created_at->format(settings('date_format', 'd M Y')),
                        'Last updated' => $product->updated_at->diffForHumans(),
                    ] as $term => $value)
                        <div class="flex justify-between gap-4 py-3 first:pt-0 last:pb-0">
                            <dt class="text-muted">{{ $term }}</dt>
                            <dd class="text-right font-medium text-heading tabular">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>
            <x-card title="Pricing & profit">
                <dl class="divide-y divide-line text-sm">
                    @foreach ([
                        'Purchase price' => money($product->purchase_price, true),
                        'Sale price' => money($product->sale_price, true),
                        'Wholesale price' => $product->wholesale_price !== null ? money($product->wholesale_price, true) : '—',
                        'Tax' => rtrim(rtrim(number_format((float) $product->tax_percent, 2), '0'), '.').'%',
                        'Profit per unit' => money((float) $product->sale_price - (float) $product->purchase_price, true),
                    ] as $term => $value)
                        <div class="flex justify-between gap-4 py-3 first:pt-0">
                            <dt class="text-muted">{{ $term }}</dt>
                            <dd class="font-medium text-heading tabular">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <div class="mt-4 rounded-xl bg-subtle p-4">
                    <div class="mb-2 flex justify-between text-xs"><span class="text-muted">Profit margin</span><span class="font-semibold text-heading">{{ number_format($product->margin, 1) }}%</span></div>
                    <div class="h-2 overflow-hidden rounded-full bg-line">
                        <div class="h-full rounded-full bg-gradient-to-r from-primary to-violet-500" style="width: {{ max(0, min(100, $product->margin)) }}%"></div>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Stock by branch --}}
        <div x-show="tab === 'stock'" x-cloak>
            <x-table>
                <x-slot name="head">
                    <tr><th>Branch</th><th class="text-right">Quantity</th><th class="text-right">Value (cost)</th><th class="text-right">Value (sale)</th><th>Status</th></tr>
                </x-slot>
                @foreach ($stockRows as $row)
                    @php [$c, $l] = \App\Models\Product::statusBadge($product->stockStatusFor((float) $row->quantity)); @endphp
                    <tr>
                        <td>
                            <span class="inline-flex items-center gap-2 font-medium text-heading">
                                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $row->branch->color }}"></span>{{ $row->branch->name }}
                                <x-badge color="slate">{{ $row->branch->code }}</x-badge>
                            </span>
                        </td>
                        <td class="text-right tabular font-semibold text-heading">{{ $product->formatQty($row->quantity) }} <span class="text-xs font-normal text-muted">{{ $unit }}</span></td>
                        <td class="text-right tabular">{{ money(max(0, (float) $row->quantity) * (float) $product->purchase_price) }}</td>
                        <td class="text-right tabular">{{ money(max(0, (float) $row->quantity) * (float) $product->sale_price) }}</td>
                        <td><x-badge :color="$c" dot>{{ $l }}</x-badge></td>
                    </tr>
                @endforeach
                @if ($stockRows->count() > 1)
                    <tr class="!bg-subtle font-semibold">
                        <td class="text-heading">Total</td>
                        <td class="text-right tabular text-heading">{{ $product->formatQty($totalQty) }} <span class="text-xs font-normal text-muted">{{ $unit }}</span></td>
                        <td class="text-right tabular text-heading">{{ money($stockRows->sum(fn ($r) => max(0, (float) $r->quantity)) * (float) $product->purchase_price) }}</td>
                        <td class="text-right tabular text-heading">{{ money($stockRows->sum(fn ($r) => max(0, (float) $r->quantity)) * (float) $product->sale_price) }}</td>
                        <td></td>
                    </tr>
                @endif
            </x-table>
        </div>

        {{-- Stock history --}}
        <div x-show="tab === 'history'" x-cloak>
            @include('stock.partials.movements-table', ['movements' => $movements, 'showProduct' => false])
        </div>

        {{-- Sales (Phase 3) --}}
        <div x-show="tab === 'sales'" x-cloak>
            <x-card>
                <x-empty-state icon="receipt" title="Sales history arrives in Phase 3" description="Once the POS is live, every sale of this product will be listed here." />
            </x-card>
        </div>
    </div>
</x-app-layout>
