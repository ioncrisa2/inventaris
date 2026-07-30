@extends('layouts.print')

@section('title', 'Cetak Laporan Penyusutan')
@section('back_url', route('laporan.penyusutan', request()->query()))

@section('content')
<x-print.header>
    <h1 class="report-title">Laporan Penyusutan</h1>
    <p class="report-subtitle">Rekap penyusutan fiskal aset tahun {{ $tahun }} — dasar pelaporan SPT.</p>

    <x-slot:meta>
        <p>Tanggal cetak: {{ now()->translatedFormat('d F Y H:i') }}</p>
        <p>Tahun: {{ $tahun }}</p>
        <p>Unit kerja: {{ $selectedUnitKerja?->nama_unit ?? 'Semua unit kerja' }}</p>
        <p>Golongan: {{ request('kategori', 'Semua golongan') }}</p>
    </x-slot:meta>
</x-print.header>

<section class="summary-grid">
    <x-print.summary-item label="Total Aset" :value="number_format($totalAset, 0, ',', '.')" />
    <x-print.summary-item label="Total Harga Perolehan" :value="'Rp '.number_format($totalHargaPerolehan, 0, ',', '.')" />
    <x-print.summary-item label="Penyusutan Tahun {{ $tahun }}" :value="'Rp '.number_format($totalPenyusutanTahunIni, 0, ',', '.')" />
    <x-print.summary-item label="Total Nilai Buku" :value="'Rp '.number_format($totalNilaiBuku, 0, ',', '.')" />
</section>

<h2 class="section-title">Daftar Penyusutan Fiskal</h2>
<table>
    <thead>
        <tr>
            <th>Kode</th>
            <th>Nama Barang</th>
            <th>Golongan</th>
            <th>Metode</th>
            <th>Tgl. Perolehan</th>
            <th class="text-end">Harga Perolehan</th>
            <th class="text-end">Akumulasi Awal Tahun</th>
            <th class="text-end">Penyusutan Tahun Ini</th>
            <th class="text-end">Akumulasi Akhir Tahun</th>
            <th class="text-end">Nilai Buku Akhir Tahun</th>
        </tr>
    </thead>
    <tbody>
        @forelse($barangs as $barang)
        @php $r = $rincian->get($barang->id); @endphp
        <tr>
            <td>{{ $barang->kode_barang }}</td>
            <td>{{ $barang->nama_barang }}</td>
            <td>{{ config('inventaris.kategori_label_singkat')[$barang->kategori] ?? $barang->kategori }}</td>
            <td>{{ \App\Support\PenyusutanCalculator::namaMetode($r['metode']) }}</td>
            <td>{{ $barang->tanggal_perolehan->format('d/m/Y') }}</td>
            <td class="text-end">Rp {{ number_format($barang->harga_perolehan, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($r['akumulasi_awal_tahun'], 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($r['penyusutan_tahun_ini'], 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($r['akumulasi_akhir_tahun'], 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($r['nilai_buku_akhir_tahun'], 0, ',', '.') }}</td>
        </tr>
        @empty
        <tr><td colspan="10" class="text-center muted">Tidak ada aset yang cocok dengan filter untuk tahun {{ $tahun }}.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">Total</td>
            <td class="text-end">Rp {{ number_format($totalHargaPerolehan, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($totalAkumulasiAwalTahun, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($totalPenyusutanTahunIni, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($totalAkumulasiPenyusutan, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($totalNilaiBuku, 0, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

<x-print.signature />
@endsection
