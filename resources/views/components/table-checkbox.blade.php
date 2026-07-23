@props([
    'group',
    'value' => null,
    'label',
    'selectAll' => false,
])

<input
    type="checkbox"
    {{ $attributes->class(['form-check-input']) }}
    @if($selectAll)
        data-bulk-select-all="{{ $group }}"
    @else
        data-bulk-select="{{ $group }}"
        value="{{ $value }}"
    @endif
    aria-label="{{ $label }}">
