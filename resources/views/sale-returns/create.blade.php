<x-app-layout title="New Sale Return">
    <x-page-header title="New Sale Return" subtitle="Find the sale by invoice number, or scan the barcode on the receipt." :breadcrumbs="['Sales' => null, 'Sale Returns' => route('sale-returns.index'), 'New' => null]" />

    <form method="GET" class="card mb-6 flex flex-col gap-3 p-4 sm:flex-row sm:items-start">
        <div class="flex-1">
            <x-input name="invoice" icon="scan" placeholder="Scan receipt barcode or type invoice no, e.g. HYD-S-000012" :value="$sale?->invoice_no ?? request('invoice')" autofocus />
        </div>
        <x-button type="submit" icon="search">Find sale</x-button>
    </form>

    @if ($sale)
        <form method="POST" action="{{ route('sale-returns.store') }}"
            x-data="{
                lines: @js($lines->map(fn ($l) => ['id' => $l['item']->id, 'max' => $l['remaining'], 'price' => $l['unit_refund'], 'qty' => 0])->values()),
                method: @js(old('refund_method', $sale->customer_id && (float) $sale->due_amount > 0 ? 'adjust' : 'cash')),
                get total() { return Math.round(this.lines.reduce((s, l) => s + (Number(l.qty) || 0) * l.price, 0) * 100) / 100 },
                all() { this.lines.forEach(l => l.qty = l.max) },
            }">
            @csrf
            <input type="hidden" name="sale_id" value="{{ $sale->id }}">
            <div class="grid gap-6 xl:grid-cols-3">
                <div class="xl:col-span-2">
                    <x-card :title="$sale->invoice_no" :subtitle="$sale->sold_at->format('d M Y, h:i A').' · '.$sale->branch->name.' · '.($sale->customer?->name ?? 'Walk-in Customer').' · '.money($sale->grand_total, true)" padding="p-0">
                        <x-slot name="actions"><x-button size="sm" variant="secondary" @click="all()">Return all</x-button></x-slot>
                        @error('items')<p class="mx-6 mt-4 rounded-lg bg-danger/10 px-3 py-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead><tr><th>Item</th><th class="text-right">Sold</th><th class="text-right">Returned</th><th class="text-right">Refund / unit</th><th class="w-36">Return qty</th><th class="text-right">Refund</th></tr></thead>
                                <tbody>
                                    @foreach ($lines as $i => $l)
                                        @php $p = $l['item']->product; @endphp
                                        <tr @class(['opacity-50' => $l['remaining'] <= 0])>
                                            <td><span class="font-medium text-heading">{{ $l['item']->product_name }}</span><span class="block text-xs text-muted">{{ $l['item']->sku }}</span></td>
                                            <td class="text-right tabular">{{ $p?->formatQty($l['sold']) ?? $l['sold'] }}</td>
                                            <td class="text-right tabular {{ $l['returned'] > 0 ? 'text-warning' : 'text-muted' }}">{{ $p?->formatQty($l['returned']) ?? $l['returned'] }}</td>
                                            <td class="text-right tabular">{{ money($l['unit_refund'], true) }}</td>
                                            <td>
                                                <input type="number" min="0" :max="lines[{{ $i }}].max" step="{{ $p?->unit?->allow_decimal ? '0.001' : '1' }}" name="items[{{ $l['item']->id }}]"
                                                    x-model.number="lines[{{ $i }}].qty" @disabled($l['remaining'] <= 0) class="form-control h-9 tabular" aria-label="Return quantity">
                                                <span class="text-xs text-muted">max {{ $p?->formatQty($l['remaining']) ?? $l['remaining'] }}</span>
                                            </td>
                                            <td class="text-right font-semibold tabular text-heading" x-text="formatMoney((Number(lines[{{ $i }}].qty) || 0) * lines[{{ $i }}].price)"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="border-t border-line px-6 py-3 text-xs text-muted">Refund per unit includes the item's share of any bill discount. Other charges are not refunded.</p>
                    </x-card>
                </div>
                <div>
                    <div class="card sticky top-[86px] space-y-5 p-6">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-muted">Refund total</p>
                            <p class="text-3xl font-extrabold tabular text-primary dark:text-primary-300" x-text="formatMoney(total)"></p>
                        </div>
                        <div>
                            <span class="form-label">Refund method</span>
                            <div class="space-y-2">
                                @if ($sale->customer_id)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                        <input type="radio" name="refund_method" value="adjust" x-model="method" class="mt-0.5 h-4 w-4 border-line text-primary">
                                        <span><span class="block text-sm font-semibold text-heading">Adjust in khata</span><span class="block text-xs text-muted">Reduce {{ $sale->customer->name }}'s due (now {{ money($sale->customer->balance, true) }})</span></span>
                                    </label>
                                @endif
                                @foreach ($methods as $k => $label)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                        <input type="radio" name="refund_method" value="{{ $k }}" x-model="method" class="h-4 w-4 border-line text-primary">
                                        <span class="text-sm font-semibold text-heading">{{ $label }} refund</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('refund_method')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <x-input name="reason" label="Reason" placeholder="e.g. Damaged packet, wrong item" />
                        <x-button type="submit" icon="undo" class="w-full" loading x-bind:disabled="total <= 0">Save return</x-button>
                    </div>
                </div>
            </div>
        </form>
    @else
        <x-card><x-empty-state icon="scan" title="Find the sale to return" description="Scan the barcode printed at the bottom of the receipt, or type the invoice number above." /></x-card>
    @endif
</x-app-layout>
