@extends('layouts.print')

@section('title', 'Cetak Laporan Absensi')
@section('back_url', route('laporan.absensi', request()->query()))

@section('content')
<x-print.header>
    <h1 class="report-title">Laporan Absensi</h1>
    <p class="report-subtitle">Ringkasan kehadiran pegawai berdasarkan periode.</p>

    <x-slot:meta>
        <p>Tanggal cetak: {{ now()->translatedFormat('d F Y H:i') }}</p>
        <p>Periode: {{ DateTime::createFromFormat('!m', $bulan)->format('F') }} {{ $tahun }}</p>
        <p>Pegawai: {{ $selectedKaryawan?->nama_lengkap ?? 'Semua pegawai' }}</p>
    </x-slot:meta>
</x-print.header>

<section class="summary-grid">
    <x-print.summary-item label="Hadir" :value="number_format($totalHadir, 0, ',', '.')" />
    <x-print.summary-item label="Izin" :value="number_format($totalIzin, 0, ',', '.')" />
    <x-print.summary-item label="Sakit" :value="number_format($totalSakit, 0, ',', '.')" />
    <x-print.summary-item label="Cuti" :value="number_format($totalCuti, 0, ',', '.')" />
    <x-print.summary-item label="Dinas Luar Kota" :value="number_format($totalDinasLuarKota, 0, ',', '.')" />
    <x-print.summary-item label="Alpha" :value="number_format($totalAlpha, 0, ',', '.')" />
</section>

<h2 class="section-title">Detail Absensi</h2>
<table>
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>Pegawai</th>
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
                <td>{{ $absensi->status }}</td>
                <td>{{ $absensi->catatan ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center muted">Belum ada data absensi untuk periode ini.</td></tr>
        @endforelse
    </tbody>
</table>

<x-print.signature />
@endsection
