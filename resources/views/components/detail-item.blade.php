@props(['label', 'value' => null, 'emphasis' => false])

<div {{ $attributes->class(['detail-item']) }}>
    <dt>{{ $label }}</dt>
    <dd @class(['detail-item__value', 'is-emphasis' => $emphasis])>
        {{ $slot->isNotEmpty() ? $slot : ($value ?? '—') }}
    </dd>
</div>
