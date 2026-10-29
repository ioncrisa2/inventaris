@extends('layouts.app')

@section('title', 'Laporan Absensi - Sistem Inventaris & Kepegawaian')

@section('content')
@php
    $showTenant = auth()->user()->isSuperAdmin();
    $currentDate = \Carbon\CarbonImmutable::create($tahun, $bulan, 1);
    $prevDate = $currentDate->subMonthNoOverflow();
    $endOfMonth = $currentDate->endOfMonth();
    $namaBulan = $currentDate->translatedFormat('F');

    $isPenggajian = ($tipePeriode === 'penggajian');
    if ($isPenggajian) {
        $tglAwal = $prevDate->setDay(25)->translatedFormat('d M');
        $tglAkhir = $currentDate->setDay(24)->translatedFormat('d M Y');
        $periodSubtitle = "Periode Penggajian: {$tglAwal} – {$tglAkhir} (Cut-off 25–24)";
    } else {
        $tglAwal = $currentDate->setDay(1)->translatedFormat('d M');
        $tglAkhir = $endOfMonth->translatedFormat('d M Y');
        $periodSubtitle = "Bulan {$namaBulan} {$tahun} ({$tglAwal} – {$tglAkhir})";
    }

    $hasActiveFilters = request()->filled('unit_kerja_id')
        || (int) $bulan !== now()->month
        || (int) $tahun !== now()->year
        || request()->filled('status')
        || request()->filled('tipe_periode')
        || request()->filled('search')
        || (request()->filled('koperasi_id') && $showTenant);

    $excelUrl = auth()->user()->can('laporan.absensi.export')
        ? route('laporan.absensi.export', request()->query())
        : null;

    $printUrl = auth()->user()->can('laporan.absensi.cetak')
        ? route('laporan.absensi.cetak', request()->query())
        : null;

    // Helper untuk membuat toggle link kartu filter status
    $cardFilterUrl = function (?string $targetStatus) {
        $query = request()->except(['page']);
        if (request('status') === $targetStatus) {
            unset($query['status']);
        } else {
            $query['status'] = $targetStatus;
        }
        return url()->current() . ($query ? '?' . http_build_query($query) : '');
    };
@endphp

<x-report-layout
    title="Laporan Absensi"
    :subtitle="$periodSubtitle"
    :excel-url="$excelUrl"
    :print-url="$printUrl"
    :paginator="$paginator"
    :unit="$mode === 'karyawan' ? 'karyawan' : 'catatan'"
    search-name="search"
    :search-value="request('search')"
    search-placeholder="Cari nama / NIK karyawan..."
    :has-active-filters="$hasActiveFilters"
    :reset-url="route('laporan.absensi')"
>
    {{-- Slot Filter Bar --}}
    <x-slot:filters>
        @if($showTenant)
            <select
                name="koperasi_id"
                class="form-select form-select-sm"
                aria-label="Filter Koperasi"
                style="min-width: 170px;"
                onchange="this.form.submit()"
            >
                <option value="">Koperasi: Semua</option>
                @foreach($koperasis as $koperasi)
                    <option value="{{ $koperasi->id }}" @selected((string) request('koperasi_id') === (string) $koperasi->id)>
                        {{ $koperasi->nama }}
                    </option>
                @endforeach
            </select>
        @endif

        {{-- Dropdown Bulan --}}
        <select
            name="bulan"
            class="form-select form-select-sm"
            aria-label="Filter Bulan"
            style="min-width: 130px;"
            onchange="this.form.submit()"
        >
            @for($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" @selected((int) old('bulan', $bulan) === $m)>
                    {{ \Carbon\CarbonImmutable::create(2000, $m, 1)->translatedFormat('F') }}
                </option>
            @endfor
        </select>

        {{-- Dropdown Tahun --}}
        <select
            name="tahun"
            class="form-select form-select-sm"
            aria-label="Filter Tahun"
            style="width: 95px;"
            onchange="this.form.submit()"
        >
            @for($y = max((int) date('Y'), $tahun); $y >= 2020; $y--)
                <option value="{{ $y }}" @selected((int) old('tahun', $tahun) === $y)>{{ $y }}</option>
            @endfor
        </select>

        {{-- Filter Opsi Periode Penggajian vs Kalender (C17) --}}
        <select
            name="tipe_periode"
            class="form-select form-select-sm"
            aria-label="Rentang Periode"
            style="min-width: 180px;"
            onchange="this.form.submit()"
        >
            <option value="kalender" @selected(request('tipe_periode') !== 'penggajian')>
                Bulan Kalender (1–31)
            </option>
            <option value="penggajian" @selected(request('tipe_periode') === 'penggajian')>
                Periode Gaji (25–24)
            </option>
        </select>

        {{-- Dropdown Unit Kerja --}}
        <select
            name="unit_kerja_id"
            class="form-select form-select-sm"
            aria-label="Filter Unit Kerja"
            style="min-width: 170px;"
            onchange="this.form.submit()"
        >
            <option value="">Unit Kerja: Semua</option>
            @foreach($unitKerjas as $unit)
                <option value="{{ $unit->id }}" @selected((string) request('unit_kerja_id') === (string) $unit->id)>
                    {{ $showTenant ? (($unit->koperasi?->nama ?? 'Tanpa koperasi').' — '.$unit->nama_unit) : $unit->nama_unit }}
                </option>
            @endforeach
        </select>
    </x-slot:filters>

    {{-- Slot Kartu Ringkasan (6 Kartu Interaktif Tombol Pintas Filter Status) --}}
    <x-slot:cards>
        <x-report-card
            label="Hadir"
            :value="number_format($totalHadir, 0, ',', '.')"
            icon="bi-person-check"
            clickable
            :active="request('status') === 'Hadir'"
            :href="$cardFilterUrl('Hadir')"
            :subtitle="request('status') === 'Hadir' ? 'Filter aktif (klik lepas)' : 'Klik untuk filter'"
        />
        <x-report-card
            label="Izin"
            :value="number_format($totalIzin, 0, ',', '.')"
            icon="bi-envelope-check"
            clickable
            :active="request('status') === 'Izin'"
            :href="$cardFilterUrl('Izin')"
            :subtitle="request('status') === 'Izin' ? 'Filter aktif (klik lepas)' : 'Klik untuk filter'"
        />
        <x-report-card
            label="Sakit"
            :value="number_format($totalSakit, 0, ',', '.')"
            icon="bi-heart-pulse"
            clickable
            :active="request('status') === 'Sakit'"
            :href="$cardFilterUrl('Sakit')"
            :subtitle="request('status') === 'Sakit' ? 'Filter aktif (klik lepas)' : 'Klik untuk filter'"
        />
        <x-report-card
            label="Cuti"
            :value="number_format($totalCuti, 0, ',', '.')"
            icon="bi-calendar2-week"
            clickable
            :active="request('status') === 'Cuti'"
            :href="$cardFilterUrl('Cuti')"
            :subtitle="request('status') === 'Cuti' ? 'Filter aktif (klik lepas)' : 'Klik untuk filter'"
        />
        <x-report-card
            label="Dinas"
            :value="number_format($totalDinasLuarKota, 0, ',', '.')"
            icon="bi-geo-alt"
            clickable
            :active="request('status') === 'Dinas'"
            :href="$cardFilterUrl('Dinas')"
            :subtitle="request('status') === 'Dinas' ? 'Filter aktif (klik lepas)' : 'Klik untuk filter'"
        />
        <x-report-card
            label="Alpha"
            :value="number_format($totalAlpha, 0, ',', '.')"
            icon="bi-person-x"
            accent
            clickable
            :active="request('status') === 'Alpha'"
            :href="$cardFilterUrl('Alpha')"
            :subtitle="request('status') === 'Alpha' ? 'Filter aktif (klik lepas)' : 'Klik untuk filter'"
        />
    </x-slot:cards>

    {{-- Header Tabel: Segmented Control Switch 2 Mode Tampilan (B5) --}}
    <x-slot:tableHeader>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-semibold text-body-emphasis">
                    @if($mode === 'karyawan')
                        Rekap Kehadiran per Karyawan
                    @else
                        Catatan Riwayat Absensi
                    @endif
                </span>
                @if(request()->filled('status'))
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle d-inline-flex align-items-center gap-1">
                        <span>Filter: {{ request('status') }}</span>
                        <a href="{{ $cardFilterUrl(request('status')) }}" class="text-reset text-decoration-none" title="Hapus filter status">
                            <i class="bi bi-x"></i>
                        </a>
                    </span>
                @endif
            </div>

            {{-- Segmented control 2 mode tampilan --}}
            <div class="btn-group btn-group-sm" role="group" aria-label="Mode Tampilan Laporan Absensi">
                @php
                    $urlKaryawan = url()->current() . '?' . http_build_query([...request()->query(), 'mode' => 'karyawan', 'page' => 1]);
                    $urlTanggal = url()->current() . '?' . http_build_query([...request()->query(), 'mode' => 'tanggal', 'page' => 1]);
                @endphp
                <a
                    href="{{ $urlKaryawan }}"
                    class="btn {{ $mode === 'karyawan' ? 'btn-primary' : 'btn-outline-secondary' }}"
                    title="Tampilkan rekap per karyawan"
                >
                    <i class="bi bi-person-lines-fill me-1"></i>Mode Karyawan
                </a>
                <a
                    href="{{ $urlTanggal }}"
                    class="btn {{ $mode === 'tanggal' ? 'btn-primary' : 'btn-outline-secondary' }}"
                    title="Tampilkan riwayat per tanggal"
                >
                    <i class="bi bi-calendar3 me-1"></i>Mode Tanggal
                </a>
            </div>
        </div>
    </x-slot:tableHeader>

    {{-- Tabel Detail Sesuai Mode --}}
    @if($mode === 'karyawan')
        {{-- MODE 1: REKAP PER KARYAWAN (DEFAULT) --}}
        <table class="report-table align-middle">
            <thead>
                <tr>
                    <x-report-sort-th column="karyawan" label="Karyawan" default-direction="asc" />
                    @if($showTenant)
                        <th>Koperasi</th>
                    @endif
                    <x-report-sort-th column="unit_kerja" label="Unit Kerja" />
                    <x-report-sort-th column="hadir" label="Hadir" align="end" />
                    <x-report-sort-th column="izin" label="Izin" align="end" />
                    <x-report-sort-th column="sakit" label="Sakit" align="end" />
                    <x-report-sort-th column="cuti" label="Cuti" align="end" />
                    <x-report-sort-th column="dinas" label="Dinas" align="end" />
                    <x-report-sort-th column="alpha" label="Alpha" align="end" />
                    <x-report-sort-th column="total_hari" label="Total Hari" align="end" />
                </tr>
            </thead>
            <tbody>
                @forelse($paginator as $row)
                    <tr>
                        {{-- 2-line cell: Nama tebal (link ke detail karyawan) + NIK --}}
                        <td>
                            <div class="report-cell-identity">
                                <a href="{{ route('karyawan.show', $row->karyawan_id) }}" class="report-cell-identity__primary">
                                    {{ $row->nama_lengkap }}
                                </a>
                                <span class="report-cell-identity__secondary">{{ $row->nik }}</span>
                            </div>
                        </td>
                        @if($showTenant)
                            <td class="text-nowrap">{{ $row->nama_koperasi ?? 'Tanpa koperasi' }}</td>
                        @endif
                        <td class="text-nowrap">{{ $row->nama_unit ?? '—' }}</td>
                        <td class="text-end tabular-nums text-nowrap">{{ number_format($row->hadir, 0, ',', '.') }}</td>
                        <td class="text-end tabular-nums text-nowrap">{{ number_format($row->izin, 0, ',', '.') }}</td>
                        <td class="text-end tabular-nums text-nowrap">{{ number_format($row->sakit, 0, ',', '.') }}</td>
                        <td class="text-end tabular-nums text-nowrap">{{ number_format($row->cuti, 0, ',', '.') }}</td>
                        <td class="text-end tabular-nums text-nowrap">{{ number_format($row->dinas, 0, ',', '.') }}</td>
                        <td class="text-end tabular-nums text-nowrap {{ $row->alpha > 0 ? 'text-danger fw-semibold' : '' }}">
                            {{ number_format($row->alpha, 0, ',', '.') }}
                        </td>
                        <td class="text-end tabular-nums text-nowrap fw-bold">{{ number_format($row->total_hari, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showTenant ? 10 : 9 }}" class="text-center py-4 text-body-secondary">
                            @if(filled(request('search')))
                                Tidak ada data yang cocok.
                            @else
                                Belum ada data absensi untuk periode ini.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($paginator->total() > 0)
                <tfoot>
                    <tr>
                        <td colspan="{{ $showTenant ? 3 : 2 }}">
                            Total Seluruh Karyawan ({{ $paginator->total() }})
                        </td>
                        <td class="text-end tabular-nums text-nowrap">{{ number_format($modeTotals['totalHadir'], 0, ',', '.') }}</td>
                        <td class="text-end tabular-nums text-nowrap">{{ number_format($modeTotals['totalIzin'], 0, ',', '.') }}</td>
                        <td class="text-end tabular-nums text-nowrap">{{ number_format($modeTotals['totalSakit'], 0, ',', '.') }}</td>
                        <td class="text-end tabular-nums text-nowrap">{{ number_format($modeTotals['totalCuti'], 0, ',', '.') }}</td>
                        <td class="text-end tabular-nums text-nowrap">{{ number_format($modeTotals['totalDinas'], 0, ',', '.') }}</td>
                        <td class="text-end tabular-nums text-nowrap">{{ number_format($modeTotals['totalAlpha'], 0, ',', '.') }}</td>
                        <td class="text-end tabular-nums text-nowrap fw-bold">{{ number_format($modeTotals['totalHari'], 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    @else
        {{-- MODE 2: KRONOLOGIS RIWAYAT PER TANGGAL --}}
        <table class="report-table align-middle">
            <thead>
                <tr>
                    <x-report-sort-th column="tanggal" label="Tanggal" default-direction="asc" />
                    <x-report-sort-th column="karyawan" label="Karyawan" />
                    @if($showTenant)
                        <th>Koperasi</th>
                    @endif
                    <x-report-sort-th column="unit_kerja" label="Unit Kerja" />
                    <x-report-sort-th column="status" label="Status" />
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($paginator as $absensi)
                    <tr>
                        <td class="text-nowrap">{{ $absensi->tanggal->translatedFormat('d M Y') }}</td>
                        <td>
                            <div class="report-cell-identity">
                                <a href="{{ route('karyawan.show', $absensi->karyawan_id) }}" class="report-cell-identity__primary">
                                    {{ $absensi->karyawan?->nama_lengkap ?? '—' }}
                                </a>
                                <span class="report-cell-identity__secondary">{{ $absensi->karyawan?->nik ?? '' }}</span>
                            </div>
                        </td>
                        @if($showTenant)
                            <td class="text-nowrap">{{ $absensi->koperasi?->nama ?? 'Tanpa koperasi' }}</td>
                        @endif
                        <td class="text-nowrap">{{ $absensi->karyawan?->unitKerja?->nama_unit ?? '—' }}</td>
                        <td class="text-nowrap">
                            <x-badge :color="\App\Models\Absensi::STATUS_COLORS[$absensi->status] ?? 'bg-secondary'">
                                {{ \App\Models\Absensi::statusLabel($absensi->status) }}
                            </x-badge>
                        </td>
                        <td>{{ $absensi->catatan ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showTenant ? 6 : 5 }}" class="text-center py-4 text-body-secondary">
                            @if(filled(request('search')))
                                Tidak ada data yang cocok.
                            @else
                                Belum ada data absensi untuk periode ini.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($totalRecords > 0)
                <tfoot>
                    <tr>
                        <td colspan="{{ $showTenant ? 6 : 5 }}">
                            Total Catatan: {{ number_format($totalRecords, 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    @endif
</x-report-layout>
@endsection
