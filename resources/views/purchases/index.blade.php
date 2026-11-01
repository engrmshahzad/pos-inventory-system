<x-app-layout title="Purchases">
    @php $actor = auth()->user(); @endphp
    <x-page-header title="Purchases" :subtitle="'Goods bought from suppliers'.($currentBranch ? ' for '.$currentBranch->name : ' across all branches').'.'" :breadcrumbs="['Purchases' => null, 'Purchase List' => null]">
        @can('purchase_returns.create')<x-button :href="route('purchase-returns.create')" variant="outline" icon="rotate-ccw">Return to supplier</x-button>@endcan
        @can('purchases.create')<x-button :href="route('purchases.create')" icon="plus">New Purchase</x-button>@endcan
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat-card label="Received value" :value="money($summary->received)" icon="truck" :hint="number_format($summary->count).' purchases'" />
        <x-stat-card label="Paid" :value="money($summary->paid)" icon="check-circle" color="success" />
        <x-stat-card label="Due to suppliers" :value="money($summary->due)" icon="wallet" color="danger" />
        <x-stat-card label="Open orders" :value="number_format($summary->ordered)" icon="clock" color="info" :href="route('purchases.index', ['status' => 'ordered'])" />
    </div>

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 xl:items-center">
        <div class="relative sm:col-span-2 xl:col-span-2">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Purchase or supplier ref no…" class="form-control pl-10" aria-label="Search">
        </div>
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control" aria-label="From">
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control" aria-label="To">
        <select name="supplier" class="form-control" aria-label="Supplier">
            <option value="">All suppliers</option>
            @foreach ($suppliers as $id => $name)<option value="{{ $id }}" @selected(request('supplier') == $id)>{{ $name }}</option>@endforeach
        </select>
        @if ($actor->isOwner())
            <select name="branch" class="form-control" aria-label="Branch">
                <option value="">All branches</option>
                @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch') == $b->id)>{{ $b->name }}</option>@endforeach
            </select>
        @endif
        <select name="payment" class="form-control" aria-label="Payment status">
            <option value="">Any payment</option>
            @foreach (\App\Models\Sale::PAYMENT_BADGES as $k => [, $l])<option value="{{ $k }}" @selected(request('payment') === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="status" class="form-control" aria-label="Status">
            <option value="">Any status</option>
            <option value="received" @selected(request('status') === 'received')>Received</option>
            <option value="ordered" @selected(request('status') === 'ordered')>Ordered</option>
        </select>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-4 xl:col-span-7 xl:justify-end">
            @if (request()->hasAny(['search', 'from', 'to', 'supplier', 'branch', 'payment', 'status']))<x-button :href="route('purchases.index')" variant="ghost" icon="x">Clear</x-button>@endif
            <x-button type="submit" icon="filter">Apply</x-button>
        </div>
    </form>

    @include('purchases.partials.table', ['purchases' => $purchases, 'showSupplier' => true])
</x-app-layout>
