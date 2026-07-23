@props([
    'src' => null,
    'alt',
    'icon' => 'bi-image',
    'size' => 'cover',
])

<div {{ $attributes->class(['image-preview', "image-preview--{$size}"]) }}>
    @if($src)
        <img src="{{ $src }}" alt="{{ $alt }}">
    @else
        <div class="image-preview__empty" role="img" aria-label="{{ $alt }} belum tersedia">
            <i class="bi {{ $icon }}" aria-hidden="true"></i>
        </div>
    @endif
</div>
