{{-- Report table (screen). $rows: paginator or collection --}}
@php $styles = ['total' => 'bg-primary/5 font-bold text-heading', 'subtotal' => 'bg-subtle font-semibold text-heading', 'heading' => 'bg-subtle/60', 'muted' => 'text-muted text-xs', 'indent' => '']; @endphp
<div class="overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>@foreach ($result->columns as $col)<th @class(['text-right' => \App\Reports\ReportFormat::isNumeric($col[1])])>{{ $col[0] }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php $style = $row['_style'] ?? ''; @endphp
                <tr class="{{ $styles[$style] ?? '' }}">
                    @foreach ($result->columns as $key => [$label, $type])
                        @php
                            $type = $loop->first ? $type : ($row['_type'] ?? $type);
                            $value = $row[$key] ?? null;
                            $numeric = \App\Reports\ReportFormat::isNumeric($type);
                        @endphp
                        <td @class(['whitespace-nowrap tabular text-right' => $numeric, 'pl-10' => $style === 'indent' && $loop->first, 'text-danger' => $numeric && is_numeric($value) && $value < 0 && $type === 'money', 'section-label !text-heading' => $style === 'heading'])>
                            @if ($loop->first && ! empty($row['_url']))
                                <a href="{{ $row['_url'] }}" class="font-medium text-heading hover:text-primary">{{ $value }}</a>
                            @elseif ($type === 'badge' && $value)
                                <x-badge :color="\App\Reports\ReportFormat::badgeColor($value)" dot>{{ $value }}</x-badge>
                            @elseif ($style !== 'heading' || $loop->first)
                                {{ \App\Reports\ReportFormat::cell($value, $type) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($result->columns) }}"><x-empty-state icon="inbox" title="No data for these filters" description="Try a wider date range or another branch." /></td></tr>
            @endforelse
        </tbody>
        @if ($result->totals && $result->rows->isNotEmpty())
            <tfoot>
                <tr class="bg-subtle font-semibold text-heading">
                    @foreach ($result->columns as $key => [$label, $type])
                        <td @class(['px-4 py-3', 'whitespace-nowrap text-right tabular' => \App\Reports\ReportFormat::isNumeric($type)])>{{ array_key_exists($key, $result->totals) ? ($loop->first ? $result->totals[$key] : \App\Reports\ReportFormat::cell($result->totals[$key], $type)) : '' }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
</div>
