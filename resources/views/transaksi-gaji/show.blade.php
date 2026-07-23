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
                    <a class="btn btn-success" href="{{ route('transaksi-gaji.cetak', $transaksiGaji) }}" target="_blank">
                        <i class="bi bi-printer"></i>
                        Cetak Slip Gaji
                    </a>
                    <a class="btn btn-primary" href="{{ route('transaksi-gaji.edit', $transaksiGaji) }}">
                        <i class="bi bi-pencil"></i>
                        Edit
                    </a>
                    <a class="btn btn-light" href="{{ route('transaksi-gaji.index') }}">Kembali</a>
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <div class="card mb-4 content-narrow">
            <div class="card-header">
                <span>{{ $transaksiGaji->karyawan->nama_lengkap }}</span>
            </div>
            <div class="card-body">
                <dl class="row mb-0 g-3">
                    <dt class="col-sm-4 text-muted">Unit Kerja</dt>
                    <dd class="col-sm-8">{{ $transaksiGaji->karyawan->unitKerja?->nama_unit ?? '-' }}</dd>

                    <dt class="col-sm-4 text-muted">Periode</dt>
                    <dd class="col-sm-8">{{ $namaBulan[$transaksiGaji->bulan] }} {{ $transaksiGaji->tahun }}</dd>

                </dl>
            </div>
        </div>

        <div class="card content-narrow">
            <div class="card-header">Rincian Komponen Gaji</div>
            <div class="table-responsive">
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
                                                @elseif($detail->metode_perhitungan_snapshot === 'per_kehadiran')
                                                    Rp {{ number_format($detail->nilai_snapshot, 0, ',', '.') }} /hari &times; {{ $detail->jumlah_hadir_snapshot ?? 0 }} hari hadir
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
            </div>

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
@endsection
