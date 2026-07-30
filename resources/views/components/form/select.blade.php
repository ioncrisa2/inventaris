@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'help' => null])

@php
    $descriptionIds = collect([
        $errors->has($name) ? $name.'-error' : null,
        $help ? $name.'-help' : null,
    ])->filter()->implode(' ');
@endphp

<div>
    @if($label)
    <label for="{{ $name }}" class="form-label">
        {{ $label }}
        @if($required)<span class="text-danger">*</span>@endif
    </label>
    @endif

    <select
        name="{{ $name }}"
        id="{{ $name }}"
        @if($required) required @endif
        @if($descriptionIds) aria-describedby="{{ $descriptionIds }}" @endif
        @if($errors->has($name)) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'form-select'])->class(['is-invalid' => $errors->has($name)]) }}
    >
        @if($placeholder)
        <option value="">{{ $placeholder }}</option>
        @endif

        @foreach($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>

    @error($name)
    <div class="invalid-feedback" id="{{ $name }}-error">{{ $message }}</div>
    @enderror

    @if($help)
    <div class="form-text" id="{{ $name }}-help">{{ $help }}</div>
    @endif
</div>
