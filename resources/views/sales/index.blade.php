<x-app-layout title="Sales">
    @php $actor = auth()->user(); @endphp
    <x-page-header title="Sales" :subtitle="$actor->isCashier() ? 'Your sales. Showing today by default.' : 'Completed sales'.($currentBranch ? ' at '.$currentBranch->name : ' across all branches').'.'"
        :breadcrumbs="['Sales' => null, 'Sales List' => null]">
        @can('pos.access')<x-button :href="route('pos.index')" icon="shopping-cart">New Sale</x-button>@endcan
    </x-page-header>

    <div @class(['mb-6 grid grid-cols-2 gap-4', 'xl:grid-cols-4' => $profit !== null, 'xl:grid-cols-3' => $profit === null])>
        <x-stat-card label="Total sales" :value="money($summary->total)" icon="receipt" :hint="number_format($summary->count).' invoices'" />
        <x-stat-card label="Paid" :value="money($summary->paid)" icon="check-circle" color="success" />
        <x-stat-card label="Due (credit)" :value="money($summary->due)" icon="alert-triangle" color="danger" />
        @if ($profit !== null)
            <x-stat-card label="Gross profit" :value="money($profit)" icon="trending-up" color="info" hint="Sales excl. tax & charges − cost" />
        @endif
    </div>

    <x-table :paginator="$sales">
        <x-slot name="toolbar">
            <form method="GET" class="grid w-full gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8 xl:items-end">
                <div class="relative sm:col-span-2">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Invoice no, customer or phone…" class="form-control pl-10" aria-label="Search">
                </div>
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control" aria-label="From date">
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control" aria-label="To date">
                @if ($actor->isOwner())
                    <select name="branch" class="form-control" aria-label="Branch">
                        <option value="">All branches</option>
                        @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch') == $b->id)>{{ $b->name }}</option>@endforeach
                    </select>
                @endif
                @unless ($actor->isCashier())
                    <select name="cashier" class="form-control" aria-label="Cashier">
                        <option value="">All staff</option>
                        @foreach ($cashiers as $id => $name)<option value="{{ $id }}" @selected(request('cashier') == $id)>{{ $name }}</option>@endforeach
                    </select>
                @endunless
                <select name="status" class="form-control" aria-label="Payment status">
                    <option value="">Any status</option>
                    @foreach (\App\Models\Sale::PAYMENT_BADGES as $key => [, $label])<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach
                </select>
                <select name="method" class="form-control" aria-label="Payment method">
                    <option value="">Any method</option>
                    @foreach (\App\Models\Payment::METHODS as $key => $label)<option value="{{ $key }}" @selected(request('method') === $key)>{{ $label }}</option>@endforeach
                </select>
                <div class="flex gap-2 sm:col-span-2 lg:col-span-4 xl:col-span-8 xl:justify-end">
                    <div class="mr-auto flex flex-wrap items-center gap-1.5">
                        @foreach (['Today' => [today(), today()], 'Yesterday' => [today()->subDay(), today()->subDay()], 'This week' => [today()->startOfWeek(), today()], 'This month' => [today()->startOfMonth(), today()]] as $label => [$f, $t])
                            <a href="{{ request()->fullUrlWithQuery(['from' => $f->toDateString(), 'to' => $t->toDateString(), 'page' => null]) }}"
                                @class(['rounded-full px-3 py-1.5 text-xs font-semibold transition', 'bg-primary text-white' => $from->isSameDay($f) && $to->isSameDay($t), 'bg-subtle text-body hover:text-heading' => ! ($from->isSameDay($f) && $to->isSameDay($t))])>{{ $label }}</a>
                        @endforeach
                    </div>
                    @if (request()->hasAny(['search', 'from', 'to', 'branch', 'cashier', 'status', 'method']))
                        <x-button :href="route('sales.index')" variant="ghost" icon="x">Clear</x-button>
                    @endif
                    <x-button type="submit" icon="filter">Apply</x-button>
                </div>
            </form>
        </x-slot>

        <x-slot name="head">
            <tr>
                <th>Invoice</th><th>Date</th>@if ($actor->isOwner() && ! $currentBranch)<th>Branch</th>@endif<th>Customer</th><th>Cashier</th>
                <th class="text-right">Items</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Due</th><th>Status</th><th class="text-right">Actions</th>
            </tr>
        </x-slot>

        @foreach ($sales as $sale)
            @php [$color, $label] = $sale->payment_badge; @endphp
            <tr>
                <td><a href="{{ route('sales.show', $sale) }}" class="font-semibold tabular text-heading hover:text-primary">{{ $sale->invoice_no }}</a></td>
                <td class="whitespace-nowrap"><p class="text-heading">{{ $sale->sold_at->format('d M Y') }}</p><p class="text-xs text-muted">{{ $sale->sold_at->format('h:i A') }}</p></td>
                @if ($actor->isOwner() && ! $currentBranch)<td><x-badge color="slate">{{ $sale->branch->code }}</x-badge></td>@endif
                <td>
                    @if ($sale->customer)
                        <p class="font-medium text-heading">{{ $sale->customer->name }}</p><p class="text-xs text-muted">{{ $sale->customer->phone }}</p>
                    @else
                        <span class="text-muted">Walk-in</span>
                    @endif
                </td>
                <td>{{ $sale->user->name }}</td>
                <td class="text-right tabular">{{ $sale->items_count }}</td>
                <td class="text-right font-semibold tabular text-heading">{{ money($sale->grand_total) }}</td>
                <td class="text-right tabular">{{ money($sale->paid_amount) }}</td>
                <td @class(['text-right tabular', 'font-semibold text-danger' => (float) $sale->due_amount > 0])>{{ money($sale->due_amount) }}</td>
                <td><x-badge :color="$color" dot>{{ $label }}</x-badge></td>
                <td class="text-right">
                    <div class="flex justify-end">
                        <x-dropdown width="w-48">
                            <x-slot name="trigger"><button type="button" class="rounded-lg p-1.5 text-muted hover:bg-subtle hover:text-heading" aria-label="Actions for {{ $sale->invoice_no }}"><x-icon name="more-horizontal" size="w-5 h-5" /></button></x-slot>
                            <x-slot name="content">
                                <a href="{{ route('sales.show', $sale) }}" class="dropdown-item"><x-icon name="eye" size="w-4 h-4" /> View</a>
                                <a href="{{ route('sales.receipt', $sale) }}" target="_blank" class="dropdown-item"><x-icon name="receipt" size="w-4 h-4" /> Print receipt</a>
                                <a href="{{ route('sales.pdf', $sale) }}" target="_blank" class="dropdown-item"><x-icon name="file-text" size="w-4 h-4" /> A4 invoice (PDF)</a>
                                @can('sales.return')
                                    <a href="{{ route('sale-returns.create', ['invoice' => $sale->invoice_no]) }}" class="dropdown-item"><x-icon name="undo" size="w-4 h-4" /> Return items</a>
                                @endcan
                            </x-slot>
                        </x-dropdown>
                    </div>
                </td>
            </tr>
        @endforeach

        @if ($sales->isEmpty())
            <x-slot name="empty">
                <x-empty-state icon="receipt" title="No sales in this period" description="Try a different date range or filter.">
                    @can('pos.access')<x-button :href="route('pos.index')" icon="shopping-cart">Open POS</x-button>@endcan
                </x-empty-state>
            </x-slot>
        @endif
    </x-table>
</x-app-layout>
