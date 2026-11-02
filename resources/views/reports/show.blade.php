<x-app-layout :title="$report->title">
    @php $query = request()->except('page'); @endphp
    <x-page-header :title="$report->title" :subtitle="$subtitle" :breadcrumbs="['Reports' => route('reports.index'), $report->title => null]">
        <x-button :href="route('reports.export', ['key' => $report->key, 'format' => 'print'] + $query)" target="_blank" variant="outline" icon="printer">Print</x-button>
        <x-button :href="route('reports.export', ['key' => $report->key, 'format' => 'pdf'] + $query)" target="_blank" variant="outline" icon="file-text">PDF</x-button>
        <x-button :href="route('reports.export', ['key' => $report->key, 'format' => 'xlsx'] + $query)" variant="success" icon="download">Excel</x-button>
    </x-page-header>

    {{-- Filters --}}
    <form method="GET" class="card mb-6 p-4" x-data="{ preset: @js($ctx->preset) }">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end">
            @if ($report->usesDates)
                <div class="min-w-0 flex-1">
                    <span class="form-label">Period</span>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($presets as $key => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="preset" value="{{ $key }}" x-model="preset" class="peer sr-only" @if ($key !== 'custom') onchange="this.form.submit()" @endif>
                                <span class="inline-flex h-9 items-center rounded-full border border-line px-3.5 text-xs font-semibold text-body transition peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white hover:border-primary/40">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div x-show="preset === 'custom'" x-cloak class="mt-3 flex flex-wrap items-center gap-2">
                        <input type="date" name="from" value="{{ $ctx->from->toDateString() }}" class="form-control h-9 w-auto" aria-label="From">
                        <span class="text-muted">to</span>
                        <input type="date" name="to" value="{{ $ctx->to->toDateString() }}" class="form-control h-9 w-auto" aria-label="To">
                    </div>
                </div>
            @endif
            <div class="flex flex-wrap items-end gap-3">
                @if ($ctx->user->isOwner())
                    <div>
                        <label class="form-label" for="branch">Branch</label>
                        <select id="branch" name="branch" class="form-control h-9 w-auto min-w-[160px] py-0" onchange="this.form.submit()">
                            <option value="all">All branches</option>
                            @foreach ($branches as $b)<option value="{{ $b->id }}" @selected($ctx->branchId === $b->id)>{{ $b->name }}</option>@endforeach
                        </select>
                    </div>
                @endif
                @foreach ($report->filters() as $name => [$label, $type, $options])
                    <div>
                        <label class="form-label" for="f-{{ $name }}">{{ $label }}</label>
                        <select id="f-{{ $name }}" name="{{ $name }}" class="form-control h-9 w-auto min-w-[140px] py-0" onchange="this.form.submit()">
                            @foreach ($options as $value => $text)<option value="{{ $value }}" @selected((string) $ctx->param($name) === (string) $value)>{{ $text }}</option>@endforeach
                        </select>
                    </div>
                @endforeach
                <x-button type="submit" size="sm" icon="filter" class="!h-9">Apply</x-button>
            </div>
        </div>
    </form>

    @if ($result->cards)
        <div @class(['mb-6 grid grid-cols-2 gap-4', 'xl:grid-cols-4' => count($result->cards) >= 4, 'xl:grid-cols-3' => count($result->cards) === 3, 'xl:grid-cols-5' => count($result->cards) === 5])>
            @foreach ($result->cards as $card)
                <x-stat-card :label="$card[0]" :value="$card[1]" :icon="$card[2] ?? 'bar-chart'" :color="$card[3] ?? 'primary'" />
            @endforeach
        </div>
    @endif

    @if ($result->chart)
        <x-card class="mb-6" padding="px-3 pb-3 pt-4">
            <div class="h-[300px]" x-data="apexChart(@js($result->chart))"></div>
        </x-card>
    @endif

    <x-card padding="p-0">
        @include('reports.partials.table', ['rows' => $pagedRows ?? $result->rows])
        @if ($pagedRows && $pagedRows->hasPages())
            <div class="flex items-center justify-between border-t border-line px-4 py-3 text-xs text-muted">
                <span>{{ number_format($pagedRows->total()) }} rows · exports include all rows</span>
                {{ $pagedRows->links() }}
            </div>
        @endif
    </x-card>
    @if ($result->note)<p class="mt-3 text-xs text-muted">{{ $result->note }}</p>@endif
</x-app-layout>
