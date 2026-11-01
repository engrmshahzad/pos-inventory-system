<x-app-layout title="Stock History">
    <x-page-header title="Stock History" :subtitle="'Every stock change'.($currentBranch ? ' at '.$currentBranch->name : ' across all branches').', newest first.'"
        :breadcrumbs="['Inventory' => null, 'Stock' => route('stock.index'), 'History' => null]" />

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-6 xl:items-end">
        <div class="sm:col-span-2">
            <label class="form-label" for="product">Product</label>
            <select id="product" name="product" placeholder="Any product"
                x-data x-tomselect="{
                    valueField: 'id', labelField: 'name', searchField: ['name', 'sku', 'barcode'],
                    load: (q, cb) => fetch(@js(route('products.search')) + '?active_only=0&q=' + encodeURIComponent(q)).then(r => r.json()).then(cb).catch(() => cb()),
                    render: { option: (i, e) => `<div><div class='font-medium'>${e(i.name)}</div><div class='text-xs text-muted'>${e(i.sku)}</div></div>` },
                }">
                <option value="">Any product</option>
                @if ($selectedProduct)<option value="{{ $selectedProduct->id }}" selected>{{ $selectedProduct->name }}</option>@endif
            </select>
        </div>
        <div>
            <label class="form-label" for="type">Type</label>
            <select id="type" name="type" class="form-control">
                <option value="">All types</option>
                @foreach (\App\Models\StockMovement::TYPES as $key => [$label])<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="user">User</label>
            <select id="user" name="user" class="form-control">
                <option value="">Anyone</option>
                @foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('user') == $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <x-input name="from" type="date" label="From" :value="request('from')" />
        <x-input name="to" type="date" label="To" :value="request('to')" />
        <div class="flex gap-2 sm:col-span-2 xl:col-span-6 xl:justify-end">
            @if (request()->hasAny(['product', 'type', 'user', 'from', 'to']))
                <x-button :href="route('stock.movements')" variant="ghost" icon="x">Clear</x-button>
            @endif
            <x-button type="submit" icon="filter">Apply filters</x-button>
        </div>
    </form>

    @include('stock.partials.movements-table', ['movements' => $movements, 'showProduct' => true])
</x-app-layout>
