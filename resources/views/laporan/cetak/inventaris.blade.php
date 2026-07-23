@extends('layouts.print')

@section('title', 'Cetak Laporan Inventaris')
@section('back_url', route('laporan.inventaris', request()->query()))

@section('content')
<x-print.header>
    <h1 class="report-title">Laporan Inventaris</h1>
    <p class="report-subtitle">Ringkasan nilai, kondisi, dan detail inventaris.</p>

    <x-slot:meta>
        <p>Tanggal cetak: {{ now()->translatedFormat('d F Y H:i') }}</p>
        <p>Unit kerja: {{ $selectedUnitKerja?->nama_unit ?? 'Semua unit kerja' }}</p>
        <p>Golongan: {{ request('kategori', 'Semua golongan') }}</p>
        <p>Periode: {{ request('tanggal_awal', 'Awal') }} s/d {{ request('tanggal_akhir', 'Akhir') }}</p>
    </x-slot:meta>
</x-print.header>

<section class="summary-grid">
    <x-print.summary-item label="Total Barang" :value="number_format($totalBarang, 0, ',', '.')" />
    <x-print.summary-item label="Total Nilai Perolehan" :value="'Rp '.number_format($totalNilai, 0, ',', '.')" />
    <x-print.summary-item label="Perlu Perbaikan" :value="number_format($barangPerluPerbaikan, 0, ',', '.')" />
    <x-print.summary-item label="Jumlah Golongan" :value="number_format($rekapKategori->count(), 0, ',', '.')" />
</section>

<h2 class="section-title">Rekapitulasi per Golongan</h2>
<table>
    <thead>
        <tr>
            <th>Golongan</th>
            <th class="text-end">Jumlah Barang</th>
            <th class="text-end">Total Nilai</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rekapKategori as $rekap)
            <tr>
                <td>{{ $rekap->kategori }}</td>
                <td class="text-end">{{ number_format($rekap->total_barang, 0, ',', '.') }}</td>
                <td class="text-end">Rp {{ number_format($rekap->total_nilai, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="text-center muted">Tidak ada data untuk direkap.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td class="text-end">{{ number_format($totalBarang, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($totalNilai, 0, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

<h2 class="section-title">Detail Inventaris</h2>
<table>
    <thead>
        <tr>
            <th>Kode</th>
            <th>Nama Barang</th>
            <th>Golongan</th>
            <th>Unit Kerja</th>
            <th>Tanggal Perolehan</th>
            <th>Kondisi</th>
            <th class="text-end">Nilai</th>
        </tr>
    </thead>
    <tbody>
        @forelse($barangs as $barang)
            <tr>
                <td>{{ $barang->kode_barang }}</td>
                <td>{{ $barang->nama_barang }}</td>
                <td>{{ $barang->kategori }}</td>
                <td>{{ $barang->unitKerja?->nama_unit ?? '-' }}</td>
                <td>{{ $barang->tanggal_perolehan->format('d/m/Y') }}</td>
                <td>{{ $barang->kondisiTerakhir?->kondisi ?? 'Belum diperiksa' }}</td>
                <td class="text-end">Rp {{ number_format($barang->harga_perolehan, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center muted">Tidak ada inventaris yang cocok dengan filter.</td></tr>
        @endforelse
    </tbody>
</table>

<x-print.signature />
@endsection
