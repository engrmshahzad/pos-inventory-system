@props(['user' => null, 'name' => null, 'src' => null, 'size' => 'md'])
@php
    $name = $user?->name ?? $name ?? '?';
    $src = $user?->avatar_url ?? $src;
    $initials = $user?->initials ?? collect(explode(' ', $name))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $sizes = ['xs' => 'h-7 w-7 text-[10px]', 'sm' => 'h-8 w-8 text-xs', 'md' => 'h-10 w-10 text-sm', 'lg' => 'h-14 w-14 text-lg', 'xl' => 'h-20 w-20 text-2xl'];
    $palette = ['bg-indigo-500', 'bg-emerald-500', 'bg-amber-500', 'bg-sky-500', 'bg-rose-500', 'bg-violet-500', 'bg-teal-500'];
    $bg = $palette[crc32($name) % count($palette)];
@endphp

@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" {{ $attributes->merge(['class' => 'shrink-0 rounded-full object-cover ring-2 ring-card '.$sizes[$size]]) }}>
@else
    <span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center rounded-full font-semibold text-white ring-2 ring-card {$bg} {$sizes[$size]}"]) }} aria-label="{{ $name }}">{{ $initials }}</span>
@endif
