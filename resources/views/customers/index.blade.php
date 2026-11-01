<x-app-layout title="Customers">
    @php
        $blank = ['id' => null, 'name' => '', 'phone' => '', 'address' => '', 'city' => '', 'cnic' => '', 'credit_limit' => 0, 'opening_balance' => 0, 'is_active' => true];
        $reopen = $errors->any() && old('_form') === 'customer';
        $initial = $reopen ? array_merge($blank, \Illuminate\Support\Arr::only(old(), array_keys($blank)), ['id' => old('_id') ?: null, 'is_active' => (bool) old('is_active')]) : $blank;
    @endphp
    <div x-data="{ form: @js($initial), blank: @js($blank), create() { this.form = { ...this.blank }; $dispatch('open-drawer', 'customer-form') } }"
        x-init="@if ($reopen) $nextTick(() => $dispatch('open-drawer', 'customer-form')) @endif">

        <x-page-header title="Customers" subtitle="Customer khata: credit sales, payments and running balance." :breadcrumbs="['Sales' => null, 'Customers' => null]">
            @can('payments.create')<x-button :href="route('payments.create', ['type' => 'receive'])" variant="outline" icon="banknote">Receive payment</x-button>@endcan
            @can('customers.create')<x-button icon="user-plus" @click="create()">Add Customer</x-button>@endcan
        </x-page-header>

        <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
            <x-stat-card label="Customers" :value="number_format($stats['total'])" icon="users" />
            <x-stat-card label="With due" :value="number_format($stats['with_due'])" icon="file-text" color="warning" :href="route('customers.index', ['filter' => 'due'])" />
            <x-stat-card label="Total receivable" :value="money($stats['receivable'])" icon="wallet" color="danger" :href="route('customers.index', ['filter' => 'due', 'sort' => 'due'])" />
            <x-stat-card label="Over credit limit" :value="number_format($stats['over_limit'])" icon="alert-triangle" color="info" :href="route('customers.index', ['filter' => 'over_limit'])" />
        </div>

        <x-table :paginator="$customers">
            <x-slot name="toolbar">
                <form method="GET" class="flex w-full flex-col gap-3 lg:flex-row lg:items-center">
                    <div class="relative flex-1 lg:max-w-sm">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                        <input type="search" name="search" value="{{ request('search') }}" placeholder="Name or phone…" class="form-control pl-10" aria-label="Search customers">
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <select name="filter" class="form-control w-auto min-w-[160px]" onchange="this.form.submit()" aria-label="Filter">
                            <option value="">All customers</option>
                            <option value="due" @selected(request('filter') === 'due')>With due</option>
                            <option value="over_limit" @selected(request('filter') === 'over_limit')>Over credit limit</option>
                            <option value="inactive" @selected(request('filter') === 'inactive')>Inactive</option>
                        </select>
                        <select name="city" class="form-control w-auto min-w-[140px]" onchange="this.form.submit()" aria-label="City">
                            <option value="">All cities</option>
                            @foreach ($cities as $city)<option @selected(request('city') === $city)>{{ $city }}</option>@endforeach
                        </select>
                        <select name="sort" class="form-control w-auto min-w-[150px]" onchange="this.form.submit()" aria-label="Sort">
                            <option value="">Sort: Name</option>
                            <option value="due" @selected(request('sort') === 'due')>Highest due</option>
                        </select>
                        @if (request()->hasAny(['search', 'filter', 'city', 'sort']))<x-button :href="route('customers.index')" variant="ghost" icon="x">Clear</x-button>@endif
                    </div>
                </form>
            </x-slot>
            <x-slot name="head">
                <tr><th>Customer</th><th>Phone</th><th>City</th><th class="text-right">Credit limit</th><th class="text-right">Balance due</th><th>Last visit</th><th>Status</th><th class="text-right">Actions</th></tr>
            </x-slot>
            @foreach ($customers as $customer)
                @php $over = (float) $customer->credit_limit > 0 && (float) $customer->balance > (float) $customer->credit_limit; @endphp
                <tr @class(['opacity-60' => ! $customer->is_active])>
                    <td>
                        <a href="{{ route('customers.show', $customer) }}" class="flex items-center gap-3">
                            <x-avatar :name="$customer->name" size="sm" />
                            <span class="font-semibold text-heading hover:text-primary">{{ $customer->name }}</span>
                        </a>
                    </td>
                    <td class="whitespace-nowrap tabular">{{ $customer->phone }}</td>
                    <td>{{ $customer->city ?: '—' }}</td>
                    <td class="text-right tabular text-muted">{{ (float) $customer->credit_limit > 0 ? money($customer->credit_limit) : 'No limit' }}</td>
                    <td class="text-right">
                        @if ((float) $customer->balance > 0)
                            <x-badge :color="$over ? 'danger' : 'warning'" class="tabular">{{ money($customer->balance) }}</x-badge>
                        @elseif ((float) $customer->balance < 0)
                            <x-badge color="info" class="tabular">Advance {{ money(abs((float) $customer->balance)) }}</x-badge>
                        @else
                            <x-badge color="success">Clear</x-badge>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-muted">{{ $customer->last_visit ? \Illuminate\Support\Carbon::parse($customer->last_visit)->diffForHumans() : 'Never' }}</td>
                    <td><x-badge :color="$customer->is_active ? 'success' : 'slate'" dot>{{ $customer->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                    <td class="text-right">
                        <div class="flex justify-end">
                            <x-dropdown width="w-52">
                                <x-slot name="trigger"><button type="button" class="rounded-lg p-1.5 text-muted hover:bg-subtle hover:text-heading" aria-label="Actions for {{ $customer->name }}"><x-icon name="more-horizontal" size="w-5 h-5" /></button></x-slot>
                                <x-slot name="content">
                                    <a href="{{ route('customers.show', $customer) }}" class="dropdown-item"><x-icon name="eye" size="w-4 h-4" /> View khata</a>
                                    @if ((float) $customer->balance > 0)
                                        @can('payments.create')<a href="{{ route('payments.create', ['type' => 'receive', 'customer' => $customer->id]) }}" class="dropdown-item"><x-icon name="banknote" size="w-4 h-4" /> Receive payment</a>@endcan
                                        <a href="{{ $customer->reminder_url }}" target="_blank" rel="noopener" class="dropdown-item"><x-icon name="phone" size="w-4 h-4" /> WhatsApp reminder</a>
                                    @endif
                                    <a href="{{ route('customers.statement', $customer) }}" target="_blank" class="dropdown-item"><x-icon name="file-text" size="w-4 h-4" /> Statement PDF</a>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    </td>
                </tr>
            @endforeach
            @if ($customers->isEmpty())
                <x-slot name="empty"><x-empty-state icon="users" title="No customers found" description="Try a different search or filter." /></x-slot>
            @endif
        </x-table>

        @can('customers.create')
            @include('customers.partials.form')
        @endcan
    </div>
</x-app-layout>
