{{-- Payments table used on the payments page and on customer / supplier profiles. --}}
<x-table :paginator="$payments">
    <x-slot name="head">
        <tr><th>Date</th><th>Number</th><th>Type</th>@if ($showParty)<th>Customer / supplier</th>@endif<th>Branch</th><th>Method</th><th>For</th><th class="text-right">Amount</th><th class="text-right"></th></tr>
    </x-slot>
    @foreach ($payments as $payment)
        @php
            $doc = $payment->payable;
            $docLabel = $doc?->invoice_no ?? $doc?->purchase_no ?? $doc?->return_no;
            $docUrl = match (true) {
                $doc instanceof \App\Models\Sale => route('sales.show', $doc),
                $doc instanceof \App\Models\Purchase => route('purchases.show', $doc),
                $doc instanceof \App\Models\SaleReturn => route('sale-returns.show', $doc),
                $doc instanceof \App\Models\PurchaseReturn => route('purchase-returns.show', $doc),
                default => null,
            };
        @endphp
        <tr>
            <td class="whitespace-nowrap">{{ fmtDate($payment->paid_at) }}<span class="block text-xs text-muted">{{ $payment->paid_at->format('h:i A') }}</span></td>
            <td class="tabular">
                @if ($payment->payment_no)
                    <a href="{{ route('payments.show', $payment) }}" class="font-semibold text-heading hover:text-primary">{{ $payment->payment_no }}</a>
                @else
                    <span class="text-muted">—</span>
                @endif
            </td>
            <td><x-badge :color="$payment->type_color">{{ $payment->type_label }}</x-badge></td>
            @if ($showParty)
                <td>
                    @if ($payment->customer)<a href="{{ route('customers.show', $payment->customer) }}" class="font-medium text-heading hover:text-primary">{{ $payment->customer->name }}</a>
                    @elseif ($payment->supplier)<a href="{{ route('suppliers.show', $payment->supplier) }}" class="font-medium text-heading hover:text-primary">{{ $payment->supplier->name }}</a>
                    @else<span class="text-muted">Walk-in</span>@endif
                </td>
            @endif
            <td><x-badge color="slate">{{ $payment->branch->code }}</x-badge></td>
            <td>{{ $payment->method_label }}@if ($payment->reference)<span class="block text-xs text-muted">Ref {{ $payment->reference }}</span>@endif</td>
            <td class="tabular">
                @if ($docUrl)<a href="{{ $docUrl }}" class="text-primary hover:text-primary-hover">{{ $docLabel }}</a>@else<span class="text-muted">{{ $payment->allocations_count ?? 0 }} invoice(s)</span>@endif
            </td>
            <td @class(['text-right font-semibold tabular', 'text-emerald-600 dark:text-emerald-400' => $payment->is_incoming, 'text-danger' => ! $payment->is_incoming])>
                {{ $payment->is_incoming ? '+' : '−' }}{{ money($payment->amount, true) }}
            </td>
            <td class="text-right">
                @if ($payment->payment_no)
                    <a href="{{ route('payments.receipt', $payment) }}" target="_blank" class="rounded-lg p-1.5 text-muted hover:bg-subtle hover:text-heading" title="Print"><x-icon name="printer" size="w-4 h-4" /></a>
                @endif
            </td>
        </tr>
    @endforeach
    @if ($payments->isEmpty())
        <x-slot name="empty"><x-empty-state icon="coins" title="No payments found" /></x-slot>
    @endif
</x-table>
