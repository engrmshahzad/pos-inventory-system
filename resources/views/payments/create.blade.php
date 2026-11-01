@php $receive = $type === 'receive'; @endphp
<x-app-layout :title="$receive ? 'Receive Payment' : 'Pay Supplier'">
    <x-page-header :title="$receive ? 'Receive payment from customer' : 'Pay a supplier'"
        :subtitle="$receive ? 'Money is applied to the oldest unpaid invoices first, unless you choose invoices yourself.' : 'Payment is applied to the oldest unpaid purchases first, unless you choose them yourself.'"
        :breadcrumbs="['Finance' => null, 'Payments' => route('payments.index'), $receive ? 'Receive' : 'Pay' => null]">
        <div class="flex rounded-lg border border-line p-1 text-sm font-semibold">
            <a href="{{ route('payments.create', ['type' => 'receive']) }}" @class(['rounded-md px-3 py-1.5', 'bg-primary text-white' => $receive, 'text-muted' => ! $receive])>Receive</a>
            <a href="{{ route('payments.create', ['type' => 'pay']) }}" @class(['rounded-md px-3 py-1.5', 'bg-primary text-white' => ! $receive, 'text-muted' => $receive])>Pay</a>
        </div>
    </x-page-header>

    @if (! $currentBranch)
        <div class="mb-6 flex items-center gap-3 rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-300">
            <x-icon name="alert-triangle" size="w-5 h-5" /> Choose a branch in the switcher (top bar). The payment is recorded at that branch.
        </div>
    @endif

    <form method="POST" action="{{ route('payments.store') }}"
        x-data="{
            party: @js($party ? ['id' => $party->id, 'name' => $party->name, 'balance' => (float) $party->balance] : null),
            amount: @js(old('amount', $party && (float) $party->balance > 0 ? (float) $party->balance : '')),
            mode: @js(old('allocation_mode', 'auto')),
            invoices: [],
            alloc: {},
            loading: false,
            async loadInvoices() {
                this.invoices = []; this.alloc = {};
                if (!this.party) return;
                this.loading = true;
                const url = @js($receive ? route('customers.invoices', '__ID__') : route('suppliers.invoices', '__ID__')).replace('__ID__', this.party.id);
                this.invoices = await fetch(url, { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => []);
                this.loading = false;
            },
            get preview() {
                let left = Number(this.amount) || 0;
                return this.invoices.map(i => {
                    const a = this.mode === 'auto' ? Math.min(left, i.due) : (Number(this.alloc[i.id]) || 0);
                    if (this.mode === 'auto') left = Math.round((left - a) * 100) / 100;
                    return { ...i, applied: a };
                });
            },
            get allocated() { return Math.round(this.preview.reduce((s, i) => s + i.applied, 0) * 100) / 100 },
            get leftover() { return Math.round(((Number(this.amount) || 0) - this.allocated) * 100) / 100 },
            get totalDue() { return this.invoices.reduce((s, i) => s + i.due, 0) },
        }" x-init="loadInvoices()" @party-picked.window="party = $event.detail; if (party && !amount && party.balance > 0) amount = party.balance; loadInvoices()">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="allocation_mode" :value="mode">

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-red-700 dark:text-red-300">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <x-card title="Payment" class="xl:col-span-1">
                <div class="space-y-5">
                    <div>
                        <label class="form-label" for="party_id">{{ $receive ? 'Customer' : 'Supplier' }} <span class="text-danger">*</span></label>
                        <select id="party_id" name="party_id" placeholder="Search…" required
                            x-data x-tomselect="{
                                valueField: 'id', labelField: 'name', searchField: ['name', 'phone', 'company'], preload: 'focus',
                                load: (q, cb) => fetch(@js($receive ? route('customers.search') : route('suppliers.search')) + '?q=' + encodeURIComponent(q)).then(r => r.json()).then(cb).catch(() => cb()),
                                render: { option: (i, e) => `<div class='flex justify-between gap-3'><div><div class='font-medium'>${e(i.name)}</div><div class='text-xs text-muted'>${e(i.phone ?? '')}</div></div><div class='text-xs font-semibold'>${e(i.balance_label)}</div></div>` },
                                onChange(value) { $dispatch('party-picked', value ? this.options[value] : null) },
                            }">
                            @if ($party)<option value="{{ $party->id }}" selected>{{ $party->name }}</option>@endif
                        </select>
                        <p class="form-help" x-show="party" x-cloak>{{ $receive ? 'Balance due' : 'Payable' }}: <b class="tabular text-heading" x-text="formatMoney(party?.balance ?? 0)"></b></p>
                    </div>
                    <x-input name="amount" type="number" step="0.01" min="0.01" label="Amount" icon="banknote" x-model.number="amount" required />
                    <x-select name="method" label="Method" :options="$methods" value="cash" required />
                    <x-input name="paid_at" type="date" label="Date" :value="today()->toDateString()" :max="today()->toDateString()" required />
                    <x-input name="reference" label="Reference / cheque / TID" placeholder="Optional" />
                    <x-input name="note" label="Note" placeholder="Optional" />
                </div>
            </x-card>

            <x-card class="xl:col-span-2" title="Apply to invoices" :subtitle="$receive ? 'Unpaid sales of this customer (all branches)' : 'Unpaid received purchases from this supplier'" padding="p-0">
                <x-slot name="actions">
                    <div class="flex rounded-lg border border-line p-1 text-xs font-semibold">
                        <button type="button" @click="mode = 'auto'" class="rounded-md px-3 py-1" :class="mode === 'auto' ? 'bg-primary text-white' : 'text-muted'">Oldest first</button>
                        <button type="button" @click="mode = 'manual'" class="rounded-md px-3 py-1" :class="mode === 'manual' ? 'bg-primary text-white' : 'text-muted'">Choose invoices</button>
                    </div>
                </x-slot>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Invoice</th><th>Date</th><th>Branch</th><th class="text-right">Total</th><th class="text-right">Due</th><th class="w-40 text-right">Apply</th></tr></thead>
                        <tbody>
                            <template x-for="inv in preview" :key="inv.id">
                                <tr>
                                    <td class="font-semibold tabular text-heading" x-text="inv.number"></td>
                                    <td x-text="inv.date"></td>
                                    <td><span class="rounded-full bg-subtle px-2 py-0.5 text-xs font-semibold" x-text="inv.branch"></span></td>
                                    <td class="text-right tabular" x-text="formatMoney(inv.total)"></td>
                                    <td class="text-right font-semibold tabular text-danger" x-text="formatMoney(inv.due)"></td>
                                    <td class="text-right">
                                        <span x-show="mode === 'auto'" class="font-semibold tabular" :class="inv.applied > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted'" x-text="formatMoney(inv.applied)"></span>
                                        <input x-show="mode === 'manual'" type="number" step="0.01" min="0" :max="inv.due" :name="`allocations[${inv.id}]`" x-model.number="alloc[inv.id]"
                                            class="form-control h-9 text-right tabular" aria-label="Amount to apply">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div x-show="!party"><x-empty-state icon="search" :title="$receive ? 'Choose a customer' : 'Choose a supplier'" description="Their unpaid invoices appear here." /></div>
                    <div x-show="party && !loading && !invoices.length" x-cloak><x-empty-state icon="check-circle" title="No unpaid invoices" description="The whole amount reduces the opening balance or is kept as an advance." /></div>
                </div>
                <x-slot name="footer">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <dl class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                            <div><dt class="inline text-muted">Applied:</dt> <dd class="inline font-semibold tabular text-heading" x-text="formatMoney(allocated)"></dd></div>
                            <div><dt class="inline text-muted">Not applied:</dt> <dd class="inline font-semibold tabular" :class="leftover < 0 ? 'text-danger' : 'text-heading'" x-text="formatMoney(leftover)"></dd></div>
                        </dl>
                        <x-button type="submit" :variant="$receive ? 'success' : 'primary'" icon="check" loading x-bind:disabled="!party || !(amount > 0) || leftover < 0">
                            {{ $receive ? 'Receive payment' : 'Save payment' }}
                        </x-button>
                    </div>
                    <p class="mt-2 text-xs text-muted" x-show="leftover > 0">The amount not applied to invoices reduces the {{ $receive ? 'customer' : 'supplier' }}'s opening balance, or stays as an advance.</p>
                </x-slot>
            </x-card>
        </div>
    </form>
</x-app-layout>
