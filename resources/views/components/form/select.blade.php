@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'help' => null, 'id' => null])

@php
    $id ??= $name;
    $descriptionIds = collect([
        $errors->has($name) ? $id.'-error' : null,
        $help ? $id.'-help' : null,
    ])->filter()->implode(' ');
@endphp

<div>
    @if($label)
    <label for="{{ $id }}" class="form-label">
        {{ $label }}
        @if($required)<span class="text-danger">*</span>@endif
    </label>
    @endif

    <select
        name="{{ $name }}"
        id="{{ $id }}"
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
    <div class="invalid-feedback" id="{{ $id }}-error">{{ $message }}</div>
    @enderror

    @if($help)
    <div class="form-text" id="{{ $id }}-help">{{ $help }}</div>
    @endif
</div>
