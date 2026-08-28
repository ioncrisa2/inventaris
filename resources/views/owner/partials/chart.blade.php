@props(['id', 'title', 'subtitle' => null, 'chart' => null, 'type' => 'line'])

<section class="owner-panel owner-chart-panel" aria-labelledby="{{ $id }}Title">
    <header class="owner-panel__header">
        <div>
            <h2 id="{{ $id }}Title">{{ $title }}</h2>
            @if ($subtitle)
                <p>{{ $subtitle }}</p>
            @endif
        </div>
    </header>
    <div class="owner-panel__body">
        @if (!empty($chart['labels']) && !empty($chart['series']))
            <div class="owner-chart-wrap">
                <canvas id="{{ $id }}" data-owner-chart data-chart-type="{{ $type }}"
                    data-labels='@json($chart['labels'])' data-series='@json($chart['series'])' role="img"
                    aria-label="{{ $title }}">
                </canvas>
            </div>
        @else
            <div class="owner-empty-state">
                <i class="bi bi-bar-chart" aria-hidden="true"></i>
                <p>Belum ada data pada periode ini.</p>
            </div>
        @endif
    </div>
</section>
