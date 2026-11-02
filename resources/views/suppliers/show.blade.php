<x-app-layout :title="$supplier->name">
    @php
        $form = $supplier->only(['id', 'name', 'company', 'phone', 'email', 'address', 'city', 'ntn', 'is_active']) + ['opening_balance' => 0];
        $reopen = $errors->any() && old('_form') === 'supplier';
        if ($reopen) { $form = array_merge($form, \Illuminate\Support\Arr::only(old(), ['name', 'company', 'phone', 'email', 'address', 'city', 'ntn'])); }
        $balance = (float) $supplier->balance;
    @endphp
    <div x-data="{ form: @js($form), tab: @js($tab) }" x-init="@if ($reopen) $nextTick(() => $dispatch('open-drawer', 'supplier-form')) @endif">
        <x-page-header :title="$supplier->name" :subtitle="collect([$supplier->company, $supplier->phone, $supplier->city])->filter()->implode(' · ')"
            :breadcrumbs="['Purchases' => null, 'Suppliers' => route('suppliers.index'), $supplier->name => null]">
            @can('purchases.create')<x-button :href="route('purchases.create', ['supplier' => $supplier->id])" variant="outline" icon="truck">New purchase</x-button>@endcan
            @if ($balance > 0)
                @can('payments.create')<x-button :href="route('payments.create', ['type' => 'pay', 'supplier' => $supplier->id])" variant="success" icon="banknote">Pay supplier</x-button>@endcan
            @endif
            @can('suppliers.edit')<x-button variant="outline" icon="pencil" @click="$dispatch('open-drawer', 'supplier-form')">Edit</x-button>@endcan
        </x-page-header>

        <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
            @foreach (['overview' => 'Overview', 'purchases' => 'Purchases', 'payments' => 'Payments', 'ledger' => 'Ledger'] as $key => $label)
                <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                    class="-mb-px whitespace-nowrap border-b-2 pb-3 text-sm font-semibold transition"
                    :class="tab === '{{ $key }}' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-heading'">{{ $label }}</button>
            @endforeach
        </div>

        <div x-show="tab === 'overview'" class="space-y-6">
            <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <x-stat-card label="Total purchases" :value="money($stats['purchases'])" icon="truck" :hint="$stats['count'].' purchase(s)'" />
                <x-stat-card label="Total paid" :value="money($stats['paid'])" icon="check-circle" color="success" />
                <x-stat-card label="Payable now" :value="money(max(0, $balance))" icon="wallet" color="danger" :hint="$balance < 0 ? 'Advance '.money(abs($balance)) : null" />
                <x-stat-card label="Last purchase" :value="$stats['last'] ? fmtDate($stats['last']) : '—'" icon="calendar" color="info" />
            </div>
            <x-card title="Details">
                <dl class="grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (['Contact' => $supplier->name, 'Company' => $supplier->company, 'Phone' => $supplier->phone, 'Email' => $supplier->email, 'Address' => $supplier->address, 'City' => $supplier->city, 'NTN' => $supplier->ntn, 'Opening payable' => money($supplier->opening_balance, true)] as $k => $v)
                        <div><dt class="text-muted">{{ $k }}</dt><dd class="font-medium text-heading">{{ $v ?: '—' }}</dd></div>
                    @endforeach
                </dl>
            </x-card>
        </div>

        <div x-show="tab === 'purchases'" x-cloak>
            @include('purchases.partials.table', ['purchases' => $purchases, 'showSupplier' => false])
        </div>
        <div x-show="tab === 'payments'" x-cloak>
            @include('payments.partials.table', ['payments' => $payments, 'showParty' => false])
        </div>
        <div x-show="tab === 'ledger'" x-cloak>
            @include('parties.ledger', ['ledger' => $ledger, 'balanceLabel' => 'closing payable', 'statementRoute' => route('suppliers.statement', $supplier)])
        </div>

        @can('suppliers.edit')
            @include('suppliers.partials.form')
        @endcan
    </div>
</x-app-layout>
