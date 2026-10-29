@props([
    'paginator',
    'unit' => 'data',
    'options' => [10, 25, 50],
])

@php
    $perPageId = 'per_page_'.spl_object_id($paginator);
    $preservedQuery = request()->except(['page', 'per_page']);
@endphp

@if ($paginator->total() > 0)
<div {{ $attributes->merge(['class' => 'card-footer bg-body-tertiary border-top d-print-none py-2 px-3']) }}>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <form method="GET" action="{{ request()->url() }}" class="d-flex align-items-center gap-2">
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
                class="form-select form-select-sm"
                style="width: auto; min-width: 70px;"
                aria-label="Jumlah {{ $unit }} per halaman"
                onchange="this.form.submit()"
            >
                @foreach($options as $option)
                    <option value="{{ $option }}" @selected($paginator->perPage() === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <span class="text-body-secondary small text-nowrap">{{ $unit }} / hal</span>
        </form>

        <div class="d-flex flex-wrap align-items-center gap-3 ms-auto">
            <div class="text-body-secondary small text-nowrap">
                @if($paginator->hasPages())
                    Menampilkan <span class="fw-semibold">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span> dari <span class="fw-semibold">{{ $paginator->total() }}</span> {{ $unit }}
                @else
                    Menampilkan <span class="fw-semibold">{{ $paginator->total() }}</span> {{ $unit }}
                @endif
            </div>

            @if ($paginator->hasPages())
                <nav aria-label="Navigasi halaman {{ $unit }}">
                    {{ $paginator->appends(request()->query())->onEachSide(1)->links('vendor.pagination.report') }}
                </nav>
            @endif
        </div>
    </div>
</div>
@endif
