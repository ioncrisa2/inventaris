@extends('layouts.app')

@section('title', 'Detail Komponen Gaji')

@php
    $metodeLabel = \App\Models\KomponenGaji::METODE_PERHITUNGAN[$komponenGaji->metode_perhitungan]
        ?? $komponenGaji->metode_perhitungan;
    $memakaiDaftarTetap = $komponenGaji->metode_perhitungan === \App\Models\KomponenGaji::METODE_DAFTAR_TETAP;
    $nilaiDiisiSaatTransaksi = in_array(
        $komponenGaji->metode_perhitungan,
        \App\Models\KomponenGaji::METODE_INPUT_TRANSAKSI,
        true,
    );
    $totalRincian = $komponenGaji->rincian->sum(fn ($item) => (float) $item->nominal);
    $penjelasanMetode = match ($komponenGaji->metode_perhitungan) {
        'nominal_tetap' => 'Nominal master digunakan langsung pada transaksi gaji.',
        'nominal_tidak_tetap' => 'Nominal diisi petugas ketika transaksi gaji dibuat.',
        'nominal_tetap_list' => 'Petugas memilih satu atau beberapa rincian tetap ketika transaksi gaji dibuat.',
        'persentase' => 'Persentase dihitung dari gaji pokok.',
        'persentase_pengali' => 'Persentase dihitung dari gaji pokok, lalu dikalikan jumlah pengali pada transaksi.',
        'per_hari' => 'Nominal dikalikan jumlah absensi Hadir dalam periode transaksi gaji.',
        'harian_manual' => 'Nominal dikalikan jumlah hari yang diisi petugas pada transaksi gaji.',
        default => 'Mengikuti aturan metode perhitungan yang dipilih.',
    };
    $deleteBlockedMessage = $jumlahTransaksi > 0
        ? 'Komponen gaji tidak dapat dihapus karena sudah digunakan pada '.$jumlahTransaksi.' transaksi gaji.'
        : null;
@endphp

@section('content')
<x-app-page long-footer>
    <x-page-header
        title="{{ $komponenGaji->nama_komponen }}"
        subtitle="Detail konfigurasi komponen gaji."
    >
        <x-slot:actions>
            <div class="d-flex flex-wrap gap-2">
                @can('update', $komponenGaji)
                    <a class="btn btn-primary" href="{{ route('komponen-gaji.edit', $komponenGaji) }}">
                        <i class="bi bi-pencil" aria-hidden="true"></i>
                        Edit
                    </a>
                @endcan
                @can('delete', $komponenGaji)
                    <button
                        type="button"
                        class="btn btn-outline-danger"
                        data-bs-toggle="modal"
                        data-bs-target="#confirmDeleteModal"
                        data-delete-url="{{ route('komponen-gaji.destroy', $komponenGaji) }}"
                        data-delete-message="Hapus komponen gaji &quot;{{ $komponenGaji->nama_komponen }}&quot;? Tindakan ini tidak dapat dibatalkan."
                        @if($deleteBlockedMessage) data-delete-blocked-message="{{ $deleteBlockedMessage }}" @endif
                    >
                        <i class="bi bi-trash" aria-hidden="true"></i>
                        Hapus
                    </button>
                @endcan
                <a class="btn btn-light" href="{{ route('komponen-gaji.index') }}">Kembali</a>
            </div>
        </x-slot:actions>
    </x-page-header>

    <x-flash-alert />

    <div class="content-narrow">
        <x-section-card title="Ringkasan Komponen" subtitle="Aturan yang digunakan dalam perhitungan transaksi gaji.">
            <x-slot:actions>
                <x-badge :color="$komponenGaji->jenis === 'Tunjangan' ? 'text-bg-success' : 'text-bg-secondary'">
                    {{ $komponenGaji->jenis }}
                </x-badge>
            </x-slot:actions>

            <x-detail-list>
                <x-detail-item label="Nama Komponen" :value="$komponenGaji->nama_komponen" emphasis />
                <x-detail-item label="Metode Perhitungan" :value="$metodeLabel" />

                @if(auth()->user()->isSuperAdmin())
                    <x-detail-item label="Koperasi" :value="$komponenGaji->koperasi?->nama ?? 'Tanpa koperasi'" />
                @endif

                @if($memakaiDaftarTetap)
                    <x-detail-item label="Jumlah Rincian" :value="$komponenGaji->rincian->count().' pilihan'" />
                    <x-detail-item label="Total Nominal" emphasis>
                        Rp {{ number_format($totalRincian, 0, ',', '.') }}
                    </x-detail-item>
                @elseif($nilaiDiisiSaatTransaksi)
                    <x-detail-item label="Nilai">Diisi saat transaksi</x-detail-item>
                @elseif(in_array($komponenGaji->metode_perhitungan, ['persentase', 'persentase_pengali'], true))
                    <x-detail-item label="Nilai Default" emphasis>
                        {{ rtrim(rtrim($komponenGaji->nilai_default, '0'), '.') }}%
                    </x-detail-item>
                @elseif(in_array($komponenGaji->metode_perhitungan, ['per_hari', 'harian_manual'], true))
                    <x-detail-item label="Nilai Default" emphasis>
                        Rp {{ number_format($komponenGaji->nilai_default, 0, ',', '.') }} /hari
                    </x-detail-item>
                @else
                    <x-detail-item label="Nilai Default" emphasis>
                        Rp {{ number_format($komponenGaji->nilai_default, 0, ',', '.') }}
                    </x-detail-item>
                @endif

                <x-detail-item label="Cara Perhitungan" :value="$penjelasanMetode" />
                <x-detail-item label="Penggunaan">
                    @if($jumlahTransaksi > 0)
                        <x-badge color="text-bg-primary">{{ $jumlahTransaksi }} transaksi</x-badge>
                    @else
                        <x-badge color="text-bg-secondary">Belum digunakan</x-badge>
                    @endif
                </x-detail-item>
                <x-detail-item label="Dibuat" :value="$komponenGaji->created_at->translatedFormat('d F Y H:i')" />
                <x-detail-item label="Terakhir Diperbarui" :value="$komponenGaji->updated_at->translatedFormat('d F Y H:i')" />
            </x-detail-list>
        </x-section-card>

        @if($memakaiDaftarTetap)
            <x-data-table
                title="Daftar Nominal Tetap"
                subtitle="Pilihan rincian yang tersedia ketika komponen digunakan dalam transaksi gaji."
                class="mt-4"
            >
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="table-col-width-100">No.</th>
                            <th>Keterangan</th>
                            <th class="text-end table-col-width-200">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($komponenGaji->rincian as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $item->keterangan }}</strong></td>
                                <td class="text-end">Rp {{ number_format($item->nominal, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <x-empty-row :colspan="3">Belum ada rincian nominal tetap.</x-empty-row>
                        @endforelse
                    </tbody>
                    @if($komponenGaji->rincian->isNotEmpty())
                        <tfoot>
                            <tr>
                                <th colspan="2">Total</th>
                                <th class="text-end">Rp {{ number_format($totalRincian, 0, ',', '.') }}</th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </x-data-table>
        @endif
    </div>
</x-app-page>
@endsection
