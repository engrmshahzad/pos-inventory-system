{{-- Product image, or a coloured initial tile when there is none. --}}
@php
    $sizeClass = $size ?? 'h-11 w-11 text-sm';
    $color = $product->category?->color ?? '#4F46E5';
@endphp
@if ($product->image_url)
    <img src="{{ $product->image_url }}" alt="" loading="lazy" class="{{ $sizeClass }} shrink-0 rounded-xl border border-line bg-white object-cover">
@else
    <span class="{{ $sizeClass }} flex shrink-0 items-center justify-center rounded-xl font-bold" style="background: {{ $color }}1a; color: {{ $color }}">
        {{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}
    </span>
@endif
