@props(['color' => 'bg-secondary'])

<span {{ $attributes->merge(['class' => "badge {$color}"]) }}>{{ $slot }}</span>
