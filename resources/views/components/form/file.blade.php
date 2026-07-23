@props(['name', 'label' => null, 'required' => false, 'help' => null, 'accept' => null, 'multiple' => false])

@php
    $errorKey = $multiple ? $name.'.*' : $name;
@endphp

{{-- File input tidak memakai old($name, $value): file tidak bisa direstore lewat old(). --}}
<div>
    @if($label)
    <label for="{{ $name }}" class="form-label">
        {{ $label }}
        @if($required)<span class="text-danger">*</span>@endif
    </label>
    @endif

    <div class="file-picker {{ $errors->has($errorKey) ? 'is-invalid' : '' }}" data-file-picker>
        <input
            type="file"
            name="{{ $multiple ? $name.'[]' : $name }}"
            id="{{ $name }}"
            @if($accept) accept="{{ $accept }}" @endif
            @if($required) required @endif
            @if($multiple) multiple @endif
            {{ $attributes->class(['file-picker__input']) }}
        >
        <label class="btn btn-light file-picker__button" for="{{ $name }}">Pilih file</label>
        <span class="file-picker__status" data-file-picker-status>Belum ada file dipilih</span>
    </div>

    @if($errors->has($errorKey))
    <div class="invalid-feedback">{{ $errors->first($errorKey) }}</div>
    @endif

    @if($help)
    <div class="form-text">{{ $help }}</div>
    @endif
</div>
