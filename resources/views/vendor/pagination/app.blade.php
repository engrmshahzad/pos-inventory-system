@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center gap-1">
        @php $base = 'inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs font-semibold tabular transition'; @endphp

        @if ($paginator->onFirstPage())
            <span class="{{ $base }} text-muted/60" aria-disabled="true"><x-icon name="chevron-left" size="w-4 h-4" /></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $base }} text-body hover:bg-subtle" aria-label="Previous"><x-icon name="chevron-left" size="w-4 h-4" /></a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="{{ $base }} text-muted">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="{{ $base }} bg-primary text-white shadow-sm shadow-primary/30">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="{{ $base }} text-body hover:bg-subtle">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $base }} text-body hover:bg-subtle" aria-label="Next"><x-icon name="chevron-right" size="w-4 h-4" /></a>
        @else
            <span class="{{ $base }} text-muted/60" aria-disabled="true"><x-icon name="chevron-right" size="w-4 h-4" /></span>
        @endif
    </nav>
@endif
