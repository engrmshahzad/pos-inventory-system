@props(['name', 'label' => null, 'description' => null, 'checked' => false])
@php $isOn = (bool) old($name, $checked); @endphp

<label {{ $attributes->only('class')->merge(['class' => 'flex cursor-pointer items-start justify-between gap-4']) }}>
    @if ($label)
        <span>
            <span class="block text-sm font-medium text-heading">{{ $label }}</span>
            @if ($description)<span class="block text-xs text-muted">{{ $description }}</span>@endif
        </span>
    @endif
    <span class="relative inline-flex shrink-0">
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox" name="{{ $name }}" value="1" class="peer sr-only" @checked($isOn) {{ $attributes->except('class') }}>
        <span class="h-6 w-11 rounded-full bg-line transition peer-checked:bg-primary peer-focus-visible:ring-[3px] peer-focus-visible:ring-primary/30"></span>
        <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
    </span>
</label>
