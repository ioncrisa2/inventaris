@props([
    'src' => null,
    'alt',
    'icon' => 'bi-image',
    'size' => 'cover',
])

<div {{ $attributes->class(['image-preview', "image-preview--{$size}"]) }}>
    @if ($src)
        <img src="{{ $src }}" alt="{{ $alt }}" onerror="this.onerror=null;this.src='{{ asset('assets/img/no-image.svg') }}';">
    @else
        <img src="{{ asset('assets/img/no-image.svg') }}" alt="{{ $alt }} (No Image)" class="image-preview__no-image">
    @endif
</div>
