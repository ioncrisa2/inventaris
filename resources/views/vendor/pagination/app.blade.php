@php
    $perPageId = 'per_page_'.spl_object_id($paginator);
    $preservedQuery = request()->except(['page', 'per_page']);
@endphp

<div class="pagination-footer__layout">
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

    <div class="pagination-footer__navigation">
        <div class="pagination-footer__summary text-body-secondary">
            @if($paginator->hasPages())
                Menampilkan
                <span class="fw-semibold">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                dari <span class="fw-semibold">{{ $paginator->total() }}</span> hasil
            @else
                Menampilkan <span class="fw-semibold">{{ $paginator->total() }}</span> hasil
            @endif
        </div>

        @if ($paginator->hasPages())
            <nav class="pagination-footer__desktop" aria-label="Navigasi halaman">
                <ul class="pagination pagination-sm mb-0">
                    @if ($paginator->onFirstPage())
                        <li class="page-item disabled" aria-disabled="true" aria-label="Halaman sebelumnya">
                            <span class="page-link" aria-hidden="true">&lsaquo;</span>
                        </li>
                    @else
                        <li class="page-item">
                            <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">&lsaquo;</a>
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
                                    <li class="page-item"><a class="page-link" href="{{ $url }}" aria-label="Halaman {{ $page }}">{{ $page }}</a></li>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <li class="page-item">
                            <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">&rsaquo;</a>
                        </li>
                    @else
                        <li class="page-item disabled" aria-disabled="true" aria-label="Halaman berikutnya">
                            <span class="page-link" aria-hidden="true">&rsaquo;</span>
                        </li>
                    @endif
                </ul>
            </nav>

            <nav class="pagination-footer__mobile" aria-label="Navigasi halaman mobile">
                @if ($paginator->onFirstPage())
                    <span class="btn btn-sm btn-light disabled" aria-disabled="true">
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                        Sebelumnya
                    </span>
                @else
                    <a class="btn btn-sm btn-light" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                        Sebelumnya
                    </a>
                @endif

                <span class="pagination-footer__page" aria-current="page">
                    {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
                </span>

                @if ($paginator->hasMorePages())
                    <a class="btn btn-sm btn-light" href="{{ $paginator->nextPageUrl() }}" rel="next">
                        Berikutnya
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </a>
                @else
                    <span class="btn btn-sm btn-light disabled" aria-disabled="true">
                        Berikutnya
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </span>
                @endif
            </nav>
        @endif
    </div>
</div>
