<x-app-layout title="Payments">
    @php $actor = auth()->user(); @endphp
    <x-page-header title="Payments" :subtitle="'Money in and out'.($currentBranch ? ' at '.$currentBranch->name : ' across all branches').'.'" :breadcrumbs="['Finance' => null, 'Payments' => null]">
        @can('payments.create')
            <x-button :href="route('payments.create', ['type' => 'pay'])" variant="outline" icon="truck">Pay supplier</x-button>
            <x-button :href="route('payments.create', ['type' => 'receive'])" icon="banknote">Receive from customer</x-button>
        @endcan
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat-card label="Money in" :value="money($summary->received)" icon="trending-up" color="success" />
        <x-stat-card label="Money out" :value="money($summary->paid)" icon="trending-down" color="danger" />
        <x-stat-card label="Net" :value="money($summary->received - $summary->paid)" icon="wallet" color="info" />
        <x-stat-card label="Transactions" :value="number_format($summary->count)" icon="coins" />
    </div>

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 xl:items-center">
        <div class="relative sm:col-span-2">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Number, reference or name…" class="form-control pl-10" aria-label="Search">
        </div>
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control" aria-label="From">
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control" aria-label="To">
        <select name="type" class="form-control" aria-label="Type">
            <option value="">All types</option>
            @foreach (\App\Models\Payment::TYPES as $k => [$l])<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="method" class="form-control" aria-label="Method">
            <option value="">All methods</option>
            @foreach (\App\Models\Payment::METHODS as $k => $l)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $l }}</option>@endforeach
        </select>
        @if ($actor->isOwner())
            <select name="branch" class="form-control" aria-label="Branch">
                <option value="">All branches</option>
                @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch') == $b->id)>{{ $b->name }}</option>@endforeach
            </select>
        @endif
        <div class="flex gap-2 sm:col-span-2 lg:col-span-4 xl:col-span-7 xl:justify-end">
            <div class="mr-auto flex rounded-lg border border-line p-1 text-sm font-semibold">
                @foreach (['' => 'All', 'in' => 'Money in', 'out' => 'Money out'] as $k => $l)
                    <a href="{{ request()->fullUrlWithQuery(['direction' => $k ?: null, 'page' => null]) }}" @class(['rounded-md px-3 py-1', 'bg-primary text-white' => request('direction', '') === $k, 'text-muted hover:text-heading' => request('direction', '') !== $k])>{{ $l }}</a>
                @endforeach
            </div>
            @if (request()->hasAny(['search', 'from', 'to', 'type', 'method', 'branch', 'direction']))<x-button :href="route('payments.index')" variant="ghost" icon="x">Clear</x-button>@endif
            <x-button type="submit" icon="filter">Apply</x-button>
        </div>
    </form>

    @include('payments.partials.table', ['payments' => $payments, 'showParty' => true])
</x-app-layout>
