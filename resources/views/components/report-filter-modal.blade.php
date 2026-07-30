@props([
    'id',
    'title',
    'action',
    'resetUrl',
    'hasFilters' => false,
    'hasErrors' => false,
])

<div
    {{ $attributes->class(['modal fade report-filter-modal']) }}
    id="{{ $id }}"
    tabindex="-1"
    aria-labelledby="{{ $id }}Label"
    aria-describedby="{{ $id }}Description"
    aria-hidden="true"
    data-report-filter-modal
    data-has-errors="{{ $hasErrors ? 'true' : 'false' }}"
>
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" method="GET" action="{{ $action }}">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5" id="{{ $id }}Label">{{ $title }}</h2>
                    <p class="report-filter-modal__description" id="{{ $id }}Description">
                        Atur cakupan data yang ditampilkan, dicetak, dan diekspor.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                @if($hasErrors)
                    <div class="alert alert-danger d-flex gap-2" role="alert">
                        <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                        <div>
                            <strong>Filter belum dapat diterapkan.</strong>
                            <div>Periksa kembali isian yang ditandai.</div>
                        </div>
                    </div>
                @endif

                <div class="row g-3">
                    {{ $slot }}
                </div>
            </div>

            <div class="modal-footer report-filter-modal__footer">
                <div>
                    @if($hasFilters)
                        <a class="btn btn-light" href="{{ $resetUrl }}">
                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                            Reset
                        </a>
                    @endif
                </div>

                <div class="d-flex flex-wrap justify-content-end gap-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2" aria-hidden="true"></i>
                        Terapkan Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
