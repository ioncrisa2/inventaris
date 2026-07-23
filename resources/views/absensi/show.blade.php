@extends('layouts.app')

@section('title', 'Absensi ' . $karyawan->nama_lengkap . ' - Sistem Inventaris & Kepegawaian')

@php
$bulanSebelumnya = $bulan === 1 ? 12 : $bulan - 1;
$tahunSebelumnya = $bulan === 1 ? $tahun - 1 : $tahun;
$bulanBerikutnya = $bulan === 12 ? 1 : $bulan + 1;
$tahunBerikutnya = $bulan === 12 ? $tahun + 1 : $tahun;

$namaHariSingkat = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
@endphp

@section('content')
<x-app-page>
        <x-page-header subtitle="{{ $karyawan->nik }} · {{ $karyawan->jabatan }} · {{ $karyawan->unitKerja?->nama_unit ?? 'Belum ditentukan' }}">
            <x-slot:title>
                {{ $karyawan->nama_lengkap }}
                <x-badge class="align-middle" :color="\App\Models\Karyawan::STATUS_COLORS[$karyawan->status_karyawan] ?? 'bg-secondary'">{{ $karyawan->status_karyawan }}</x-badge>
            </x-slot:title>
            <x-slot:actions>
                <a href="{{ route('absensi.index') }}" class="btn btn-light">
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        @if($errors->has('absensi'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('absensi') }}</div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-2">
                <x-stat-card icon="bi-person-check" label="Hadir" :value="$totalHadir" variant="success" />
            </div>
            <div class="col-sm-6 col-xl-2">
                <x-stat-card icon="bi-envelope-check" label="Izin" :value="$totalIzin" variant="warning" />
            </div>
            <div class="col-sm-6 col-xl-2">
                <x-stat-card icon="bi-heart-pulse" label="Sakit" :value="$totalSakit" variant="info" />
            </div>
            <div class="col-sm-6 col-xl-2">
                <x-stat-card icon="bi-calendar2-week" label="Cuti" :value="$totalCuti" />
            </div>
            <div class="col-sm-6 col-xl-2">
                <x-stat-card icon="bi-geo-alt" label="Dinas Luar Kota" :value="$totalDinasLuarKota" variant="secondary" compact />
            </div>
            <div class="col-sm-6 col-xl-2">
                <x-stat-card icon="bi-person-x" label="Alpha" :value="$totalAlpha" variant="danger" />
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <a
                    class="btn btn-sm btn-outline-secondary"
                    href="{{ route('absensi.show', ['karyawan' => $karyawan, 'bulan' => $bulanSebelumnya, 'tahun' => $tahunSebelumnya]) }}">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <h2 class="mb-0 h5">{{ $namaBulan }} {{ $tahun }}</h2>
                <a
                    class="btn btn-sm btn-outline-secondary"
                    href="{{ route('absensi.show', ['karyawan' => $karyawan, 'bulan' => $bulanBerikutnya, 'tahun' => $tahunBerikutnya]) }}">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>

            <div class="card-body">
                <div class="calendar-legend" aria-label="Keterangan kalender">
                    <span><i class="calendar-legend__swatch is-workday" aria-hidden="true"></i> Hari kerja</span>
                    <span><i class="calendar-legend__swatch is-holiday" aria-hidden="true"></i> Hari libur (Minggu)</span>
                    <span><i class="calendar-legend__swatch is-outside" aria-hidden="true"></i> Di luar bulan</span>
                </div>

                <div class="calendar-grid mb-2">
                    @foreach($namaHariSingkat as $namaHari)
                    <div class="calendar-day-header {{ $loop->last ? 'is-holiday' : '' }}">{{ $namaHari }}</div>
                    @endforeach
                </div>

                <div class="calendar-grid">
                    @foreach($mingguKalender as $minggu)
                    @foreach($minggu as $cell)
                    @php
                    $bisaDiklik = ! $cell['di_luar_bulan'] && ! $cell['masa_depan'];
                    $kondisiKelas = collect([
                    'calendar-cell',
                    $cell['di_luar_bulan'] ? 'is-outside' : null,
                    $cell['hari_minggu'] ? 'is-sunday' : null,
                    $cell['masa_depan'] ? 'is-future' : null,
                    $cell['tanggal']->isToday() ? 'is-today' : null,
                    ])->filter()->implode(' ');
                    $absensiHariIni = $cell['absensi'];
                    $badgeStatus = $absensiHariIni ? (\App\Models\Absensi::STATUS_COLORS[$absensiHariIni->status] ?? 'bg-secondary') : null;
                    @endphp
                    <div class="{{ $kondisiKelas }}">
                        @if($bisaDiklik)
                        <button
                            type="button"
                            class="calendar-cell-button"
                            data-bs-toggle="modal"
                            data-bs-target="#modalAbsensi"
                            data-tanggal="{{ $cell['tanggal']->format('Y-m-d') }}"
                            data-tanggal-label="{{ $cell['tanggal']->translatedFormat('l, d F Y') }}"
                            data-status="{{ $absensiHariIni->status ?? '' }}"
                            data-catatan="{{ $absensiHariIni->catatan ?? '' }}"
                            data-hari-minggu="{{ $cell['hari_minggu'] ? '1' : '0' }}"
                            aria-label="Isi absensi tanggal {{ $cell['tanggal']->translatedFormat('d F Y') }}">
                            <span class="calendar-cell__top">
                                <span class="calendar-cell-date">{{ $cell['tanggal']->day }}</span>
                                @if($cell['hari_minggu'])
                                    <span class="calendar-cell-holiday">Libur</span>
                                @endif
                            </span>
                            @if($badgeStatus)
                            <x-badge :color="$badgeStatus" :title="$absensiHariIni->status">{{ \App\Models\Absensi::CALENDAR_LABELS[$absensiHariIni->status] ?? $absensiHariIni->status }}</x-badge>
                            @endif
                        </button>
                        @else
                        <span class="calendar-cell__top">
                            <span class="calendar-cell-date">{{ $cell['tanggal']->day }}</span>
                            @if($cell['hari_minggu'] && ! $cell['di_luar_bulan'])
                                <span class="calendar-cell-holiday">Libur</span>
                            @endif
                        </span>
                        @if($badgeStatus)
                        <span class="badge {{ $badgeStatus }}" title="{{ $absensiHariIni->status }}">{{ \App\Models\Absensi::CALENDAR_LABELS[$absensiHariIni->status] ?? $absensiHariIni->status }}</span>
                        @endif
                        @endif
                    </div>
                    @endforeach
                    @endforeach
                </div>
            </div>
        </div>

</x-app-page>

<x-modal-form
    id="modalAbsensi"
    :data-auto-show-modal="$errors->any()"
    :data-sunday-allowed-statuses="json_encode(\App\Models\Absensi::SUNDAY_ALLOWED_STATUSES)"
    dialog-class="modal-dialog-centered"
    :action="route('absensi.store', $karyawan)"
    submit-label="Simpan Absensi"
    submit-variant="success"
>
    <x-slot:header>
        <div>
            <h2 class="modal-title fs-5" id="modalAbsensiLabel">Isi Absensi</h2>
            <div class="text-muted small" id="modalAbsensiTanggalLabel">{{ old('tanggal') ? \Illuminate\Support\Carbon::parse(old('tanggal'))->translatedFormat('l, d F Y') : '' }}</div>
        </div>
    </x-slot:header>

    <input type="hidden" name="tanggal" value="{{ old('tanggal') }}">

    <div class="mb-3">
        <x-form.select
            name="status"
            label="Status"
            :options="collect(\App\Models\Absensi::STATUSES)->mapWithKeys(fn ($status) => [$status => $status])"
            required
            help="Hari Minggu hanya dapat diisi Izin, Sakit, atau Dinas Luar Kota."
        />
    </div>

    <div>
        <label class="form-label" for="catatan">Catatan</label>
        <textarea
            class="form-control @error('catatan') is-invalid @enderror"
            id="catatan"
            name="catatan"
            rows="3"
            placeholder="Opsional">{{ old('catatan') }}</textarea>
        @error('catatan') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</x-modal-form>
@endsection
