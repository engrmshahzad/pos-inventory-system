<x-app-layout title="Suggest Transfers">
    <x-page-header title="Suggested transfers" subtitle="Products at or below their low-stock level in one branch, with spare stock in another (the sender keeps at least 2× the low-stock level)."
        :breadcrumbs="['Inventory' => null, 'Transfers' => route('transfers.index'), 'Suggestions' => null]" />

    @forelse ($groups as $key => $rows)
        @php [$from, $to] = explode('-', $key); @endphp
        <form method="GET" action="{{ route('transfers.create') }}" class="mb-6" x-data="{ picked: @js($rows->pluck('product_id')->map(fn ($id) => (string) $id)) }">
            <input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}">
            <x-card padding="p-0">
                <x-slot name="title">{{ $branches[$from] }} → {{ $branches[$to] }}</x-slot>
                <x-slot name="actions">
                    <x-button type="submit" size="sm" icon="arrow-left-right" x-bind:disabled="!picked.length">Create transfer (<span x-text="picked.length"></span>)</x-button>
                </x-slot>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th class="w-10"></th><th>Product</th><th class="text-right">Low-stock level</th><th class="text-right">At {{ $codes[$to] }}</th><th class="text-right">At {{ $codes[$from] }}</th><th class="text-right w-32">Send</th></tr></thead>
                        <tbody>
                            @foreach ($rows as $r)
                                <tr>
                                    <td><input type="checkbox" class="form-checkbox" value="{{ $r['product_id'] }}" x-model="picked" aria-label="Include {{ $r['name'] }}"></td>
                                    <td><span class="font-medium text-heading">{{ $r['name'] }}</span><span class="block text-xs text-muted">{{ $r['sku'] }}</span></td>
                                    <td class="text-right tabular text-muted">{{ rtrim(rtrim(number_format($r['min'], 3), '0'), '.') }}</td>
                                    <td class="text-right font-semibold tabular {{ $r['to_qty'] <= 0 ? 'text-danger' : 'text-amber-600' }}">{{ rtrim(rtrim(number_format($r['to_qty'], 3), '0'), '.') }}</td>
                                    <td class="text-right tabular text-emerald-600 dark:text-emerald-400">{{ rtrim(rtrim(number_format($r['from_qty'], 3), '0'), '.') }}</td>
                                    <td class="text-right"><input type="number" min="1" name="products[{{ $r['product_id'] }}]" value="{{ $r['suggested'] }}" x-bind:disabled="!picked.includes('{{ $r['product_id'] }}')" class="form-control h-9 text-right tabular" aria-label="Quantity to send"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </form>
    @empty
        <x-card><x-empty-state icon="check-circle" title="Nothing to balance" description="No branch is low on a product that another branch has in surplus." /></x-card>
    @endforelse
</x-app-layout>
