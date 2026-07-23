@extends('layouts.print')

@section('title', 'Cetak Laporan Kepegawaian')
@section('back_url', route('laporan.kepegawaian', request()->query()))

@section('content')
<x-print.header>
    <h1 class="report-title">Laporan Kepegawaian</h1>
    <p class="report-subtitle">Ringkasan status, penempatan, dan gaji pokok karyawan.</p>

    <x-slot:meta>
        <p>Tanggal cetak: {{ now()->translatedFormat('d F Y H:i') }}</p>
        <p>Unit kerja: {{ $selectedUnitKerja?->nama_unit ?? 'Semua unit kerja' }}</p>
        <p>Status: {{ request('status_karyawan', 'Semua status') }}</p>
    </x-slot:meta>
</x-print.header>

<section class="summary-grid">
    <x-print.summary-item label="Total Karyawan" :value="number_format($totalKaryawan, 0, ',', '.')" />
    <x-print.summary-item label="Karyawan Aktif" :value="number_format($totalAktif, 0, ',', '.')" />
    <x-print.summary-item label="Karyawan Mengundurkan Diri" :value="number_format($totalMengundurkanDiri, 0, ',', '.')" />
    <x-print.summary-item label="Gaji Pokok Aktif" :value="'Rp '.number_format($totalGajiAktif, 0, ',', '.')" />
</section>

<h2 class="section-title">Rekapitulasi Status</h2>
<table>
    <thead>
        <tr>
            <th>Status</th>
            <th class="text-end">Jumlah</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rekapStatus as $rekap)
            <tr>
                <td>{{ $rekap->status_karyawan }}</td>
                <td class="text-end">{{ number_format($rekap->total_karyawan, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="2" class="text-center muted">Tidak ada data status.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td class="text-end">{{ number_format($totalKaryawan, 0, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

<h2 class="section-title">Distribusi per Unit Kerja</h2>
<table>
    <thead>
        <tr>
            <th>Unit Kerja</th>
            <th class="text-end">Jumlah Karyawan</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rekapUnitKerja as $rekap)
            <tr>
                <td>{{ $rekap->unitKerja?->nama_unit ?? 'Tanpa unit kerja' }}</td>
                <td class="text-end">{{ number_format($rekap->total_karyawan, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="2" class="text-center muted">Tidak ada data unit kerja.</td></tr>
        @endforelse
    </tbody>
</table>

<h2 class="section-title">Detail Karyawan</h2>
<table>
    <thead>
        <tr>
            <th>NIK</th>
            <th>Nama Lengkap</th>
            <th>Unit Kerja</th>
            <th>Jabatan</th>
            <th>Status</th>
            <th class="text-end">Gaji Pokok</th>
        </tr>
    </thead>
    <tbody>
        @forelse($karyawans as $karyawan)
            <tr>
                <td>{{ $karyawan->nik }}</td>
                <td>{{ $karyawan->nama_lengkap }}</td>
                <td>{{ $karyawan->unitKerja?->nama_unit ?? '-' }}</td>
                <td>{{ $karyawan->jabatan }}</td>
                <td>{{ $karyawan->status_karyawan }}</td>
                <td class="text-end">Rp {{ number_format($karyawan->gaji_pokok, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center muted">Tidak ada karyawan yang cocok dengan filter.</td></tr>
        @endforelse
    </tbody>
</table>

<x-print.signature />
@endsection
