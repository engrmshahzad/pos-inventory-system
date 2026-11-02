<x-app-layout title="Stock Adjustments">
    <x-page-header title="Stock Adjustments" :subtitle="'Damaged, expired, lost or found stock. Manager adjustments above '.money($threshold).' need owner approval.'" :breadcrumbs="['Inventory' => null, 'Adjustments' => null]">
        <x-button :href="route('stock-counts.index')" variant="outline" icon="list">Stock count</x-button>
        <x-button :href="route('adjustments.create')" icon="plus">New Adjustment</x-button>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat-card label="Pending approval" :value="$counts['pending']" icon="clock" color="warning" :href="route('adjustments.index', ['status' => 'pending'])" />
        <x-stat-card label="Stock lost (value)" :value="money(abs($counts['loss']))" icon="trending-down" color="danger" />
        <x-stat-card label="Stock gained (value)" :value="money($counts['gain'])" icon="trending-up" color="success" />
        <x-stat-card label="This month" :value="$counts['month']" icon="calendar" color="info" hint="adjustments" />
    </div>

    <div class="mb-4 flex gap-6 overflow-x-auto border-b border-line">
        @foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
            <a href="{{ route('adjustments.index', array_filter(['status' => $key])) }}" @class(['-mb-px inline-flex items-center gap-2 border-b-2 pb-3 text-sm font-semibold', 'border-primary text-primary' => ($status ?? '') === $key, 'border-transparent text-muted hover:text-heading' => ($status ?? '') !== $key])>
                {{ $label }} @if ($key === 'pending' && $counts['pending'])<span class="rounded-full bg-warning px-1.5 text-[11px] font-bold text-white">{{ $counts['pending'] }}</span>@endif
            </a>
        @endforeach
    </div>

    <x-table :paginator="$adjustments">
        <x-slot name="head"><tr><th>Adjustment</th><th>Date</th><th>Branch</th><th>Reason</th><th class="text-right">Items</th><th class="text-right">Value impact</th><th>Status</th><th>By</th></tr></x-slot>
        @foreach ($adjustments as $a)
            @php [$label, $color] = $a->status_badge; @endphp
            <tr>
                <td><a href="{{ route('adjustments.show', $a) }}" class="font-semibold tabular text-heading hover:text-primary">{{ $a->adjustment_no }}</a>
                    @if ($a->source !== 'manual')<span class="block text-xs text-muted">{{ $a->source === 'stock_count' ? 'From stock count' : 'From expiry list' }}</span>@endif</td>
                <td class="whitespace-nowrap">{{ $a->adjustment_date->format('d M Y') }}</td>
                <td><x-badge color="slate">{{ $a->branch->code }}</x-badge></td>
                <td>{{ $a->reason_label }}</td>
                <td class="text-right tabular">{{ $a->items_count }}</td>
                <td @class(['text-right font-semibold tabular', 'text-danger' => (float) $a->total_value < 0, 'text-emerald-600 dark:text-emerald-400' => (float) $a->total_value > 0])>{{ (float) $a->total_value > 0 ? '+' : '' }}{{ money($a->total_value) }}</td>
                <td><x-badge :color="$color" dot>{{ $label }}</x-badge></td>
                <td>{{ $a->creator->name }}</td>
            </tr>
        @endforeach
        @if ($adjustments->isEmpty())
            <x-slot name="empty"><x-empty-state icon="sliders" title="No adjustments" /></x-slot>
        @endif
    </x-table>
</x-app-layout>
