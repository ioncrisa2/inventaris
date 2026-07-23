@extends('layouts.print')

@section('title', 'Cetak Laporan Penggajian')
@section('back_url', route('laporan.penggajian', request()->query()))

@section('content')
<x-print.header>
    <h1 class="report-title">Laporan Penggajian</h1>
    <p class="report-subtitle">Rekap gaji yang dibayarkan per periode dan unit kerja.</p>

    <x-slot:meta>
        <p>Tanggal cetak: {{ now()->translatedFormat('d F Y H:i') }}</p>
        <p>Periode: {{ DateTime::createFromFormat('!m', $bulan)->format('F') }} {{ $tahun }}</p>
        <p>Unit kerja: {{ $selectedUnitKerja?->nama_unit ?? 'Semua unit kerja' }}</p>
    </x-slot:meta>
</x-print.header>

<section class="summary-grid">
    <x-print.summary-item label="Total Transaksi" :value="number_format($totalTransaksi, 0, ',', '.')" />
    <x-print.summary-item label="Total Gaji Pokok" :value="'Rp '.number_format($totalGajiPokok, 0, ',', '.')" />
    <x-print.summary-item label="Total Tunjangan" :value="'Rp '.number_format($totalTunjangan, 0, ',', '.')" />
    <x-print.summary-item label="Total Potongan" :value="'Rp '.number_format($totalPotongan, 0, ',', '.')" />
    <x-print.summary-item label="Total Gaji Bersih" :value="'Rp '.number_format($totalGajiBersih, 0, ',', '.')" />
</section>

<h2 class="section-title">Rekapitulasi per Unit Kerja</h2>
<table>
    <thead>
        <tr>
            <th>Unit Kerja</th>
            <th class="text-end">Jumlah Transaksi</th>
            <th class="text-end">Total Gaji Bersih</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rekapUnitKerja as $rekap)
            <tr>
                <td>{{ $rekap->nama_unit }}</td>
                <td class="text-end">{{ number_format($rekap->total_transaksi, 0, ',', '.') }}</td>
                <td class="text-end">Rp {{ number_format($rekap->total_gaji_bersih, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="text-center muted">Tidak ada transaksi gaji pada periode ini.</td></tr>
        @endforelse
    </tbody>
</table>

<h2 class="section-title">Detail Transaksi Gaji</h2>
<table>
    <thead>
        <tr>
            <th>Karyawan</th>
            <th>Unit Kerja</th>
            <th class="text-end">Gaji Pokok</th>
            <th class="text-end">Gaji Bersih</th>
        </tr>
    </thead>
    <tbody>
        @forelse($transaksiGaji as $transaksi)
            <tr>
                <td>{{ $transaksi->karyawan?->nama_lengkap ?? '-' }}</td>
                <td>{{ $transaksi->karyawan?->unitKerja?->nama_unit ?? '-' }}</td>
                <td class="text-end">Rp {{ number_format($transaksi->gaji_pokok, 0, ',', '.') }}</td>
                <td class="text-end">Rp {{ number_format($transaksi->gaji_bersih, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center muted">Tidak ada transaksi gaji yang cocok dengan filter.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">Total</td>
            <td class="text-end">Rp {{ number_format($totalGajiPokok, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($totalGajiBersih, 0, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

<x-print.signature />
@endsection
