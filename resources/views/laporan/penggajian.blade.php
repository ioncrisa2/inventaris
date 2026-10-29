@extends('layouts.app')

@section('title', 'Laporan Penggajian - Sistem Inventaris & Kepegawaian')

@section('content')
@php
    $showTenant = auth()->user()->isSuperAdmin();
    $currentDate = \Carbon\CarbonImmutable::create($tahun, $bulan, 1);
    $prevDate = $currentDate->subMonthNoOverflow();
    $tglAwal = $prevDate->setDay(25)->translatedFormat('d M');
    $tglAkhir = $currentDate->setDay(24)->translatedFormat('d M Y');
    $namaBulan = $currentDate->translatedFormat('F');
    $periodText = "{$namaBulan} {$tahun} ({$tglAwal} – {$tglAkhir})";

    $hasActiveFilters = request()->filled('unit_kerja_id')
        || (int) $bulan !== now()->month
        || (int) $tahun !== now()->year
        || request()->filled('search')
        || (request()->filled('koperasi_id') && $showTenant);

    $excelUrl = auth()->user()->can('laporan.penggajian.export')
        ? route('laporan.penggajian.export', request()->query())
        : null;

    $printUrl = auth()->user()->can('laporan.penggajian.cetak')
        ? route('laporan.penggajian.cetak', request()->query())
        : null;
@endphp

<x-report-layout
    title="Laporan Penggajian"
    :subtitle="'Periode ' . $periodText"
    :excel-url="$excelUrl"
    :print-url="$printUrl"
    :paginator="$transaksiGaji"
    unit="transaksi"
    search-name="search"
    :search-value="request('search')"
    search-placeholder="Cari nama / NIK karyawan..."
    :has-active-filters="$hasActiveFilters"
    :reset-url="route('laporan.penggajian')"
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

        {{-- Dropdown Bulan dengan rentang tanggal periode gaji (C14) --}}
        <select
            name="bulan"
            class="form-select form-select-sm"
            aria-label="Filter Bulan"
            style="min-width: 210px;"
            onchange="this.form.submit()"
        >
            @for($m = 1; $m <= 12; $m++)
                @php
                    $mDate = \Carbon\CarbonImmutable::create($tahun, $m, 1);
                    $mPrev = $mDate->subMonthNoOverflow();
                    $mRange = $mPrev->setDay(25)->translatedFormat('d M') . '–' . $mDate->setDay(24)->translatedFormat('d M');
                    $mName = $mDate->translatedFormat('F');
                @endphp
                <option value="{{ $m }}" @selected((int) old('bulan', $bulan) === $m)>
                    {{ $mName }} · {{ $mRange }}
                </option>
            @endfor
        </select>

        {{-- Dropdown Tahun --}}
        <select
            name="tahun"
            class="form-select form-select-sm"
            aria-label="Filter Tahun"
            style="width: 100px;"
            onchange="this.form.submit()"
        >
            @for($y = max((int) date('Y'), $tahun); $y >= 2020; $y--)
                <option value="{{ $y }}" @selected((int) old('tahun', $tahun) === $y)>{{ $y }}</option>
            @endfor
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

    {{-- Slot Kartu Ringkasan (Urut sesuai rumus: Transaksi, Gaji Pokok, Tunjangan, Potongan, Gaji Bersih) --}}
    <x-slot:cards>
        <x-report-card
            label="Total Transaksi"
            :value="number_format($totalTransaksi, 0, ',', '.')"
            icon="bi-receipt"
        />
        <x-report-card
            label="Gaji Pokok"
            :value="'Rp ' . number_format($totalGajiPokok, 0, ',', '.')"
            icon="bi-cash-stack"
        />
        <x-report-card
            label="Tunjangan"
            :value="'Rp ' . number_format($totalTunjangan, 0, ',', '.')"
            icon="bi-plus-circle"
        />
        <x-report-card
            label="Potongan"
            :value="'Rp ' . number_format($totalPotongan, 0, ',', '.')"
            icon="bi-dash-circle"
        />
        <x-report-card
            label="Gaji Bersih"
            :value="'Rp ' . number_format($totalGajiBersih, 0, ',', '.')"
            icon="bi-wallet2"
            accent
        />
    </x-slot:cards>

    {{-- Slot Panel Rekap (Rekap per Unit Kerja) --}}
    <x-slot:recap>
        <x-report-recap-panel title="Rekap per Unit">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Unit Kerja</th>
                        <th class="text-end">Jumlah Transaksi</th>
                        <th class="text-end">Total Gaji Bersih</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rekapUnitKerja as $rekap)
                        <tr>
                            <td>
                                {{ $showTenant ? (($rekap->nama_koperasi ?? 'Tanpa koperasi').' — '.$rekap->nama_unit) : $rekap->nama_unit }}
                            </td>
                            <td class="text-end tabular-nums">{{ number_format($rekap->total_transaksi, 0, ',', '.') }}</td>
                            <td class="text-end tabular-nums">Rp {{ number_format($rekap->total_gaji_bersih, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-3 text-body-secondary">Tidak ada transaksi gaji pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($totalTransaksi > 0)
                    <tfoot>
                        <tr>
                            <td>Total Keseluruhan</td>
                            <td class="text-end tabular-nums">{{ number_format($totalTransaksi, 0, ',', '.') }}</td>
                            <td class="text-end tabular-nums">Rp {{ number_format($totalGajiBersih, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </x-report-recap-panel>
    </x-slot:recap>

    {{-- Slot Tabel Detail --}}
    <table class="report-table align-middle">
        <thead>
            <tr>
                <x-report-sort-th column="karyawan" label="Karyawan" default-direction="asc" />
                @if($showTenant)
                    <th>Koperasi</th>
                @endif
                <x-report-sort-th column="unit_kerja" label="Unit Kerja" />
                <x-report-sort-th column="gaji_pokok" label="Gaji Pokok" align="end" />
                <x-report-sort-th column="tunjangan" label="Tunjangan" align="end" />
                <x-report-sort-th column="potongan" label="Potongan" align="end" />
                <x-report-sort-th column="gaji_bersih" label="Gaji Bersih" align="end" />
            </tr>
        </thead>
        <tbody>
            @forelse($transaksiGaji as $transaksi)
                <tr>
                    {{-- 2-line cell: Nama tebal (link ke detail transaksi gaji) + NIK --}}
                    <td>
                        <div class="report-cell-identity">
                            @can('transaksi-gaji.view')
                                <a href="{{ route('transaksi-gaji.show', $transaksi->id) }}" class="report-cell-identity__primary">
                                    {{ $transaksi->karyawan?->nama_lengkap ?? '—' }}
                                </a>
                            @else
                                <span class="report-cell-identity__primary">
                                    {{ $transaksi->karyawan?->nama_lengkap ?? '—' }}
                                </span>
                            @endcan
                            <span class="report-cell-identity__secondary">{{ $transaksi->karyawan?->nik ?? '' }}</span>
                        </div>
                    </td>
                    @if($showTenant)
                        <td class="text-nowrap">{{ $transaksi->koperasi?->nama ?? 'Tanpa koperasi' }}</td>
                    @endif
                    <td class="text-nowrap">{{ $transaksi->karyawan?->unitKerja?->nama_unit ?? '—' }}</td>
                    <td class="text-end tabular-nums text-nowrap">
                        Rp {{ number_format($transaksi->gaji_pokok, 0, ',', '.') }}
                    </td>
                    <td class="text-end tabular-nums text-nowrap text-success">
                        + Rp {{ number_format($transaksi->tunjangan ?? 0, 0, ',', '.') }}
                    </td>
                    <td class="text-end tabular-nums text-nowrap text-danger">
                        - Rp {{ number_format($transaksi->potongan ?? 0, 0, ',', '.') }}
                    </td>
                    <td class="text-end tabular-nums text-nowrap fw-semibold">
                        Rp {{ number_format($transaksi->gaji_bersih, 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showTenant ? 7 : 6 }}" class="text-center py-4 text-body-secondary">
                        @if(filled(request('search')))
                            Tidak ada data yang cocok.
                        @else
                            Tidak ada transaksi gaji pada periode ini.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($totalTransaksi > 0)
            <tfoot>
                <tr>
                    <td colspan="{{ $showTenant ? 3 : 2 }}">
                        Total Keseluruhan ({{ number_format($totalTransaksi, 0, ',', '.') }} transaksi)
                    </td>
                    <td class="text-end tabular-nums text-nowrap">
                        Rp {{ number_format($totalGajiPokok, 0, ',', '.') }}
                    </td>
                    <td class="text-end tabular-nums text-nowrap text-success">
                        + Rp {{ number_format($totalTunjangan, 0, ',', '.') }}
                    </td>
                    <td class="text-end tabular-nums text-nowrap text-danger">
                        - Rp {{ number_format($totalPotongan, 0, ',', '.') }}
                    </td>
                    <td class="text-end tabular-nums text-nowrap fw-bold">
                        Rp {{ number_format($totalGajiBersih, 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>
</x-report-layout>
@endsection
