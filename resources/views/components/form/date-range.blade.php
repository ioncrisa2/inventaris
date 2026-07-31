@props([
    'nameAwal',
    'nameAkhir',
    'label' => null,
    'valueAwal' => null,
    'valueAkhir' => null,
    'required' => false,
    'help' => null,
    'id' => null,
])

@php
    $id ??= $nameAwal;
    $resolvedAwal = old($nameAwal, $valueAwal);
    $resolvedAkhir = old($nameAkhir, $valueAkhir);
    $hasError = $errors->has($nameAwal) || $errors->has($nameAkhir);
    $displayAwal = filled($resolvedAwal) ? \Illuminate\Support\Carbon::parse($resolvedAwal)->format('d/m/Y') : '';
    $displayAkhir = filled($resolvedAkhir) ? \Illuminate\Support\Carbon::parse($resolvedAkhir)->format('d/m/Y') : '';
    $display = $displayAwal && $displayAkhir ? "{$displayAwal} s.d. {$displayAkhir}" : ($displayAwal ?: '');
@endphp

<div>
    @if($label)
    <label for="{{ $id }}" class="form-label">
        {{ $label }}
        @if($required)<span class="text-danger">*</span>@endif
    </label>
    @endif

    <div class="input-group" data-date-range-group>
        <input
            type="text"
            id="{{ $id }}"
            value="{{ $display }}"
            placeholder="dd/mm/yyyy s.d. dd/mm/yyyy"
            autocomplete="off"
            readonly
            data-date-range
            {{ $attributes->merge(['class' => 'form-control'])->class(['is-invalid' => $hasError]) }}
        >
        <span class="input-group-text"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
        <input type="hidden" name="{{ $nameAwal }}" value="{{ $resolvedAwal }}" data-date-range-awal>
        <input type="hidden" name="{{ $nameAkhir }}" value="{{ $resolvedAkhir }}" data-date-range-akhir>
    </div>

    @error($nameAwal)
    <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error($nameAkhir)
    <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror

    @if($help)
    <div class="form-text">{{ $help }}</div>
    @endif
</div>
