@props([
    'action',
    'resetRoute',
    'hasFilters' => false,
    'submitLabel' => 'Filter',
    'submitIcon' => 'bi-funnel',
])

<form
    action="{{ $action }}"
    method="GET"
    data-filter-form
    {{ $attributes->class(['row g-2 align-items-end filter-toolbar']) }}>
    {{ $slot }}

    <button class="visually-hidden" type="submit">Terapkan filter</button>

    @if($hasFilters)
        <div class="col-12 filter-toolbar__active">
            <div class="active-filter-list" data-active-filter-list aria-label="Filter aktif"></div>
            <a class="btn btn-sm btn-light" href="{{ $resetRoute }}">Reset semua</a>
        </div>
    @endif
</form>
