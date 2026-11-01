@props(['icon' => 'inbox', 'title' => 'Nothing here yet', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <div class="relative mb-5">
        <div class="absolute inset-0 -m-3 rounded-full bg-primary/5"></div>
        <div class="absolute inset-0 -m-6 rounded-full bg-primary/[.03]"></div>
        <div class="relative flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-primary/15 to-violet-500/15 text-primary dark:text-primary-300">
            <x-icon :name="$icon" size="w-8 h-8" />
        </div>
    </div>
    <h3 class="text-base font-semibold text-heading">{{ $title }}</h3>
    @if ($description)<p class="mt-1.5 max-w-sm text-sm text-muted">{{ $description }}</p>@endif
    @if (trim($slot))<div class="mt-5 flex flex-wrap items-center justify-center gap-2">{{ $slot }}</div>@endif
</div>
