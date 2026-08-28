@props(['action', 'filters', 'koperasis' => [], 'showKoperasi' => true, 'showModule' => true])

<form class="owner-filter" method="GET" action="{{ $action }}" aria-label="Filter analitik">
    <div class="owner-filter__fields">
        <div>
            <label class="form-label" for="ownerTanggalAwal">Tanggal awal</label>
            <input class="form-control" id="ownerTanggalAwal" type="date" name="tanggal_awal"
                value="{{ $filters['tanggal_awal'] }}" required>
        </div>
        <div>
            <label class="form-label" for="ownerTanggalAkhir">Tanggal akhir</label>
            <input class="form-control" id="ownerTanggalAkhir" type="date" name="tanggal_akhir"
                value="{{ $filters['tanggal_akhir'] }}" max="{{ now()->toDateString() }}" required>
        </div>
        @if ($showKoperasi)
            <div class="owner-filter__wide">
                <label class="form-label" for="ownerKoperasi">Koperasi</label>
                <select class="form-select" id="ownerKoperasi" name="koperasi_id">
                    <option value="">Seluruh koperasi</option>
                    @foreach ($koperasis as $option)
                        <option value="{{ $option['id'] }}" @selected((int) ($filters['koperasi_id'] ?? 0) === $option['id'])>
                            {{ $option['nama'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($showModule)
            <div>
                <label class="form-label" for="ownerModul">Modul</label>
                <select class="form-select" id="ownerModul" name="modul">
                    @foreach ([
        'semua' => 'Semua modul',
        'inventaris' => 'Inventaris',
        'kepegawaian' => 'Kepegawaian',
        'absensi' => 'Absensi',
        'penggajian' => 'Penggajian',
    ] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['modul'] ?? 'semua') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <input type="hidden" name="modul" value="semua">
        @endif
    </div>
    <button class="btn btn-primary owner-filter__submit" type="submit">
        <i class="bi bi-funnel" aria-hidden="true"></i>
        Terapkan
    </button>
</form>

@if ($errors->any())
    <div class="alert alert-danger mt-3 mb-0" role="alert">
        {{ $errors->first() }}
    </div>
@endif
