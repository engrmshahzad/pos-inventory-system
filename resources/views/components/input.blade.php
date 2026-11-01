@props([
    'name',
    'label' => null,
    'type' => 'text',
    'icon' => null,
    'help' => null,
    'value' => null,
    'required' => false,
    'errorBag' => 'default',
])
@php
    $id = $attributes->get('id', $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->getBag($errorBag)->has($errorKey);
    $isPassword = $type === 'password';
@endphp

<div {{ $attributes->only('class')->merge(['class' => '']) }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }} @if ($required)<span class="text-danger">*</span>@endif
        </label>
    @endif

    <div class="relative" @if ($isPassword) x-data="{ show: false }" @endif>
        @if ($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted">
                <x-icon :name="$icon" size="w-[18px] h-[18px]" />
            </span>
        @endif

        @if ($type === 'textarea')
            <textarea id="{{ $id }}" name="{{ $name }}" rows="3"
                {{ $attributes->except(['class', 'id'])->class(['form-control', 'pl-10' => $icon, 'is-invalid' => $hasError]) }}
                @if ($required) required @endif
                @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>{{ old($errorKey, $value) }}</textarea>
        @else
            <input id="{{ $id }}" name="{{ $name }}"
                @if ($isPassword) :type="show ? 'text' : 'password'" type="password" @else type="{{ $type }}" @endif
                @if ($type !== 'file' && ! $isPassword) value="{{ old($errorKey, $value) }}" @endif
                {{ $attributes->except(['class', 'id'])->class(['form-control', 'pl-10' => $icon, 'pr-11' => $isPassword, 'is-invalid' => $hasError]) }}
                @if ($required) required @endif
                @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
        @endif

        @if ($isPassword)
            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-muted hover:text-heading"
                :aria-label="show ? 'Hide password' : 'Show password'">
                <x-icon name="eye" size="w-[18px] h-[18px]" x-show="!show" />
                <x-icon name="eye-off" size="w-[18px] h-[18px]" x-show="show" x-cloak />
            </button>
        @endif
    </div>

    @if ($hasError)
        <p id="{{ $id }}-error" class="form-error">{{ $errors->getBag($errorBag)->first($errorKey) }}</p>
    @elseif ($help)
        <p class="form-help">{{ $help }}</p>
    @endif
</div>
