@props([
    'name',
    'label' => null,
    'options' => [],          // [value => label]
    'value' => null,
    'placeholder' => null,
    'searchable' => false,    // Tom Select
    'help' => null,
    'required' => false,
    'icon' => null,
])
@php
    $id = $attributes->get('id', $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($errorKey);
    $selected = old($errorKey, $value);
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }} @if ($required)<span class="text-danger">*</span>@endif
        </label>
    @endif

    <div class="relative">
        @if ($icon && ! $searchable)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted">
                <x-icon :name="$icon" size="w-[18px] h-[18px]" />
            </span>
        @endif
        <select id="{{ $id }}" name="{{ $name }}"
            @if ($searchable) x-data x-tomselect @endif
            {{ $attributes->except(['class', 'id'])->class(['form-control pr-9', 'pl-10' => $icon && ! $searchable, 'is-invalid' => $hasError]) }}
            @if ($required) required @endif>
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
            {{ $slot }}
        </select>
    </div>

    @if ($hasError)
        <p class="form-error">{{ $errors->first($errorKey) }}</p>
    @elseif ($help)
        <p class="form-help">{{ $help }}</p>
    @endif
</div>
