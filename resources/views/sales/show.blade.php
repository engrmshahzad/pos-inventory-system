<x-app-layout :title="$sale->invoice_no">
    @php
        [$color, $label] = $sale->payment_badge;
        $qty = fn ($item) => $item->product ? $item->product->formatQty($item->quantity) : rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.');
    @endphp

    <x-page-header :title="$sale->invoice_no" :subtitle="$sale->sold_at->format('l, d M Y · h:i A').' · '.$sale->branch->name"
        :breadcrumbs="['Sales' => null, 'Sales List' => route('sales.index'), $sale->invoice_no => null]">
        <x-button :href="route('sales.receipt', $sale)" target="_blank" variant="outline" icon="receipt">Receipt</x-button>
        <x-button :href="route('sales.pdf', $sale)" target="_blank" variant="outline" icon="file-text">A4 PDF</x-button>
        @can('receivePayment', $sale)
            <x-button icon="banknote" variant="success" @click="$dispatch('open-modal', 'receive-payment')">Receive Payment</x-button>
        @endcan
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Invoice --}}
        <div class="card overflow-hidden xl:col-span-2">
            <div class="hero-pattern flex flex-wrap items-start justify-between gap-4 bg-gradient-to-r from-primary-600 to-violet-600 px-6 py-6 text-white">
                <div>
                    <p class="text-sm text-indigo-100">{{ settings('business_name', config('app.name')) }}</p>
                    <p class="text-xl font-bold text-white">{{ $sale->branch->name }}</p>
                    <p class="text-sm text-indigo-100">{{ $sale->branch->address }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs uppercase tracking-widest text-indigo-200">Invoice</p>
                    <p class="font-mono text-lg font-bold text-white">{{ $sale->invoice_no }}</p>
                    <span class="mt-1 inline-block rounded-full bg-white/20 px-3 py-1 text-xs font-bold text-white">{{ $label }}</span>
                </div>
            </div>
            <div class="grid gap-4 border-b border-line p-6 sm:grid-cols-3">
                <div>
                    <p class="section-label">Customer</p>
                    <p class="mt-1 font-semibold text-heading">{{ $sale->customer?->name ?? 'Walk-in Customer' }}</p>
                    @if ($sale->customer)<p class="text-sm text-muted">{{ $sale->customer->phone }}</p>@endif
                </div>
                <div>
                    <p class="section-label">Cashier</p>
                    <p class="mt-1 font-semibold text-heading">{{ $sale->user->name }}</p>
                </div>
                <div>
                    <p class="section-label">Date</p>
                    <p class="mt-1 font-semibold text-heading">{{ $sale->sold_at->format('d M Y') }}</p>
                    <p class="text-sm text-muted">{{ $sale->sold_at->format('h:i A') }}</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Item</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Discount</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach ($sale->items as $item)
                            <tr>
                                <td class="text-muted">{{ $loop->iteration }}</td>
                                <td>
                                    @if ($item->product && auth()->user()->can('products.view'))
                                        <a href="{{ route('products.show', $item->product) }}" class="font-medium text-heading hover:text-primary">{{ $item->product_name }}</a>
                                    @else
                                        <span class="font-medium text-heading">{{ $item->product_name }}</span>
                                    @endif
                                    <p class="text-xs text-muted">{{ $item->sku }}</p>
                                </td>
                                <td class="text-right tabular">{{ $qty($item) }} <span class="text-xs text-muted">{{ $item->product?->unit?->short_name }}</span></td>
                                <td class="text-right tabular">{{ money($item->unit_price, true) }}</td>
                                <td class="text-right tabular text-muted">{{ (float) $item->discount > 0 ? money($item->discount, true) : '—' }}</td>
                                <td class="text-right font-semibold tabular text-heading">{{ money($item->line_total, true) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end border-t border-line p-6">
                <dl class="w-full max-w-xs space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd class="tabular text-heading">{{ money($sale->subtotal, true) }}</dd></div>
                    @if ((float) $sale->discount_amount > 0)<div class="flex justify-between"><dt class="text-muted">Discount</dt><dd class="tabular text-heading">-{{ money($sale->discount_amount, true) }}</dd></div>@endif
                    @if ((float) $sale->tax_amount > 0)<div class="flex justify-between"><dt class="text-muted">Tax</dt><dd class="tabular text-heading">{{ money($sale->tax_amount, true) }}</dd></div>@endif
                    @if ((float) $sale->shipping > 0)<div class="flex justify-between"><dt class="text-muted">Other charges</dt><dd class="tabular text-heading">{{ money($sale->shipping, true) }}</dd></div>@endif
                    <div class="flex justify-between border-t border-line pt-2 text-base font-bold"><dt class="text-heading">Grand total</dt><dd class="tabular text-primary dark:text-primary-300">{{ money($sale->grand_total, true) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Paid</dt><dd class="tabular text-emerald-600 dark:text-emerald-400">{{ money($sale->paid_amount, true) }}</dd></div>
                    @if ((float) $sale->change_amount > 0)<div class="flex justify-between"><dt class="text-muted">Change given</dt><dd class="tabular text-heading">{{ money($sale->change_amount, true) }}</dd></div>@endif
                    <div class="flex justify-between font-semibold"><dt class="text-heading">Balance due</dt><dd @class(['tabular', 'text-danger' => (float) $sale->due_amount > 0, 'text-heading' => (float) $sale->due_amount <= 0])>{{ money($sale->due_amount, true) }}</dd></div>
                </dl>
            </div>
            @if ($sale->note)<p class="border-t border-line px-6 py-4 text-sm text-body"><span class="font-semibold text-heading">Note:</span> {{ $sale->note }}</p>@endif
        </div>

        <div class="space-y-6">
            @can('reports.profit')
                <x-card title="Profit" subtitle="Using cost at the time of sale">
                    @php $profit = $sale->profit; @endphp
                    <p @class(['text-kpi tabular', 'text-emerald-600 dark:text-emerald-400' => $profit >= 0, 'text-danger' => $profit < 0])>{{ money($profit, true) }}</p>
                    <p class="text-xs text-muted">Revenue excl. tax & charges minus item cost</p>
                </x-card>
            @endcan

            <x-card title="Payments" padding="p-0">
                <ul class="divide-y divide-line">
                    @forelse ($sale->payments->sortBy('paid_at') as $payment)
                        <li class="flex items-center gap-3 px-6 py-4">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-success/10 text-emerald-600"><x-icon name="banknote" size="w-4 h-4" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-heading">{{ $payment->method_label }} <span class="text-xs font-normal text-muted">· {{ $payment->type === 'sale' ? 'at sale' : 'received later' }}</span></p>
                                <p class="text-xs text-muted">{{ $payment->paid_at->format('d M Y, h:i A') }} · {{ $payment->user?->name }}{{ $payment->reference ? ' · Ref '.$payment->reference : '' }}</p>
                            </div>
                            <p class="font-semibold tabular text-heading">{{ money($payment->amount, true) }}</p>
                        </li>
                    @empty
                        <li><x-empty-state icon="wallet" title="No payments yet" description="This sale was made fully on credit." /></li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>

    @can('receivePayment', $sale)
        <x-modal name="receive-payment" title="Receive payment" :subtitle="'Balance due: '.money($sale->due_amount, true)" maxWidth="md" :show="$errors->any()">
            <form id="receive-payment" method="POST" action="{{ route('sales.payments.store', $sale) }}" class="space-y-4">
                @csrf
                <x-input name="amount" type="number" step="0.01" min="0.01" :max="(float) $sale->due_amount" label="Amount" icon="banknote" :value="(float) $sale->due_amount" required />
                <x-select name="method" label="Method" :options="\App\Models\Payment::METHODS" value="cash" required />
                <x-input name="reference" label="Reference / TID" placeholder="Optional" />
                <x-input name="note" label="Note" placeholder="Optional" />
            </form>
            <x-slot name="footer">
                <x-button variant="outline" @click="$dispatch('close-modal', 'receive-payment')">Cancel</x-button>
                <x-button type="submit" form="receive-payment" variant="success" icon="check" loading>Receive</x-button>
            </x-slot>
        </x-modal>
    @endcan
</x-app-layout>
