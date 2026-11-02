<x-app-layout :title="$payment->payment_no">
    @php $party = $payment->customer ?? $payment->supplier; @endphp
    <x-page-header :title="$payment->payment_no" :subtitle="$payment->type_label.' · '.fmtDate($payment->paid_at, true).' · '.$payment->branch->name" :breadcrumbs="['Finance' => null, 'Payments' => route('payments.index'), $payment->payment_no => null]">
        <x-button :href="route('payments.receipt', $payment)" target="_blank" variant="outline" icon="printer">Print receipt</x-button>
    </x-page-header>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card overflow-hidden">
            <div @class(['px-6 py-8 text-center text-white', 'bg-gradient-to-br from-success to-emerald-600' => $payment->is_incoming, 'bg-gradient-to-br from-primary-600 to-violet-600' => ! $payment->is_incoming])>
                <p class="text-sm opacity-90">{{ $payment->is_incoming ? 'Received' : 'Paid' }}</p>
                <p class="mt-1 text-4xl font-extrabold tabular">{{ money($payment->amount, true) }}</p>
                <p class="mt-2 text-sm opacity-90">{{ $payment->method_label }}{{ $payment->reference ? ' · Ref '.$payment->reference : '' }}</p>
            </div>
            <dl class="divide-y divide-line px-6 text-sm">
                <div class="flex justify-between py-3"><dt class="text-muted">{{ $payment->customer ? 'Customer' : 'Supplier' }}</dt><dd class="font-semibold text-heading">
                    @if ($payment->customer)<a href="{{ route('customers.show', $payment->customer) }}" class="hover:text-primary">{{ $payment->customer->name }}</a>
                    @elseif ($payment->supplier)<a href="{{ route('suppliers.show', $payment->supplier) }}" class="hover:text-primary">{{ $payment->supplier->name }}</a>@else Walk-in @endif</dd></div>
                <div class="flex justify-between py-3"><dt class="text-muted">Type</dt><dd><x-badge :color="$payment->type_color">{{ $payment->type_label }}</x-badge></dd></div>
                <div class="flex justify-between py-3"><dt class="text-muted">Recorded by</dt><dd class="font-medium text-heading">{{ $payment->user?->name }}</dd></div>
                @if ($party)<div class="flex justify-between py-3"><dt class="text-muted">Balance now</dt><dd class="font-semibold tabular text-heading">{{ money($party->balance, true) }}</dd></div>@endif
                @if ($payment->note)<div class="py-3"><dt class="text-muted">Note</dt><dd class="text-heading">{{ $payment->note }}</dd></div>@endif
            </dl>
        </div>
        <x-card class="xl:col-span-2" title="Applied to" padding="p-0">
            <table class="data-table">
                <thead><tr><th>Document</th><th>Date</th><th class="text-right">Document total</th><th class="text-right">Applied</th></tr></thead>
                <tbody>
                    @php $docs = $payment->allocations->map(fn ($a) => [$a->allocatable, (float) $a->amount]); if ($payment->payable) { $docs->prepend([$payment->payable, (float) $payment->amount]); } @endphp
                    @forelse ($docs as [$doc, $amount])
                        @php
                            [$label, $url, $date, $total] = match (true) {
                                $doc instanceof \App\Models\Sale => [$doc->invoice_no, route('sales.show', $doc), $doc->sold_at, $doc->grand_total],
                                $doc instanceof \App\Models\Purchase => [$doc->purchase_no, route('purchases.show', $doc), $doc->purchase_date, $doc->grand_total],
                                $doc instanceof \App\Models\SaleReturn => [$doc->return_no, route('sale-returns.show', $doc), $doc->returned_at, $doc->total],
                                $doc instanceof \App\Models\PurchaseReturn => [$doc->return_no, route('purchase-returns.show', $doc), $doc->returned_at, $doc->total],
                                default => ['—', null, null, 0],
                            };
                        @endphp
                        <tr><td><a href="{{ $url }}" class="font-semibold tabular text-primary">{{ $label }}</a></td><td>{{ fmtDate($date) }}</td><td class="text-right tabular">{{ money($total, true) }}</td><td class="text-right font-semibold tabular text-heading">{{ money($amount, true) }}</td></tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state icon="file-text" title="Not linked to an invoice" description="Applied to the opening balance or kept as an advance." /></td></tr>
                    @endforelse
                </tbody>
                @php $unapplied = round((float) $payment->amount - $docs->sum(fn ($d) => $d[1]), 2); @endphp
                @if ($unapplied > 0)
                    <tfoot><tr class="bg-subtle"><td colspan="3" class="px-4 py-3 text-muted">Opening balance / advance</td><td class="px-4 py-3 text-right font-semibold tabular">{{ money($unapplied, true) }}</td></tr></tfoot>
                @endif
            </table>
        </x-card>
    </div>
</x-app-layout>
