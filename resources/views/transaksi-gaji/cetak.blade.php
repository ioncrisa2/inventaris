@extends('layouts.print')

@section('title', 'Slip Gaji - '.$transaksiGaji->karyawan->nama_lengkap)
@section('back_url', route('transaksi-gaji.show', $transaksiGaji))
@section('print_layout', 'a4-portrait')

@php
    $namaBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
@endphp
@section('content')
<x-print.header>
    <div class="slip-brand">
        <img src="{{ asset('assets/img/logo-koperasi.png') }}" alt="Logo {{ config('app.name') }}" class="slip-logo">
        <div>
            <div class="slip-org-name">{{ config('app.name') }}</div>
            <div class="slip-org-sub">Sistem Inventaris &amp; Kepegawaian</div>
        </div>
    </div>
    <h1 class="report-title slip-title">Slip Gaji</h1>
    <p class="report-subtitle">Periode {{ $namaBulan[$transaksiGaji->bulan] }} {{ $transaksiGaji->tahun }}</p>

    <x-slot:meta>
        <p>No. Transaksi: TG-{{ str_pad($transaksiGaji->id, 6, '0', STR_PAD_LEFT) }}</p>
        <p>Tanggal cetak: {{ now()->translatedFormat('d F Y H:i') }}</p>
    </x-slot:meta>
</x-print.header>

<div class="info-card">
    <dl class="info-grid">
        <dt>NIK</dt>
        <dd>{{ $transaksiGaji->karyawan->nik }}</dd>

        <dt>Nama Lengkap</dt>
        <dd>{{ $transaksiGaji->karyawan->nama_lengkap }}</dd>

        <dt>Unit Kerja</dt>
        <dd>{{ $transaksiGaji->karyawan->unitKerja?->nama_unit ?? '-' }}</dd>

        <dt>Jabatan</dt>
        <dd>{{ $transaksiGaji->karyawan->jabatan }}</dd>
    </dl>
</div>

<h2 class="section-title">Rincian Gaji</h2>
<table>
    <thead>
        <tr>
            <th></th>
            <th class="text-end">Nominal</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Gaji Pokok</td>
            <td class="text-end">Rp {{ number_format($transaksiGaji->gaji_pokok, 0, ',', '.') }}</td>
        </tr>
        @forelse($transaksiGaji->details as $detail)
        <tr>
            <td>{{ $detail->nama_komponen_snapshot }}</td>
            <td class="text-end {{ $detail->jenis_snapshot === 'Tunjangan' ? 'amount-positive' : 'amount-negative' }}">
                {{ $detail->jenis_snapshot === 'Tunjangan' ? '+' : '-' }} Rp {{ number_format($detail->nominal_hasil, 0, ',', '.') }}
            </td>
        </tr>
        @empty
        <tr><td colspan="2" class="text-center muted">Tidak ada komponen tunjangan/potongan pada transaksi ini.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td class="text-end">Total Tunjangan</td>
            <td class="text-end amount-positive">+ Rp {{ number_format($totalTunjangan, 0, ',', '.') }}</td>
        </tr>
        <tr class="total-row">
            <td class="text-end">Total Potongan</td>
            <td class="text-end amount-negative">- Rp {{ number_format($totalPotongan, 0, ',', '.') }}</td>
        </tr>
        <tr class="grand-total-row">
            <td class="text-end">Gaji Bersih</td>
            <td class="text-end">Rp {{ number_format($transaksiGaji->gaji_bersih, 0, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

<x-print.signature label="Diterima oleh," :name="$transaksiGaji->karyawan->nama_lengkap" />
@endsection
