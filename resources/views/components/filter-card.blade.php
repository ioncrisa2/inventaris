@props(['title' => 'Filter Laporan'])

<div {{ $attributes->class(['card filter-card mb-4 d-print-none']) }}>
    <div class="card-header filter-card__header">
        <i class="bi bi-sliders" aria-hidden="true"></i>
        <span>{{ $title }}</span>
    </div>
    <div class="card-body">
        {{ $slot }}
    </div>
</div>
