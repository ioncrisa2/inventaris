@props([
    'icon' => 'bi-inbox',
    'title' => 'Belum ada data',
])

<div {{ $attributes->class(['empty-state']) }}>
    <span class="empty-state__icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
    <div>
        <strong>{{ $title }}</strong>
        @if($slot->isNotEmpty())
            <p>{{ $slot }}</p>
        @endif
    </div>
</div>
