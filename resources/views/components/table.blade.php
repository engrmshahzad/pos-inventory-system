@props(['paginator' => null, 'zebra' => false, 'perPageOptions' => [10, 25, 50, 100]])
{{-- Slots: toolbar (search/filters), head (<tr><th>..), default (<tr>..), empty (shown when no rows) --}}

<div {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    @isset($toolbar)
        <div class="flex flex-col gap-3 border-b border-line p-4 lg:flex-row lg:items-center lg:justify-between">
            {{ $toolbar }}
        </div>
    @endisset

    <div class="scrollbar-thin max-h-[70vh] overflow-auto">
        <table @class(['data-table', 'zebra' => $zebra])>
            <thead>{{ $head }}</thead>
            <tbody>{{ $slot }}</tbody>
        </table>
        @isset($empty)
            {{ $empty }}
        @endisset
    </div>

    @if ($paginator && ($paginator->hasPages() || $paginator->total() > min($perPageOptions)))
        <div class="flex flex-col gap-3 border-t border-line px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2 text-xs text-muted">
                <span>Rows per page</span>
                <form method="GET" class="inline">
                    @foreach (request()->except(['per_page', 'page']) as $key => $val)
                        @if (is_scalar($val))<input type="hidden" name="{{ $key }}" value="{{ $val }}">@endif
                    @endforeach
                    <select name="per_page" onchange="this.form.submit()" aria-label="Rows per page"
                        class="h-8 rounded-md border-line bg-card py-0 pl-2 pr-7 text-xs text-heading focus:border-primary focus:ring-primary/20">
                        @foreach ($perPageOptions as $n)
                            <option value="{{ $n }}" @selected($paginator->perPage() == $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </form>
                <span class="tabular">{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ number_format($paginator->total()) }}</span>
            </div>
            {{ $paginator->onEachSide(1)->links() }}
        </div>
    @endif
</div>
