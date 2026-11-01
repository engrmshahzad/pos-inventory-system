@props(['color' => 'slate', 'dot' => false, 'icon' => null])
@php
    $colors = [
        'primary' => 'bg-primary/10 text-primary dark:text-primary-300',
        'success' => 'bg-success/10 text-emerald-600 dark:text-emerald-400',
        'warning' => 'bg-warning/10 text-amber-600 dark:text-amber-400',
        'danger' => 'bg-danger/10 text-red-600 dark:text-red-400',
        'info' => 'bg-info/10 text-sky-600 dark:text-sky-400',
        'slate' => 'bg-slate-500/10 text-slate-600 dark:text-slate-300',
        'violet' => 'bg-violet-500/10 text-violet-600 dark:text-violet-300',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold leading-none whitespace-nowrap '.($colors[$color] ?? $colors['slate'])]) }}>
    @if ($dot)<span class="h-1.5 w-1.5 rounded-full bg-current"></span>@endif
    @if ($icon)<x-icon :name="$icon" size="w-3.5 h-3.5" />@endif
    {{ $slot }}
</span>
