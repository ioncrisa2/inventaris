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
            <x-data-table title="Rincian Komponen Gaji">
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
                        @if($transaksiGaji->details->isEmpty())
                            <x-empty-row :colspan="5">Tidak ada komponen pada transaksi ini.</x-empty-row>
                        @else
                            @foreach(['Tunjangan', 'Potongan'] as $jenisGrup)
                                @php
                                    $detailGrup = $transaksiGaji->details->where('jenis_snapshot', $jenisGrup);
                                @endphp
                                @if($detailGrup->isNotEmpty())
                                    <tr class="payroll-component-group">
                                        <th colspan="5" scope="rowgroup">{{ $jenisGrup }}</th>
                                    </tr>
                                    @foreach($detailGrup as $detail)
                                        <tr>
                                            <td>{{ $detail->nama_komponen_snapshot }}</td>
                                            <td>
                                                <x-badge :color="$detail->jenis_snapshot === 'Tunjangan' ? 'text-bg-success' : 'text-bg-secondary'">{{ $detail->jenis_snapshot }}</x-badge>
                                            </td>
                                            <td>{{ \App\Models\KomponenGaji::METODE_PERHITUNGAN[$detail->metode_perhitungan_snapshot] ?? $detail->metode_perhitungan_snapshot }}</td>
                                            <td class="text-end">
                                                @if($detail->metode_perhitungan_snapshot === 'persentase')
                                                    {{ rtrim(rtrim($detail->nilai_snapshot, '0'), '.') }}%
                                                @elseif($detail->metode_perhitungan_snapshot === 'per_hari')
                                                    Rp {{ number_format($detail->nilai_snapshot, 0, ',', '.') }} /hari &times; {{ $detail->jumlah_hari_snapshot ?? 0 }} hari
                                                    @if($detail->tanggal_awal_snapshot && $detail->tanggal_akhir_snapshot)
                                                        <span class="text-body-secondary small d-block">{{ $detail->tanggal_awal_snapshot->format('d/m/Y') }} s.d. {{ $detail->tanggal_akhir_snapshot->format('d/m/Y') }}</span>
                                                    @endif
                                                @elseif($detail->metode_perhitungan_snapshot === 'harian_sehari')
                                                    Rp {{ number_format($detail->nilai_snapshot, 0, ',', '.') }} /hari
                                                    @if($detail->tanggal_awal_snapshot)
                                                        <span class="text-body-secondary small d-block">{{ $detail->tanggal_awal_snapshot->format('d/m/Y') }}</span>
                                                    @endif
                                                @elseif($detail->metode_perhitungan_snapshot === 'harian_manual')
                                                    Rp {{ number_format($detail->nilai_snapshot, 0, ',', '.') }} /hari &times; {{ $detail->jumlah_hari_snapshot ?? 0 }} hari
                                                @else
                                                    Rp {{ number_format($detail->nilai_snapshot, 0, ',', '.') }}
                                                @endif
                                            </td>
                                            <td class="text-end {{ $detail->jenis_snapshot === 'Tunjangan' ? 'text-success' : 'text-danger' }}">
                                                {{ $detail->jenis_snapshot === 'Tunjangan' ? '+' : '-' }} Rp {{ number_format($detail->nominal_hasil, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </x-data-table>

            <dl class="payroll-summary">
                <div>
                    <dt>Gaji Pokok</dt>
                    <dd>Rp {{ number_format($transaksiGaji->gaji_pokok, 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt>Total Tunjangan</dt>
                    <dd class="text-success">+ Rp {{ number_format($totalTunjangan, 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt>Total Potongan</dt>
                    <dd class="text-danger">- Rp {{ number_format($totalPotongan, 0, ',', '.') }}</dd>
                </div>
                <div class="payroll-summary__total">
                    <dt>Gaji Bersih</dt>
                    <dd>Rp {{ number_format($transaksiGaji->gaji_bersih, 0, ',', '.') }}</dd>
                </div>
            </dl>
        </div>

</x-app-page>

@include('transaksi-gaji._modal-cetak-slip', [
    'penandaTangan' => $penandaTangan,
    'paperLayoutDefault' => $paperLayoutDefault,
])
@endsection
