<x-app-layout :title="$customer->name">
    @php
        $form = $customer->only(['id', 'name', 'phone', 'address', 'city', 'cnic', 'is_active']) + ['credit_limit' => (float) $customer->credit_limit, 'opening_balance' => 0];
        $reopen = $errors->any() && old('_form') === 'customer';
        if ($reopen) { $form = array_merge($form, \Illuminate\Support\Arr::only(old(), ['name', 'phone', 'address', 'city', 'cnic', 'credit_limit'])); }
        $balance = (float) $customer->balance;
        $over = (float) $customer->credit_limit > 0 && $balance > (float) $customer->credit_limit;
    @endphp
    <div x-data="{ form: @js($form), tab: @js($tab) }" x-init="@if ($reopen) $nextTick(() => $dispatch('open-drawer', 'customer-form')) @endif">
        <x-page-header :title="$customer->name" :subtitle="$customer->phone.($customer->city ? ' · '.$customer->city : '')" :breadcrumbs="['Sales' => null, 'Customers' => route('customers.index'), $customer->name => null]">
            @if ($balance > 0)
                <x-button :href="$customer->reminder_url" target="_blank" variant="outline" icon="phone">WhatsApp reminder</x-button>
                @can('payments.create')<x-button :href="route('payments.create', ['type' => 'receive', 'customer' => $customer->id])" variant="success" icon="banknote">Receive payment</x-button>@endcan
            @endif
            @can('customers.edit')<x-button variant="outline" icon="pencil" @click="$dispatch('open-drawer', 'customer-form')">Edit</x-button>@endcan
        </x-page-header>

        {{-- Profile strip --}}
        <div class="card mb-6 flex flex-col gap-5 p-6 lg:flex-row lg:items-center">
            <div class="flex items-center gap-4">
                <x-avatar :name="$customer->name" size="lg" />
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-semibold text-heading">{{ $customer->name }}</h2>
                        <x-badge :color="$customer->is_active ? 'success' : 'slate'" dot>{{ $customer->is_active ? 'Active' : 'Inactive' }}</x-badge>
                        @if ($over)<x-badge color="danger" icon="alert-triangle">Over credit limit</x-badge>@endif
                    </div>
                    <p class="text-sm text-muted">{{ collect([$customer->address, $customer->city, $customer->cnic ? 'CNIC '.$customer->cnic : null])->filter()->implode(' · ') ?: 'No address' }}</p>
                </div>
            </div>
            <div class="lg:ml-auto lg:text-right">
                <p class="text-xs font-medium uppercase tracking-wide text-muted">Current balance</p>
                <p @class(['text-3xl font-extrabold tabular', 'text-danger' => $balance > 0, 'text-emerald-600 dark:text-emerald-400' => $balance <= 0])>{{ money(abs($balance), true) }}</p>
                <p class="text-xs text-muted">{{ $balance > 0 ? 'Customer owes you' : ($balance < 0 ? 'Advance with you' : 'All clear') }} · Limit {{ (float) $customer->credit_limit > 0 ? money($customer->credit_limit) : 'none' }}</p>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
            <x-stat-card label="Total purchases" :value="money($stats['total'])" icon="shopping-cart" :hint="$stats['count'].' invoices'" />
            <x-stat-card label="Total paid" :value="money($stats['paid'])" icon="check-circle" color="success" />
            <x-stat-card label="Current due" :value="money(max(0, $balance))" icon="wallet" color="danger" />
            <x-stat-card label="Last visit" :value="$stats['last_visit']?->format('d M Y') ?? '—'" icon="calendar" color="info" :hint="$stats['last_visit']?->diffForHumans()" />
        </div>

        <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
            @foreach (['sales' => 'Sales', 'payments' => 'Payments', 'ledger' => 'Ledger'] as $key => $label)
                <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                    class="-mb-px whitespace-nowrap border-b-2 pb-3 text-sm font-semibold transition"
                    :class="tab === '{{ $key }}' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-heading'">{{ $label }}</button>
            @endforeach
        </div>

        <div x-show="tab === 'sales'">
            <x-table :paginator="$sales">
                <x-slot name="head"><tr><th>Invoice</th><th>Date</th><th>Branch</th><th class="text-right">Items</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Due</th><th>Status</th></tr></x-slot>
                @foreach ($sales as $sale)
                    @php [$c, $l] = $sale->payment_badge; @endphp
                    <tr>
                        <td>
                            @if ($canOpenBranch($sale->branch_id))<a href="{{ route('sales.show', $sale) }}" class="font-semibold tabular text-heading hover:text-primary">{{ $sale->invoice_no }}</a>
                            @else<span class="font-semibold tabular text-heading">{{ $sale->invoice_no }}</span>@endif
                        </td>
                        <td class="whitespace-nowrap">{{ $sale->sold_at->format('d M Y, h:i A') }}</td>
                        <td><x-badge color="slate">{{ $sale->branch->code }}</x-badge></td>
                        <td class="text-right tabular">{{ $sale->items_count }}</td>
                        <td class="text-right font-semibold tabular text-heading">{{ money($sale->grand_total) }}</td>
                        <td class="text-right tabular">{{ money($sale->paid_amount) }}</td>
                        <td @class(['text-right tabular', 'font-semibold text-danger' => (float) $sale->due_amount > 0])>{{ money($sale->due_amount) }}</td>
                        <td><x-badge :color="$c" dot>{{ $l }}</x-badge></td>
                    </tr>
                @endforeach
                @if ($sales->isEmpty())<x-slot name="empty"><x-empty-state icon="receipt" title="No sales yet" /></x-slot>
@endif
            </x-table>
        </div>

        <div x-show="tab === 'payments'" x-cloak>
            @include('payments.partials.table', ['payments' => $payments, 'showParty' => false])
        </div>

        <div x-show="tab === 'ledger'" x-cloak>
            @include('parties.ledger', ['ledger' => $ledger, 'balanceLabel' => 'closing balance due', 'statementRoute' => route('customers.statement', $customer)])
        </div>

        @can('customers.edit')
            @include('customers.partials.form')
        @endcan
    </div>
</x-app-layout>
