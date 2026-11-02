<x-app-layout title="New Purchase Return">
    <x-page-header title="New Purchase Return" subtitle="Send goods back to the supplier. Stock is removed from the branch." :breadcrumbs="['Purchases' => null, 'Purchase Returns' => route('purchase-returns.index'), 'New' => null]" />

    <form method="GET" class="card mb-6 flex flex-col gap-3 p-4 sm:flex-row sm:items-start">
        <div class="flex-1"><x-input name="purchase" icon="search" placeholder="Purchase no, e.g. HYD-P-000004" :value="$purchase?->purchase_no ?? request('purchase')" autofocus /></div>
        <x-button type="submit" icon="search">Find purchase</x-button>
    </form>

    @if ($purchase)
        <form method="POST" action="{{ route('purchase-returns.store') }}"
            x-data="{
                lines: @js($lines->map(fn ($l) => ['max' => min($l['remaining'], max(0, $l['in_stock'])), 'price' => $l['unit_cost'], 'qty' => 0])->values()),
                method: @js(old('settle_method', 'adjust')),
                get total() { return Math.round(this.lines.reduce((s, l) => s + (Number(l.qty) || 0) * l.price, 0) * 100) / 100 },
            }">
            @csrf
            <input type="hidden" name="purchase_id" value="{{ $purchase->id }}">
            <div class="grid gap-6 xl:grid-cols-3">
                <x-card class="xl:col-span-2" :title="$purchase->purchase_no" :subtitle="fmtDate($purchase->purchase_date).' · '.$purchase->supplier->name.' · '.$purchase->branch->name.' · '.money($purchase->grand_total, true)" padding="p-0">
                    @error('items')<p class="mx-6 mt-4 rounded-lg bg-danger/10 px-3 py-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Item</th><th class="text-right">Bought</th><th class="text-right">Returned</th><th class="text-right">In stock</th><th class="text-right">Cost / unit</th><th class="w-32">Return qty</th><th class="text-right">Value</th></tr></thead>
                            <tbody>
                                @foreach ($lines as $i => $l)
                                    @php $p = $l['item']->product; $max = min($l['remaining'], max(0, $l['in_stock'])); @endphp
                                    <tr @class(['opacity-50' => $max <= 0])>
                                        <td class="font-medium text-heading">{{ $l['item']->product_name }}</td>
                                        <td class="text-right tabular">{{ $p?->formatQty($l['bought']) }}</td>
                                        <td class="text-right tabular text-muted">{{ $p?->formatQty($l['returned']) }}</td>
                                        <td class="text-right tabular {{ $l['in_stock'] < $l['remaining'] ? 'text-warning' : '' }}">{{ $p?->formatQty($l['in_stock']) }}</td>
                                        <td class="text-right tabular">{{ money($l['unit_cost'], true) }}</td>
                                        <td><input type="number" min="0" :max="lines[{{ $i }}].max" step="{{ $p?->unit?->allow_decimal ? '0.001' : '1' }}" name="items[{{ $l['item']->id }}]" x-model.number="lines[{{ $i }}].qty" @disabled($max <= 0) class="form-control h-9 tabular" aria-label="Return quantity"></td>
                                        <td class="text-right font-semibold tabular" x-text="formatMoney((Number(lines[{{ $i }}].qty) || 0) * lines[{{ $i }}].price)"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="border-t border-line px-6 py-3 text-xs text-muted">You can return at most what is still in this branch's stock.</p>
                </x-card>
                <div class="card sticky top-[86px] space-y-5 self-start p-6">
                    <div><p class="text-xs font-medium uppercase tracking-wide text-muted">Return value</p><p class="text-3xl font-extrabold tabular text-primary dark:text-primary-300" x-text="formatMoney(total)"></p></div>
                    <div>
                        <span class="form-label">How does the supplier settle it?</span>
                        <div class="space-y-2">
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                <input type="radio" name="settle_method" value="adjust" x-model="method" class="mt-0.5 h-4 w-4 border-line text-primary">
                                <span><span class="block text-sm font-semibold text-heading">Deduct from payable</span><span class="block text-xs text-muted">You owe {{ $purchase->supplier->name }} {{ money($purchase->supplier->balance, true) }}</span></span>
                            </label>
                            @foreach ($methods as $k => $label)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                    <input type="radio" name="settle_method" value="{{ $k }}" x-model="method" class="h-4 w-4 border-line text-primary"><span class="text-sm font-semibold text-heading">Refund received by {{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <x-input name="reason" label="Reason" placeholder="e.g. Expired, damaged in transit" />
                    <x-button type="submit" icon="rotate-ccw" class="w-full" loading x-bind:disabled="total <= 0">Save return</x-button>
                </div>
            </div>
        </form>
    @else
        <x-card title="Recent purchases" padding="p-0">
            <ul class="divide-y divide-line">
                @forelse ($recent as $p)
                    <li><a href="{{ route('purchase-returns.create', ['purchase' => $p->purchase_no]) }}" class="flex items-center justify-between px-6 py-3.5 hover:bg-subtle">
                        <span><span class="font-semibold tabular text-heading">{{ $p->purchase_no }}</span><span class="block text-xs text-muted">{{ $p->supplier->name }} · {{ fmtDate($p->purchase_date) }}</span></span>
                        <span class="font-semibold tabular">{{ money($p->grand_total) }}</span></a></li>
                @empty
                    <li><x-empty-state icon="truck" title="No received purchases yet" /></li>
                @endforelse
            </ul>
        </x-card>
    @endif
</x-app-layout>
