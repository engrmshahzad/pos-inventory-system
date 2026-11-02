<x-app-layout title="Stock Count">
    <x-page-header title="Stock Count" subtitle="Physical audit: count what is on the shelf and correct the system in one go." :breadcrumbs="['Inventory' => null, 'Stock Count' => null]" />

    <x-card class="mb-6" title="Start a new count" :subtitle="$currentBranch ? 'At '.$currentBranch->name.'. The current system quantity of each product is saved when you start.' : 'Choose a branch in the branch switcher first.'">
        <form method="POST" action="{{ route('stock-counts.store') }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
            @csrf
            <x-select name="category_id" label="Category" :options="$categories->all()" placeholder="All products" class="sm:w-64" />
            <x-input name="note" label="Note" placeholder="e.g. Month-end count" class="flex-1" />
            <x-button type="submit" icon="list" loading :disabled="! $currentBranch">Start count</x-button>
        </form>
    </x-card>

    <x-table :paginator="$counts">
        <x-slot name="head"><tr><th>Count</th><th>Started</th><th>Branch</th><th>Scope</th><th class="text-right">Counted</th><th>Status</th><th>Result</th><th>By</th></tr></x-slot>
        @foreach ($counts as $c)
            @php [$label, $color] = $c->status_badge; @endphp
            <tr>
                <td><a href="{{ route('stock-counts.show', $c) }}" class="font-semibold tabular text-heading hover:text-primary">{{ $c->count_no }}</a></td>
                <td class="whitespace-nowrap">{{ fmtDate($c->created_at, true) }}</td>
                <td><x-badge color="slate">{{ $c->branch->code }}</x-badge></td>
                <td>{{ $c->category?->name ?? 'All products' }}</td>
                <td class="text-right tabular">{{ $c->counted_count }} / {{ $c->items_count }}</td>
                <td><x-badge :color="$color" dot>{{ $label }}</x-badge></td>
                <td>
                    @if ($c->adjustment)
                        <a href="{{ route('adjustments.show', $c->adjustment) }}" class="text-primary">{{ $c->adjustment->adjustment_no }}</a>
                        <span class="text-xs {{ (float) $c->adjustment->total_value < 0 ? 'text-danger' : 'text-emerald-600' }}">{{ money($c->adjustment->total_value) }}</span>
                    @elseif ($c->status === 'applied')<span class="text-muted">No differences</span>@else<span class="text-muted">—</span>@endif
                </td>
                <td>{{ $c->creator->name }}</td>
            </tr>
        @endforeach
        @if ($counts->isEmpty())
            <x-slot name="empty"><x-empty-state icon="list" title="No stock counts yet" description="Start one above when you are ready to count the shelves." /></x-slot>
        @endif
    </x-table>
</x-app-layout>
