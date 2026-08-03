@props(['action', 'resetRoute', 'hasFilters' => false, 'submitLabel' => 'Filter', 'submitIcon' => 'bi-funnel'])

<form action="{{ $action }}" method="GET" data-filter-form
    {{ $attributes->class(['row g-2 align-items-end filter-toolbar']) }}>
    @if(request()->filled('per_page'))
        <input type="hidden" name="per_page" value="{{ request('per_page') }}">
    @endif

    {{ $slot }}

    <div class="col-12 col-sm-auto filter-toolbar__actions">
        <button class="btn btn-primary filter-toolbar__submit" type="submit" data-filter-submit>
            <i class="bi {{ $submitIcon }}" aria-hidden="true"></i>
            {{ $submitLabel }}
        </button>
    </div>

    @if ($hasFilters)
        <div class="col-12 filter-toolbar__active">
            <div class="active-filter-list" data-active-filter-list aria-label="Filter aktif"></div>
            <a class="btn btn-sm btn-light" href="{{ $resetRoute }}">Reset semua</a>
        </div>
    @endif

    <span class="visually-hidden" data-filter-status aria-live="polite"></span>
</form>
