@props(['title', 'subtitle' => null, 'breadcrumbs' => []])
{{-- breadcrumbs: ['Settings' => null, 'Branches' => route(...)] ; last item is the current page --}}

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        <nav aria-label="Breadcrumb" class="mb-1.5">
            <ol class="flex flex-wrap items-center gap-1.5 text-xs text-muted">
                <li><a href="{{ route('dashboard') }}" class="hover:text-primary">Home</a></li>
                @foreach ($breadcrumbs as $crumb => $url)
                    <li><x-icon name="chevron-right" size="w-3 h-3" /></li>
                    <li>
                        @if ($url && ! $loop->last)
                            <a href="{{ $url }}" class="hover:text-primary">{{ $crumb }}</a>
                        @else
                            <span @class(['font-medium text-body' => $loop->last])>{{ $crumb }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
        <h1 class="text-page-title text-heading">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>@endif
    </div>
    @if (trim($slot))
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
