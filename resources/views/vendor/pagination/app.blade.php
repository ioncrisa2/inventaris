@php
    $perPageId = 'per_page_'.spl_object_id($paginator);
    $preservedQuery = request()->except(['page', 'per_page']);
@endphp

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-2">
    <form method="GET" action="{{ request()->url() }}" class="pagination-per-page d-flex align-items-center gap-2">
        @foreach($preservedQuery as $key => $value)
            @if(is_array($value))
                @foreach($value as $item)
                    <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach

        <label for="{{ $perPageId }}" class="form-label text-body-secondary small mb-0 text-nowrap">Tampilkan</label>
        <select
            name="per_page"
            id="{{ $perPageId }}"
            class="form-select form-select-sm pagination-per-page__select"
            aria-label="Jumlah data per halaman"
            onchange="this.form.submit()"
        >
            @foreach(\App\Support\PerPage::OPTIONS as $option)
                <option value="{{ $option }}" @selected($paginator->perPage() === $option)>{{ $option }}</option>
            @endforeach
        </select>
        <span class="text-body-secondary small text-nowrap">data / halaman</span>
    </form>

    @if ($paginator->hasPages())
        <nav class="d-flex flex-wrap align-items-center gap-2" aria-label="@lang('Pagination Navigation')">
            <div class="small text-body-secondary text-nowrap">
                {!! __('Showing') !!}
                <span class="fw-semibold">{{ $paginator->firstItem() }}</span>
                {!! __('to') !!}
                <span class="fw-semibold">{{ $paginator->lastItem() }}</span>
                {!! __('of') !!}
                <span class="fw-semibold">{{ $paginator->total() }}</span>
                {!! __('results') !!}
            </div>

            <ul class="pagination pagination-sm mb-0">
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                        <span class="page-link" aria-hidden="true">&lsaquo;</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">&lsaquo;</a>
                    </li>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                            @else
                                <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">&rsaquo;</a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
                        <span class="page-link" aria-hidden="true">&rsaquo;</span>
                    </li>
                @endif
            </ul>
        </nav>
    @else
        <div class="small text-body-secondary text-nowrap">
            {!! __('Showing') !!}
            <span class="fw-semibold">{{ $paginator->total() }}</span>
            {!! __('of') !!}
            <span class="fw-semibold">{{ $paginator->total() }}</span>
            {!! __('results') !!}
        </div>
    @endif
</div>
