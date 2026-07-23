@props([
    'icon',
    'label',
    'value',
    'variant' => null,
    'compact' => false,
    'plain' => false,
    'accent' => false,
])

@php
    $variantClass = $variant ? "summary-card--{$variant}" : '';
    $plainClass = $plain ? 'summary-card--plain' : '';
    $accentClass = $accent ? 'summary-card--accent' : '';
@endphp

<div {{ $attributes->merge(['class' => trim("card summary-card {$variantClass} {$plainClass} {$accentClass} h-100")]) }}>
    <div class="card-body">
        <div class="card-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></div>
        <div class="card-info">
            <div class="card-title">{{ $label }}</div>
            <div class="card-value{{ $compact ? ' is-compact' : '' }}">{{ $value }}</div>
        </div>
    </div>
</div>
