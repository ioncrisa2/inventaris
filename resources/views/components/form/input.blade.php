@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'help' => null])

@php
    $resolvedValue = old($name, $value);
    $isDate = $type === 'date';
    $dateIsoValue = '';
    $dateDisplayValue = (string) $resolvedValue;

    if ($isDate && filled($resolvedValue)) {
        foreach (['Y-m-d' => 'd/m/Y', 'd/m/Y' => 'd/m/Y'] as $sourceFormat => $displayFormat) {
            $parsedDate = \DateTimeImmutable::createFromFormat('!'.$sourceFormat, (string) $resolvedValue);
            $parseErrors = \DateTimeImmutable::getLastErrors();

            if ($parsedDate && $parseErrors === false && $parsedDate->format($sourceFormat) === (string) $resolvedValue) {
                $dateIsoValue = $parsedDate->format('Y-m-d');
                $dateDisplayValue = $parsedDate->format($displayFormat);
                break;
            }
        }
    }
@endphp

<div>
    @if($label)
    <label for="{{ $name }}" class="form-label">
        {{ $label }}
        @if($required)<span class="text-danger">*</span>@endif
    </label>
    @endif

    @if($isDate)
    <div class="input-group localized-date" data-local-date-group>
        <input
            type="text"
            id="{{ $name }}"
            value="{{ $dateDisplayValue }}"
            placeholder="dd/mm/yyyy"
            inputmode="numeric"
            autocomplete="off"
            lang="id-ID"
            data-local-date
            @if($required) required @endif
            {{ $attributes->except(['min', 'max'])->merge(['class' => 'form-control'])->class(['is-invalid' => $errors->has($name)]) }}
        >
        <button
            type="button"
            class="btn btn-outline-secondary localized-date__button"
            data-local-date-button
            aria-label="Pilih {{ strtolower($label ?? 'tanggal') }}">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
        </button>
        <input type="hidden" name="{{ $name }}" value="{{ $dateIsoValue }}" data-local-date-value>
        <input
            type="date"
            class="localized-date__picker"
            value="{{ $dateIsoValue }}"
            @if($attributes->get('min')) min="{{ $attributes->get('min') }}" @endif
            @if($attributes->get('max')) max="{{ $attributes->get('max') }}" @endif
            tabindex="-1"
            aria-label="Pemilih {{ strtolower($label ?? 'tanggal') }}"
            data-local-date-picker>
    </div>
    @else
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ $resolvedValue }}"
        @if($required) required @endif
        {{ $attributes->merge(['class' => 'form-control'])->class(['is-invalid' => $errors->has($name)]) }}
    >
    @endif

    @error($name)
    <div class="invalid-feedback {{ $isDate ? 'd-block' : '' }}">{{ $message }}</div>
    @enderror

    @if($help)
    <div class="form-text">{{ $help }}</div>
    @endif
</div>
