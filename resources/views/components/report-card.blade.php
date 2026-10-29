@props([
    'label',
    'value',
    'subtitle' => null,
    'icon' => null,
    'accent' => false,
    'active' => false,
    'clickable' => false,
    'href' => null,
])

@php
    $tag = $href ? 'a' : ($clickable ? 'button' : 'div');
    $classes = [
        'card',
        'summary-card',
        'report-stat-card',
        'h-100',
        'text-start',
        'summary-card--plain',
        'summary-card--accent' => $accent,
        'is-active' => $active,
        'is-clickable' => $clickable || $href,
    ];
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    @if($clickable && ! $href) type="button" @endif
    {{ $attributes->class($classes) }}
>
    <div class="card-body">
        @if ($icon)
            <div class="card-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></div>
        @endif
        <div class="card-info w-100 min-w-0">
            <div class="card-title text-truncate" title="{{ $label }}">{{ $label }}</div>
            <div class="card-value tabular-nums" title="{{ $value }}">{{ $value }}</div>
            @if ($subtitle)
                <div class="card-subtitle text-truncate" title="{{ $subtitle }}">{{ $subtitle }}</div>
            @endif
        </div>
    </div>
</{{ $tag }}>

