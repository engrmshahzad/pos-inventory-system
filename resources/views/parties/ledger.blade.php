{{-- Ledger table shared by customer and supplier profiles. $ledger from LedgerService, $balanceLabel, $statementRoute. --}}
<x-card padding="p-0">
    <x-slot name="title">Ledger</x-slot>
    <x-slot name="actions">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="tab" value="ledger">
            <input type="date" name="from" value="{{ $from?->toDateString() }}" class="form-control h-9 w-auto" aria-label="From">
            <input type="date" name="to" value="{{ $to?->toDateString() }}" class="form-control h-9 w-auto" aria-label="To">
            <x-button type="submit" size="sm" variant="outline" icon="filter">Apply</x-button>
            <x-button size="sm" :href="$statementRoute.'?'.http_build_query(array_filter(['from' => $from?->toDateString(), 'to' => $to?->toDateString()]))" target="_blank" icon="file-text">Statement PDF</x-button>
        </form>
    </x-slot>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Description</th><th>Reference</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Balance</th></tr></thead>
            <tbody>
                @if ($from)
                    <tr class="!bg-subtle/60"><td>{{ fmtDate($from) }}</td><td class="font-medium text-heading" colspan="4">Balance brought forward</td><td class="text-right font-semibold tabular text-heading">{{ money($ledger['opening'], true) }}</td></tr>
                @endif
                @forelse ($ledger['entries'] as $e)
                    <tr>
                        <td class="whitespace-nowrap">{{ fmtDate($e['date']) }}<span class="block text-xs text-muted">{{ $e['date']->format('h:i A') }}</span></td>
                        <td class="text-heading">{{ $e['description'] }}</td>
                        @php $openable = $e['url'] && (auth()->user()->isOwner() || $e['branch_id'] === null || $e['branch_id'] === auth()->user()->branch_id); @endphp
                        <td class="tabular">@if ($openable)<a href="{{ $e['url'] }}" class="font-medium text-primary hover:text-primary-hover">{{ $e['reference'] }}</a>@else{{ $e['reference'] }}@endif</td>
                        <td class="text-right tabular">{{ $e['debit'] > 0 ? money($e['debit'], true) : '—' }}</td>
                        <td class="text-right tabular">{{ $e['credit'] > 0 ? money($e['credit'], true) : '—' }}</td>
                        <td @class(['text-right font-semibold tabular', 'text-danger' => $e['balance'] > 0, 'text-heading' => $e['balance'] <= 0])>{{ money($e['balance'], true) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="file-text" title="No entries in this period" /></td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-subtle font-semibold">
                    <td colspan="3" class="px-4 py-3 text-heading">Totals · {{ $balanceLabel }}</td>
                    <td class="px-4 py-3 text-right tabular text-heading">{{ money($ledger['debit'], true) }}</td>
                    <td class="px-4 py-3 text-right tabular text-heading">{{ money($ledger['credit'], true) }}</td>
                    <td class="px-4 py-3 text-right tabular text-primary dark:text-primary-300">{{ money($ledger['closing'], true) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-card>
