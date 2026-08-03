@extends('layouts.app')

@section('title', 'Transaksi Gaji')

@section('content')
@php
    $showTenant = auth()->user()->isSuperAdmin();
@endphp
<x-app-page long-footer>
        <x-page-header title="Transaksi Gaji" subtitle="Riwayat pembayaran per karyawan.">
            <x-slot:actions>
                @can('cetak', \App\Models\TransaksiGaji::class)
                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#cetakSlipGajiMassalModal"
                    >
                        <i class="bi bi-printer" aria-hidden="true"></i>
                        Cetak Slip Massal
                    </button>
                @endcan
                @can('transaksi-gaji.create')
                    <a href="{{ route('transaksi-gaji.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle" aria-hidden="true"></i>
                        Buat Transaksi Gaji
                    </a>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table :paginator="$karyawanList">
            <x-slot:toolbar>
                <x-filter-form
                    :action="route('transaksi-gaji.index')"
                    :reset-route="route('transaksi-gaji.index')"
                    :has-filters="request()->hasAny(['search', 'koperasi_id'])"
                    submit-label="Cari"
                    submit-icon="bi-search"
                >
                    <div class="col-12 col-sm-auto">
                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="{{ request('search') }}"
                            placeholder="Cari nama karyawan...">
                    </div>
                    <x-tenant-filter :koperasis="$koperasis" :selected="$selectedKoperasiId" id="transaksi_gaji_koperasi_id" />
                </x-filter-form>
            </x-slot:toolbar>

            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        @if($showTenant)
                            <th>Koperasi</th>
                        @endif
                        <th>Unit Kerja</th>
                        <th class="text-end table-col-width-170">Jumlah Transaksi</th>
                        <th class="text-end table-col-width-150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($karyawanList as $karyawan)
                    <tr>
                        <td><strong>{{ $karyawan->nama_lengkap }}</strong></td>
                        @if($showTenant)
                            <td>{{ $karyawan->koperasi?->nama ?? 'Tanpa koperasi' }}</td>
                        @endif
                        <td>{{ $karyawan->unitKerja?->nama_unit ?? '—' }}</td>
                        <td class="text-end">{{ $karyawan->transaksi_gaji_count }}</td>
                        <td class="text-end">
                            <a
                                href="{{ route('transaksi-gaji.karyawan', $karyawan) }}"
                                class="btn btn-sm btn-light"
                                aria-label="Lihat detail transaksi gaji {{ $karyawan->nama_lengkap }}"
                            >
                                <i class="bi bi-eye"></i>
                                Lihat Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <x-empty-row :colspan="$showTenant ? 5 : 4">
                        @if(request()->hasAny(['search', 'koperasi_id']))
                            Tidak ada karyawan yang cocok dengan filter.
                            <a href="{{ route('transaksi-gaji.index') }}">Hapus filter</a>.
                        @else
                            Belum ada transaksi gaji.
                        @endif
                    </x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </x-data-table>
</x-app-page>

@can('cetak', \App\Models\TransaksiGaji::class)
    @include('transaksi-gaji._modal-cetak-slip-massal', [
        'karyawanCetak' => $karyawanCetak,
        'penandaTangan' => $penandaTangan,
        'paperLayoutDefault' => $paperLayoutDefault,
    ])
@endcan
@endsection
