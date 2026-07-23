@props(['name', 'label' => null, 'value' => null, 'required' => false, 'prefix' => 'Rp'])

@php
    $moneyValue = old($name, $value);
    $normalizedMoneyValue = is_numeric($moneyValue)
        ? $moneyValue
        : preg_replace('/\D+/', '', (string) $moneyValue);
    $displayValue = filled($normalizedMoneyValue)
        ? number_format((float) $normalizedMoneyValue, 0, ',', '.')
        : '';
@endphp

<div>
    @if($label)
    <label for="{{ $name }}" class="form-label">
        {{ $label }}
        @if($required)<span class="text-danger">*</span>@endif
    </label>
    @endif

    <div class="input-group">
        <span class="input-group-text">{{ $prefix }}</span>
        <input
            type="text"
            name="{{ $name }}"
            id="{{ $name }}"
            value="{{ $displayValue }}"
            inputmode="numeric"
            autocomplete="off"
            data-money-input
            @if($required) required @endif
            {{ $attributes->merge(['class' => 'form-control'])->class(['is-invalid' => $errors->has($name)]) }}
        >
        @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
