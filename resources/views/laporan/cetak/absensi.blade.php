@extends('layouts.print')

@section('title', 'Cetak Laporan Absensi')
@section('back_url', route('laporan.absensi', request()->query()))

@section('content')
@php
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
@endphp

<x-print.header>
    <h1 class="report-title">Laporan Absensi</h1>
    <p class="report-subtitle">Ringkasan kehadiran karyawan berdasarkan periode.</p>

    <x-slot:meta>
        <p>Tanggal cetak: {{ now()->translatedFormat('d F Y H:i') }}</p>
        <p>Periode: {{ $periodSubtitle }}</p>
        <p>Karyawan: {{ $selectedKaryawan?->nama_lengkap ?? 'Semua karyawan' }}</p>
    </x-slot:meta>
</x-print.header>

<section class="summary-grid">
    <x-print.summary-item label="Hadir" :value="number_format($totalHadir, 0, ',', '.')" />
    <x-print.summary-item label="Izin" :value="number_format($totalIzin, 0, ',', '.')" />
    <x-print.summary-item label="Sakit" :value="number_format($totalSakit, 0, ',', '.')" />
    <x-print.summary-item label="Cuti" :value="number_format($totalCuti, 0, ',', '.')" />
    <x-print.summary-item label="Dinas" :value="number_format($totalDinasLuarKota, 0, ',', '.')" />
    <x-print.summary-item label="Alpha" :value="number_format($totalAlpha, 0, ',', '.')" />
</section>

@if(count($karyawanRows) > 0)
    <h2 class="section-title">Rekap Kehadiran per Karyawan</h2>
    <table>
        <thead>
            <tr>
                <th>Karyawan</th>
                <th>Unit Kerja</th>
                <th class="text-end">Hadir</th>
                <th class="text-end">Izin</th>
                <th class="text-end">Sakit</th>
                <th class="text-end">Cuti</th>
                <th class="text-end">Dinas</th>
                <th class="text-end">Alpha</th>
                <th class="text-end">Total Hari</th>
            </tr>
        </thead>
        <tbody>
            @foreach($karyawanRows as $row)
                <tr>
                    <td>{{ $row->nama_lengkap }} ({{ $row->nik }})</td>
                    <td>{{ $row->nama_unit ?? '-' }}</td>
                    <td class="text-end">{{ number_format($row->hadir, 0, ',', '.') }}</td>
                    <td class="text-end">{{ number_format($row->izin, 0, ',', '.') }}</td>
                    <td class="text-end">{{ number_format($row->sakit, 0, ',', '.') }}</td>
                    <td class="text-end">{{ number_format($row->cuti, 0, ',', '.') }}</td>
                    <td class="text-end">{{ number_format($row->dinas, 0, ',', '.') }}</td>
                    <td class="text-end">{{ number_format($row->alpha, 0, ',', '.') }}</td>
                    <td class="text-end fw-bold">{{ number_format($row->total_hari, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Total Seluruh Karyawan</td>
                <td class="text-end">{{ number_format($totalHadir, 0, ',', '.') }}</td>
                <td class="text-end">{{ number_format($totalIzin, 0, ',', '.') }}</td>
                <td class="text-end">{{ number_format($totalSakit, 0, ',', '.') }}</td>
                <td class="text-end">{{ number_format($totalCuti, 0, ',', '.') }}</td>
                <td class="text-end">{{ number_format($totalDinasLuarKota, 0, ',', '.') }}</td>
                <td class="text-end">{{ number_format($totalAlpha, 0, ',', '.') }}</td>
                <td class="text-end fw-bold">{{ number_format($modeTotals['totalHari'], 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
@endif

<h2 class="section-title">Detail Riwayat Absensi</h2>
<table>
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>Karyawan</th>
            <th>Unit Kerja</th>
            <th>Status</th>
            <th>Catatan</th>
        </tr>
    </thead>
    <tbody>
        @forelse($absensis as $absensi)
            <tr>
                <td>{{ $absensi->tanggal->translatedFormat('d F Y') }}</td>
                <td>{{ $absensi->karyawan?->nama_lengkap ?? '-' }}</td>
                <td>{{ $absensi->karyawan?->unitKerja?->nama_unit ?? '-' }}</td>
                <td>{{ \App\Models\Absensi::statusLabel($absensi->status) }}</td>
                <td>{{ $absensi->catatan ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center muted">Belum ada data absensi untuk periode ini.</td></tr>
        @endforelse
    </tbody>
    @if(count($absensis) > 0)
        <tfoot>
            <tr>
                <td colspan="5">Total Catatan: {{ number_format(count($absensis), 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    @endif
</table>

<x-print.signature />
@endsection
