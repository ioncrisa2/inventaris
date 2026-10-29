@props([
    'title',
    'subtitle' => null,
    'excelUrl' => null,
    'printUrl' => null,
    'pdfUrl' => null,
    'searchPlaceholder' => 'Cari data...',
    'hasActiveFilters' => false,
    'resetUrl' => null,
    'paginator' => null,
    'unit' => 'data',
    'searchName' => 'search',
])

@php
    $currentQuery = request()->query();
    $searchValue = request($searchName);
@endphp

<x-app-page>
    {{-- 1. Header: Judul Laporan + Tombol Export ▾ --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 report-page-header">
        <div>
            <h1 class="h3 fw-bold text-body-emphasis mb-0">{{ $title }}</h1>
            @if ($subtitle)
                <p class="text-body-secondary small mb-0 mt-1">{{ $subtitle }}</p>
            @endif
        </div>

        <div class="d-flex align-items-center gap-2 d-print-none">
            @isset($headerActions)
                {{ $headerActions }}
            @endisset

            <div class="dropdown">
                <button
                    class="btn btn-outline-primary dropdown-toggle d-inline-flex align-items-center gap-2"
                    type="button"
                    id="exportDropdownMenuButton"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    <i class="bi bi-box-arrow-up-right"></i>
                    <span>Export</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="exportDropdownMenuButton">
                    @if ($excelUrl)
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2" href="{{ $excelUrl }}">
                                <i class="bi bi-file-earmark-excel text-success"></i>
                                <span>Export Excel</span>
                            </a>
                        </li>
                    @endif
                    @if ($printUrl)
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2" href="{{ $printUrl }}" target="_blank" rel="noopener">
                                <i class="bi bi-printer text-primary"></i>
                                <span>Cetak</span>
                            </a>
                        </li>
                    @endif
                    <li>
                        @if ($pdfUrl)
                            <a class="dropdown-item d-flex align-items-center gap-2" href="{{ $pdfUrl }}" target="_blank" rel="noopener">
                                <i class="bi bi-file-earmark-pdf text-danger"></i>
                                <span>Export PDF</span>
                            </a>
                        @else
                            <button class="dropdown-item d-flex align-items-center justify-content-between text-body-secondary disabled" type="button" disabled title="TODO: Ekspor PDF akan segera hadir">
                                <span class="d-flex align-items-center gap-2">
                                    <i class="bi bi-file-earmark-pdf text-danger opacity-50"></i>
                                    <span>Export PDF</span>
                                </span>
                                <span class="badge bg-secondary-subtle text-secondary small">Segera</span>
                            </button>
                        @endif
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- 2. Filter Bar: Dropdown Langsung + Pencarian (Tersimpan di URL) --}}
    <form method="GET" action="{{ url()->current() }}" class="report-filter-bar d-print-none" data-report-filter-form>
        {{-- Hidden fields to preserve sort, direction, mode, etc. --}}
        @foreach(request()->only(['sort', 'direction', 'mode', 'per_page']) as $k => $v)
            @if(filled($v))
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endif
        @endforeach

        @isset($filters)
            {{ $filters }}
        @endisset

        {{-- Search Input --}}
        <div class="report-filter-bar__search">
            <div class="input-group">
                <span class="input-group-text bg-body-tertiary border-end-0 text-body-secondary">
                    <i class="bi bi-search"></i>
                </span>
                <input
                    type="search"
                    name="{{ $searchName }}"
                    value="{{ $searchValue }}"
                    class="form-control border-start-0 ps-0"
                    placeholder="{{ $searchPlaceholder }}"
                    aria-label="{{ $searchPlaceholder }}"
                >
                @if (filled($searchValue))
                    @php
                        $clearQuery = request()->except([$searchName, 'page']);
                        $clearUrl = url()->current() . ($clearQuery ? '?' . http_build_query($clearQuery) : '');
                    @endphp
                    <a href="{{ $clearUrl }}" class="btn btn-outline-secondary border-start-0" title="Hapus pencarian">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
                <button type="submit" class="btn btn-primary">
                    Cari
                </button>
            </div>
        </div>

        @if ($hasActiveFilters)
            <div class="report-filter-bar__reset">
                <a href="{{ $resetUrl ?? url()->current() }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1" title="Reset semua filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Reset</span>
                </a>
            </div>
        @endif
    </form>

    {{-- 3. Kartu Ringkasan (1 baris responsif, desktop flex/grid, mobile 2 kolom) --}}
    @isset($cards)
        <div class="report-summary-grid report-stat-grid">
            {{ $cards }}
        </div>
    @endisset

    {{-- 4. Panel Rekap (Opsional, berdampingan 2-3 kolom, stack di mobile) --}}
    @isset($recap)
        <div class="report-recap-grid">
            {{ $recap }}
        </div>
    @endisset

    {{-- 5. Tabel Detail (Lebar penuh, sticky header, sticky tfoot) --}}
    <div class="report-table-card shadow-sm">
        @isset($tableHeader)
            <div class="card-header bg-body-tertiary border-bottom py-2 px-3">
                {{ $tableHeader }}
            </div>
        @endisset

        <div class="report-table-wrapper">
            {{ $slot }}
        </div>

        @if ($paginator)
            <x-report-pagination :paginator="$paginator" :unit="$unit" />
        @endif
    </div>
</x-app-page>
