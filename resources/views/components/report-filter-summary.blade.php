@props(['items'])

<div {{ $attributes->class(['report-filter-summary d-print-none']) }} aria-label="Konteks laporan aktif">
    <div class="report-filter-summary__label">
        <i class="bi bi-funnel" aria-hidden="true"></i>
        <span>Konteks laporan</span>
    </div>

    <div class="report-filter-summary__items">
        @foreach($items as $item)
            <span class="report-filter-summary__item">
                <span>{{ $item['label'] }}:</span>
                <strong>{{ $item['value'] }}</strong>
            </span>
        @endforeach
    </div>
</div>
