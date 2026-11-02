<x-app-layout title="Stock Transfers">
    <x-page-header title="Stock Transfers" :subtitle="'Move stock between branches'.($currentBranch ? ' · viewing '.$currentBranch->name : '').'.'" :breadcrumbs="['Inventory' => null, 'Transfers' => null]">
        @role('owner')<x-button :href="route('transfers.suggestions')" variant="outline" icon="sparkles">Suggest transfers</x-button>@endrole
        @can('transfers.create')<x-button :href="route('transfers.create')" icon="plus">New Transfer</x-button>@endcan
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat-card label="Waiting to receive" :value="$counts['pending']" icon="inbox" color="warning" :href="route('transfers.index', ['tab' => 'pending'])" :hint="$branchId ? 'Incoming to your branch' : 'All branches'" />
        <x-stat-card label="In transit" :value="$counts['in_transit']" icon="truck" color="info" :href="route('transfers.index', ['status' => 'sent'])" />
        <x-stat-card label="Drafts / requests" :value="$counts['drafts']" icon="file-text" :href="route('transfers.index', ['status' => 'draft'])" />
        <x-stat-card label="With shortage" :value="$counts['short']" icon="alert-triangle" color="danger" :href="route('transfers.index', ['status' => 'partially_received'])" />
    </div>

    <div class="mb-4 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        @foreach (['all' => 'All', 'outgoing' => 'Outgoing', 'incoming' => 'Incoming', 'pending' => 'Pending receive'] as $key => $label)
            @continue(! $branchId && in_array($key, ['outgoing', 'incoming']))
            <a href="{{ route('transfers.index', ['tab' => $key]) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                @class(['-mb-px inline-flex items-center gap-2 whitespace-nowrap border-b-2 pb-3 text-sm font-semibold transition', 'border-primary text-primary' => $tab === $key, 'border-transparent text-muted hover:text-heading' => $tab !== $key])>
                {{ $label }} @if ($key === 'pending' && $counts['pending'])<span class="rounded-full bg-warning px-1.5 text-[11px] font-bold text-white">{{ $counts['pending'] }}</span>@endif
            </a>
        @endforeach
    </div>

    <x-table :paginator="$transfers">
        <x-slot name="toolbar">
            <form method="GET" class="flex w-full flex-col gap-3 sm:flex-row">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="relative flex-1 sm:max-w-xs">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search transfer no…" class="form-control pl-10" aria-label="Search">
                </div>
                <select name="status" class="form-control w-auto min-w-[170px]" onchange="this.form.submit()" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach (\App\Models\StockTransfer::STATUSES as $k => [$l])<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
                </select>
            </form>
        </x-slot>
        <x-slot name="head"><tr><th>Transfer</th><th>Date</th><th>Route</th><th class="text-right">Items</th><th>Status</th><th>Created by</th><th></th></tr></x-slot>
        @foreach ($transfers as $t)
            @php [$label, $color] = $t->status_badge; $incoming = $branchId && $t->to_branch_id === $branchId; @endphp
            <tr>
                <td>
                    <a href="{{ route('transfers.show', $t) }}" class="font-semibold tabular text-heading hover:text-primary">{{ $t->transfer_no }}</a>
                    @if ($t->is_request && $t->status === 'draft')<span class="ml-1 rounded bg-warning/15 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-700 dark:text-amber-400">Request</span>@endif
                </td>
                <td class="whitespace-nowrap">{{ fmtDate($t->transfer_date) }}</td>
                <td>
                    <span class="inline-flex items-center gap-2 font-medium text-heading">
                        <x-badge color="slate">{{ $t->fromBranch->code }}</x-badge><x-icon name="arrow-right" size="w-3.5 h-3.5 text-muted" /><x-badge color="slate">{{ $t->toBranch->code }}</x-badge>
                    </span>
                    @if ($branchId)<span class="ml-2 text-xs {{ $incoming ? 'text-emerald-600' : 'text-muted' }}">{{ $incoming ? 'Incoming' : 'Outgoing' }}</span>@endif
                </td>
                <td class="text-right tabular">{{ $t->items_count }}</td>
                <td><x-badge :color="$color" dot>{{ $label }}</x-badge></td>
                <td>{{ $t->creator->name }}</td>
                <td class="text-right">
                    @if ($t->status === 'sent' && $incoming)
                        <x-button size="sm" variant="success" :href="route('transfers.show', $t)" icon="inbox">Receive</x-button>
                    @else
                        <a href="{{ route('transfers.show', $t) }}" class="text-sm font-semibold text-primary hover:text-primary-hover">View</a>
                    @endif
                </td>
            </tr>
        @endforeach
        @if ($transfers->isEmpty())
            <x-slot name="empty"><x-empty-state icon="arrow-left-right" title="No transfers here" description="Transfers between branches will appear here." /></x-slot>
        @endif
    </x-table>
</x-app-layout>
