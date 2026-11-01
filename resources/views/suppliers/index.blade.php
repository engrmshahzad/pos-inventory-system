<x-app-layout title="Suppliers">
    @php
        $blank = ['id' => null, 'name' => '', 'company' => '', 'phone' => '', 'email' => '', 'address' => '', 'city' => '', 'ntn' => '', 'opening_balance' => 0, 'is_active' => true];
        $reopen = $errors->any() && old('_form') === 'supplier';
        $initial = $reopen ? array_merge($blank, \Illuminate\Support\Arr::only(old(), array_keys($blank)), ['id' => old('_id') ?: null, 'is_active' => (bool) old('is_active')]) : $blank;
    @endphp
    <div x-data="{ form: @js($initial), blank: @js($blank), create() { this.form = { ...this.blank }; $dispatch('open-drawer', 'supplier-form') } }"
        x-init="@if ($reopen) $nextTick(() => $dispatch('open-drawer', 'supplier-form')) @endif">
        <x-page-header title="Suppliers" subtitle="Who you buy from, and how much you owe them." :breadcrumbs="['Purchases' => null, 'Suppliers' => null]">
            @can('payments.create')<x-button :href="route('payments.create', ['type' => 'pay'])" variant="outline" icon="banknote">Pay supplier</x-button>@endcan
            @can('suppliers.create')<x-button icon="plus" @click="create()">Add Supplier</x-button>@endcan
        </x-page-header>

        <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
            <x-stat-card label="Suppliers" :value="number_format($stats['total'])" icon="factory" />
            <x-stat-card label="Active" :value="number_format($stats['active'])" icon="check-circle" color="success" />
            <x-stat-card label="Total payable" :value="money($stats['payable'])" icon="wallet" color="danger" :href="route('suppliers.index', ['filter' => 'payable', 'sort' => 'payable'])" />
            <x-stat-card label="Suppliers to pay" :value="number_format($stats['with_payable'])" icon="alert-triangle" color="warning" :href="route('suppliers.index', ['filter' => 'payable'])" />
        </div>

        <x-table :paginator="$suppliers">
            <x-slot name="toolbar">
                <form method="GET" class="flex w-full flex-col gap-3 lg:flex-row lg:items-center">
                    <div class="relative flex-1 lg:max-w-sm">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                        <input type="search" name="search" value="{{ request('search') }}" placeholder="Name, company or phone…" class="form-control pl-10" aria-label="Search suppliers">
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <select name="filter" class="form-control w-auto min-w-[160px]" onchange="this.form.submit()" aria-label="Filter">
                            <option value="">All suppliers</option>
                            <option value="payable" @selected(request('filter') === 'payable')>With payable</option>
                            <option value="inactive" @selected(request('filter') === 'inactive')>Inactive</option>
                        </select>
                        <select name="sort" class="form-control w-auto min-w-[160px]" onchange="this.form.submit()" aria-label="Sort">
                            <option value="">Sort: Name</option>
                            <option value="payable" @selected(request('sort') === 'payable')>Highest payable</option>
                        </select>
                        @if (request()->hasAny(['search', 'filter', 'sort']))<x-button :href="route('suppliers.index')" variant="ghost" icon="x">Clear</x-button>@endif
                    </div>
                </form>
            </x-slot>
            <x-slot name="head">
                <tr><th>Supplier</th><th>Phone</th><th>City</th><th class="text-right">Purchases</th><th>Last purchase</th><th class="text-right">Payable</th><th>Status</th><th class="text-right">Actions</th></tr>
            </x-slot>
            @foreach ($suppliers as $supplier)
                <tr @class(['opacity-60' => ! $supplier->is_active])>
                    <td>
                        <a href="{{ route('suppliers.show', $supplier) }}" class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-500/10 text-sm font-bold text-violet-600 dark:text-violet-300">{{ mb_strtoupper(mb_substr($supplier->company ?: $supplier->name, 0, 2)) }}</span>
                            <span><span class="block font-semibold text-heading hover:text-primary">{{ $supplier->name }}</span><span class="block text-xs text-muted">{{ $supplier->company }}</span></span>
                        </a>
                    </td>
                    <td class="whitespace-nowrap tabular">{{ $supplier->phone ?: '—' }}</td>
                    <td>{{ $supplier->city ?: '—' }}</td>
                    <td class="text-right tabular">{{ money($supplier->purchases_total ?? 0) }}</td>
                    <td class="whitespace-nowrap text-muted">{{ $supplier->last_purchase ? \Illuminate\Support\Carbon::parse($supplier->last_purchase)->format('d M Y') : '—' }}</td>
                    <td class="text-right">
                        @if ((float) $supplier->balance > 0)<x-badge color="danger" class="tabular">{{ money($supplier->balance) }}</x-badge>
                        @elseif ((float) $supplier->balance < 0)<x-badge color="info" class="tabular">Advance {{ money(abs((float) $supplier->balance)) }}</x-badge>
                        @else<x-badge color="success">Clear</x-badge>@endif
                    </td>
                    <td><x-badge :color="$supplier->is_active ? 'success' : 'slate'" dot>{{ $supplier->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                    <td class="text-right">
                        <div class="flex justify-end">
                            <x-dropdown width="w-52">
                                <x-slot name="trigger"><button type="button" class="rounded-lg p-1.5 text-muted hover:bg-subtle hover:text-heading" aria-label="Actions for {{ $supplier->name }}"><x-icon name="more-horizontal" size="w-5 h-5" /></button></x-slot>
                                <x-slot name="content">
                                    <a href="{{ route('suppliers.show', $supplier) }}" class="dropdown-item"><x-icon name="eye" size="w-4 h-4" /> View</a>
                                    @can('purchases.create')<a href="{{ route('purchases.create', ['supplier' => $supplier->id]) }}" class="dropdown-item"><x-icon name="truck" size="w-4 h-4" /> New purchase</a>@endcan
                                    @if ((float) $supplier->balance > 0)
                                        @can('payments.create')<a href="{{ route('payments.create', ['type' => 'pay', 'supplier' => $supplier->id]) }}" class="dropdown-item"><x-icon name="banknote" size="w-4 h-4" /> Pay supplier</a>@endcan
                                    @endif
                                    <a href="{{ route('suppliers.statement', $supplier) }}" target="_blank" class="dropdown-item"><x-icon name="file-text" size="w-4 h-4" /> Statement PDF</a>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    </td>
                </tr>
            @endforeach
            @if ($suppliers->isEmpty())
                <x-slot name="empty"><x-empty-state icon="factory" title="No suppliers found" /></x-slot>
            @endif
        </x-table>

        @can('suppliers.create')
            @include('suppliers.partials.form')
        @endcan
    </div>
</x-app-layout>
