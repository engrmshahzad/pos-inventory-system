{{-- Shared overlay for all POS modals --}}
<div x-show="modal" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm" @click="modal !== 'success' && closeModal()"></div>

{{-- ===== Payment ===== --}}
<div x-show="modal === 'pay'" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4 pointer-events-none" role="dialog" aria-modal="true" aria-label="Payment">
    <div class="pointer-events-auto flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-line bg-card shadow-card-hover">
        <div class="flex items-center justify-between border-b border-line px-6 py-4">
            <div>
                <h3 class="text-card-title text-heading">Payment</h3>
                <p class="text-xs text-muted" x-text="cart.customer ? cart.customer.name : 'Walk-in Customer'"></p>
            </div>
            <div class="text-right">
                <p class="text-xs font-medium uppercase tracking-wide text-muted">Amount to pay</p>
                <p class="text-3xl font-extrabold tabular text-primary dark:text-primary-300" x-text="money(grandTotal)"></p>
            </div>
        </div>

        <div class="scrollbar-thin grid flex-1 gap-6 overflow-y-auto p-6 md:grid-cols-[1fr_1.15fr]">
            {{-- Methods --}}
            <div>
                <p class="section-label mb-2">Payment method</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($methods as $key => [$label, $icon])
                        <button type="button" @click="addMethod('{{ $key }}')"
                            class="flex h-16 flex-col items-center justify-center gap-1 rounded-xl border-2 text-sm font-semibold transition"
                            :class="{{ $key === 'credit' ? "(due > 0 && !payments.length)" : "payments.some(p => p.method === '{$key}')" }} ? 'border-primary bg-primary/10 text-primary dark:text-primary-300' : 'border-line text-body hover:border-primary/40'">
                            <x-icon :name="$icon" size="w-5 h-5" /> {{ $label }}
                        </button>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-muted">Click a second method to split the bill (e.g. part cash, part JazzCash).</p>
            </div>

            {{-- Amounts --}}
            <div class="space-y-3">
                <template x-for="(p, index) in payments" :key="index">
                    <div class="rounded-xl border border-line p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-semibold capitalize text-heading" x-text="{cash:'Cash',card:'Card',jazzcash:'JazzCash',easypaisa:'Easypaisa',bank:'Bank Transfer'}[p.method]"></span>
                            <button type="button" @click="removePayment(index)" class="rounded p-1 text-muted hover:text-danger" aria-label="Remove payment"><x-icon name="x" size="w-4 h-4" /></button>
                        </div>
                        <div class="flex gap-2">
                            <input type="number" min="0" step="0.01" x-model.number="p.amount" :id="`payAmount${index}`"
                                class="form-control h-12 flex-1 text-lg font-bold tabular" aria-label="Amount">
                            <input type="text" x-show="p.method !== 'cash'" x-model="p.reference" placeholder="Ref / TID" data-no-enter class="form-control h-12 w-32" aria-label="Reference">
                        </div>
                        <div x-show="p.method === 'cash'" class="mt-2 grid grid-cols-4 gap-2">
                            <button type="button" @click="quickCash('exact')" class="h-9 rounded-lg bg-subtle text-xs font-bold text-heading hover:bg-primary/10">Exact</button>
                            @foreach ([500, 1000, 5000] as $amount)
                                <button type="button" @click="quickCash({{ $amount }})" class="h-9 rounded-lg bg-subtle text-xs font-bold tabular text-heading hover:bg-primary/10">{{ number_format($amount) }}</button>
                            @endforeach
                        </div>
                    </div>
                </template>
                <p x-show="!payments.length" class="rounded-xl border border-dashed border-line p-4 text-center text-sm text-muted">No payment now — the full amount goes to the customer's khata.</p>

                <dl class="space-y-2 rounded-xl bg-subtle p-4 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Received</dt><dd class="font-semibold tabular text-heading" x-text="money(tendered)"></dd></div>
                    <div class="flex items-center justify-between" x-show="change > 0">
                        <dt class="font-semibold text-emerald-600 dark:text-emerald-400">Change due</dt>
                        <dd class="text-2xl font-extrabold tabular text-emerald-600 dark:text-emerald-400" x-text="money(change)"></dd>
                    </div>
                    <div class="flex items-center justify-between" x-show="due > 0">
                        <dt class="font-semibold text-danger">Due (udhaar)</dt>
                        <dd class="text-xl font-extrabold tabular text-danger" x-text="money(due)"></dd>
                    </div>
                </dl>
                <p x-show="creditBlocked" class="flex items-start gap-2 rounded-lg bg-danger/10 px-3 py-2 text-xs font-medium text-red-600 dark:text-red-400">
                    <x-icon name="alert-triangle" size="w-4 h-4 shrink-0" /> <span>Select a customer to leave an amount due. <button type="button" class="underline" @click="openCustomer()">Choose customer</button></span>
                </p>
                <p x-show="overCreditLimit" class="flex items-start gap-2 rounded-lg bg-warning/10 px-3 py-2 text-xs font-medium text-amber-700 dark:text-amber-400">
                    <x-icon name="alert-triangle" size="w-4 h-4 shrink-0" /> This sale takes the customer over their credit limit.
                </p>
                <input type="text" x-model="cart.note" placeholder="Note (optional)" data-no-enter class="form-control" aria-label="Note">
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-line bg-subtle/60 px-6 py-4">
            <x-button variant="outline" @click="closeModal()">Cancel <kbd class="kbd ml-1">Esc</kbd></x-button>
            <button type="button" @click="checkout()" :disabled="busy || creditBlocked"
                class="inline-flex h-12 items-center gap-3 rounded-xl bg-success px-8 text-base font-extrabold text-white shadow-lg shadow-success/30 transition hover:bg-emerald-600 disabled:opacity-50">
                <x-icon name="loader" size="w-5 h-5 animate-spin" x-show="busy" />
                <x-icon name="check" size="w-5 h-5" x-show="!busy" />
                Confirm payment <kbd class="kbd !border-white/30 !bg-white/15 !text-white !shadow-none">↵</kbd>
            </button>
        </div>
    </div>
</div>

{{-- ===== Customer ===== --}}
<div x-show="modal === 'customer'" x-cloak x-transition class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-[10vh] pointer-events-none" role="dialog" aria-modal="true" aria-label="Choose customer">
    <div class="pointer-events-auto w-full max-w-lg overflow-hidden rounded-2xl border border-line bg-card shadow-card-hover">
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
            <h3 class="text-card-title text-heading" x-text="showCustomerForm ? 'New customer' : 'Choose customer'"></h3>
            <button type="button" @click="closeModal()" class="rounded-lg p-1.5 text-muted hover:bg-subtle" aria-label="Close"><x-icon name="x" size="w-5 h-5" /></button>
        </div>

        <div x-show="!showCustomerForm">
            <div class="p-4">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                    <input x-ref="customerSearch" x-model="customerQuery" @input.debounce.250ms="searchCustomers()" data-no-enter type="text" placeholder="Search name or phone…" class="form-control pl-10" aria-label="Search customers">
                </div>
            </div>
            <ul class="scrollbar-thin max-h-80 overflow-y-auto border-t border-line">
                <li>
                    <button type="button" @click="chooseCustomer(null)" class="flex w-full items-center gap-3 px-5 py-3 text-left hover:bg-subtle">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-subtle text-muted"><x-icon name="user" size="w-4 h-4" /></span>
                        <span class="font-medium text-heading">Walk-in Customer</span>
                    </button>
                </li>
                <template x-for="c in customerResults" :key="c.id">
                    <li>
                        <button type="button" @click="chooseCustomer(c)" class="flex w-full items-center gap-3 px-5 py-3 text-left hover:bg-subtle">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary" x-text="c.name.charAt(0)"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-heading" x-text="c.name"></span>
                                <span class="block text-xs text-muted" x-text="c.phone"></span>
                            </span>
                            <span x-show="c.balance > 0" class="rounded-full bg-danger/10 px-2 py-0.5 text-xs font-semibold tabular text-red-600 dark:text-red-400" x-text="`Due ${money(c.balance)}`"></span>
                        </button>
                    </li>
                </template>
            </ul>
            @can('customers.create')
                <div class="border-t border-line p-4">
                    <x-button variant="secondary" icon="user-plus" class="w-full" @click="showCustomerForm = true; $nextTick(() => $refs.newCustomerName.focus())">Add new customer</x-button>
                </div>
            @endcan
        </div>

        @can('customers.create')
            <form x-show="showCustomerForm" @submit.prevent="saveCustomer()" class="space-y-4 p-5">
                <div>
                    <label class="form-label" for="nc-name">Name <span class="text-danger">*</span></label>
                    <input id="nc-name" x-ref="newCustomerName" x-model="newCustomer.name" data-no-enter class="form-control" :class="customerErrors.name && 'is-invalid'" required>
                    <p class="form-error" x-show="customerErrors.name" x-text="customerErrors.name?.[0]"></p>
                </div>
                <div>
                    <label class="form-label" for="nc-phone">Phone <span class="text-danger">*</span></label>
                    <input id="nc-phone" x-model="newCustomer.phone" data-no-enter placeholder="0300-1234567" class="form-control" :class="customerErrors.phone && 'is-invalid'" required>
                    <p class="form-error" x-show="customerErrors.phone" x-text="customerErrors.phone?.[0]"></p>
                </div>
                <div>
                    <label class="form-label" for="nc-address">Address</label>
                    <input id="nc-address" x-model="newCustomer.address" data-no-enter class="form-control">
                </div>
                <div class="flex justify-end gap-2">
                    <x-button variant="outline" @click="showCustomerForm = false">Back</x-button>
                    <x-button type="submit" icon="check">Save customer</x-button>
                </div>
            </form>
        @endcan
    </div>
</div>

{{-- ===== Held bills (drawer) ===== --}}
<div x-show="modal === 'held'" x-cloak
    x-transition:enter="transform transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
    class="fixed inset-y-0 right-0 z-50 flex w-full max-w-[480px] flex-col bg-card shadow-drawer" role="dialog" aria-modal="true" aria-label="Held bills">
    <div class="flex items-center justify-between border-b border-line px-6 py-5">
        <div>
            <h3 class="text-card-title text-heading">Held bills</h3>
            <p class="text-xs text-muted">Resume a bill to finish it, or delete it.</p>
        </div>
        <button type="button" @click="closeModal()" class="rounded-lg p-1.5 text-muted hover:bg-subtle" aria-label="Close"><x-icon name="x" size="w-5 h-5" /></button>
    </div>
    <ul class="scrollbar-thin flex-1 divide-y divide-line overflow-y-auto">
        <template x-for="bill in held" :key="bill.id">
            <li class="flex items-center gap-3 px-6 py-4">
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-heading" x-text="bill.customer"></p>
                    <p class="text-xs text-muted"><span x-text="`${bill.items} items`"></span> · <span x-text="bill.time"></span> · <span x-text="bill.user"></span></p>
                    <p class="text-xs italic text-muted" x-show="bill.note" x-text="bill.note"></p>
                </div>
                <p class="font-bold tabular text-heading" x-text="bill.total_label"></p>
                <x-button size="sm" @click="resumeHeld(bill)">Resume</x-button>
                <button type="button" @click="deleteHeld(bill)" class="rounded-lg p-1.5 text-muted hover:bg-danger/10 hover:text-danger" aria-label="Delete held bill"><x-icon name="trash" size="w-4 h-4" /></button>
            </li>
        </template>
    </ul>
    <div x-show="!held.length"><x-empty-state icon="clock" title="No held bills" description="Press F8 on the POS to put a bill on hold." /></div>
</div>

{{-- ===== Success ===== --}}
<div x-show="modal === 'success'" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4 pointer-events-none" role="dialog" aria-modal="true" aria-label="Sale completed">
    <div class="pointer-events-auto w-full max-w-md overflow-hidden rounded-2xl border border-line bg-card text-center shadow-card-hover">
        <div class="bg-gradient-to-br from-success to-emerald-600 px-6 pb-8 pt-10 text-white">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/20 ring-8 ring-white/10"><x-icon name="check" size="w-9 h-9" /></span>
            <p class="mt-4 text-lg font-bold">Sale completed</p>
            <p class="font-mono text-sm text-emerald-50" x-text="lastSale?.invoice_no"></p>
        </div>
        <div class="space-y-1 px-6 py-6">
            <template x-if="lastSale?.change > 0">
                <div>
                    <p class="text-sm font-medium text-muted">Change to return</p>
                    <p class="text-4xl font-extrabold tabular text-heading" x-text="money(lastSale.change)"></p>
                </div>
            </template>
            <template x-if="lastSale && !(lastSale.change > 0)">
                <div>
                    <p class="text-sm font-medium text-muted">Total</p>
                    <p class="text-4xl font-extrabold tabular text-heading" x-text="money(lastSale.grand_total)"></p>
                </div>
            </template>
            <p x-show="lastSale?.due > 0" class="text-sm font-semibold text-danger" x-text="`${money(lastSale?.due)} added to ${lastSale?.customer}'s khata`"></p>
        </div>
        <div class="grid grid-cols-3 gap-2 border-t border-line p-4">
            <x-button variant="outline" icon="receipt" @click="printReceipt()">Print</x-button>
            <a :href="lastSale?.whatsapp_url" target="_blank" rel="noopener" class="inline-flex h-btn items-center justify-center gap-2 rounded-lg bg-[#25D366]/15 text-sm font-semibold text-[#128C7E] transition hover:bg-[#25D366]/25 dark:text-[#25D366]">
                <x-icon name="phone" size="w-4 h-4" /> WhatsApp
            </a>
            <x-button icon="plus" @click="newSale()">New <kbd class="kbd !border-white/30 !bg-white/15 !text-white !shadow-none">↵</kbd></x-button>
        </div>
    </div>
</div>
