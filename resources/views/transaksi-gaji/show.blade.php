@extends('layouts.app')

@section('title', 'Detail Transaksi Gaji')

@php
    $namaBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
@endphp

@section('content')
<x-app-page>
        <x-page-header title="Detail Transaksi Gaji" subtitle="{{ $transaksiGaji->karyawan->nama_lengkap }} — {{ $namaBulan[$transaksiGaji->bulan] }} {{ $transaksiGaji->tahun }}">
            <x-slot:actions>
                <div class="d-flex gap-2">
                    @if($salarySlipPortalEnabled)
                        @can('publish', $transaksiGaji)
                            <form method="POST" action="{{ route('transaksi-gaji.publish', $transaksiGaji) }}">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-warning" type="submit">
                                    <i class="bi bi-send-check" aria-hidden="true"></i>
                                    Terbitkan Slip
                                </button>
                            </form>
                        @endcan
                    @endif
                    @can('cetak', $transaksiGaji)
                    <button
                        class="btn btn-success"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#cetakSlipGajiModal"
                        data-slip-print-url="{{ route('transaksi-gaji.cetak', $transaksiGaji) }}"
                        data-slip-print-label="slip {{ $transaksiGaji->karyawan->nama_lengkap }}"
                    >
                        <i class="bi bi-printer"></i>
                        Cetak Slip Gaji
                    </button>
                    @endcan
                    @can('update', $transaksiGaji)
                        <a class="btn btn-primary" href="{{ route('transaksi-gaji.edit', $transaksiGaji) }}">
                            <i class="bi bi-pencil"></i>
                            Edit
                        </a>
                    @endcan
                    <a class="btn btn-light" href="{{ route('transaksi-gaji.index') }}">Kembali</a>
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <div class="mb-3">
            @if($transaksiGaji->isPublished())
                <span class="badge bg-success">Diterbitkan {{ $transaksiGaji->published_at->translatedFormat('d F Y H:i') }}</span>
            @else
                <span class="badge bg-secondary">Draf — belum terlihat oleh karyawan</span>
            @endif
        </div>

        <div class="card mb-4 content-narrow">
            <div class="card-header">
                <span>{{ $transaksiGaji->karyawan->nama_lengkap }}</span>
            </div>
            <div class="card-body">
                <dl class="row mb-0 g-3">
                    <dt class="col-sm-4 text-muted">Unit Kerja</dt>
                    <dd class="col-sm-8">{{ $transaksiGaji->karyawan->unitKerja?->nama_unit ?? '-' }}</dd>

                    @if(auth()->user()->isSuperAdmin())
                        <dt class="col-sm-4 text-muted">Koperasi</dt>
                        <dd class="col-sm-8">{{ $transaksiGaji->koperasi?->nama ?? 'Tanpa koperasi' }}</dd>
                    @endif

                    <dt class="col-sm-4 text-muted">Periode</dt>
                    <dd class="col-sm-8">{{ $namaBulan[$transaksiGaji->bulan] }} {{ $transaksiGaji->tahun }}</dd>

                </dl>
            </div>
        </div>

        <div class="content-narrow">
            <x-data-table title="Rincian Perhitungan Gaji">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Komponen</th>
                            <th>Jenis</th>
                            <th>Metode</th>
                            <th class="text-end">Nilai</th>
                            <th class="text-end">Nominal Hasil</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="payroll-component-group">
                            <th colspan="5" scope="rowgroup">Pendapatan</th>
                        </tr>
                        <tr>
                            <td>Gaji Pokok</td>
                            <td><x-badge color="text-bg-primary">Pendapatan</x-badge></td>
                            <td>Nominal Tetap</td>
                            <td class="text-end">Rp {{ number_format($transaksiGaji->gaji_pokok, 0, ',', '.') }}</td>
                            <td class="text-end text-success">+ Rp {{ number_format($transaksiGaji->gaji_pokok, 0, ',', '.') }}</td>
                        </tr>
                        @foreach($transaksiGaji->details->where('jenis_snapshot', 'Tunjangan') as $detail)
                            @include('transaksi-gaji._detail-row', ['detail' => $detail])
                        @endforeach
                        <tr class="payroll-accounting-row payroll-accounting-row--gross">
                            <th colspan="4" scope="row">Total Gaji</th>
                            <td class="text-end">Rp {{ number_format($totalGaji, 0, ',', '.') }}</td>
                        </tr>

                        <tr class="payroll-component-group">
                            <th colspan="5" scope="rowgroup">Potongan</th>
                        </tr>
                        @forelse($transaksiGaji->details->where('jenis_snapshot', 'Potongan') as $detail)
                            @include('transaksi-gaji._detail-row', ['detail' => $detail])
                        @empty
                            <tr>
                                <td colspan="5" class="text-body-secondary">Tidak ada potongan pada transaksi ini.</td>
                            </tr>
                        @endforelse
                        <tr class="payroll-accounting-row payroll-accounting-row--deduction">
                            <th colspan="4" scope="row">Total Potongan</th>
                            <td class="text-end text-danger">- Rp {{ number_format($totalPotongan, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="payroll-accounting-row payroll-accounting-row--net">
                            <th colspan="4" scope="row">Take Home Pay</th>
                            <td class="text-end">Rp {{ number_format($transaksiGaji->gaji_bersih, 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </x-data-table>
        </div>

</x-app-page>

@include('transaksi-gaji._modal-cetak-slip', [
    'penandaTangan' => $penandaTangan,
    'paperLayoutDefault' => $paperLayoutDefault,
])
@endsection
