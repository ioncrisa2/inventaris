@extends('layouts.app')

@section('title', 'Transaksi Gaji')

@php
    $namaBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
@endphp

@section('content')
<x-app-page long-footer>
        <x-page-header title="Transaksi Gaji" subtitle="Riwayat pembayaran per karyawan dan periode.">
            <x-slot:actions>
                <a href="{{ route('transaksi-gaji.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i>
                    Buat Transaksi Gaji
                </a>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table-card :paginator="$transaksiGaji">
            <x-slot:toolbar>
                <x-filter-form
                    :action="route('transaksi-gaji.index')"
                    :reset-route="route('transaksi-gaji.index')"
                    :has-filters="request()->hasAny(['bulan', 'search'])"
                    submit-label="Cari"
                    submit-icon="bi-search"
                >
                    <div class="col-12 col-sm-auto">
                    <select name="bulan" class="form-select" data-submit-on-change>
                        <option value="">Semua Bulan</option>
                        @foreach($namaBulan as $angka => $nama)
                        <option value="{{ $angka }}" @selected((string) request('bulan') === (string) $angka)>{{ $nama }}</option>
                        @endforeach
                    </select>
                    </div>
                    <div class="col-12 col-sm-auto">
                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="{{ request('search') }}"
                        placeholder="Cari nama karyawan...">
                    </div>
                </x-filter-form>
            </x-slot:toolbar>

            <x-slot:bulkActions>
                @can('transaksi-gaji.delete')
                    <x-bulk-action-bar
                        id="transaksi-gaji"
                        noun="transaksi gaji"
                        :delete-action="route('transaksi-gaji.bulk-destroy')"
                        delete-message="Transaksi gaji terpilih beserta rincian perhitungannya akan dihapus permanen." />
                @endcan
            </x-slot:bulkActions>

                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            @can('transaksi-gaji.delete')
                            <th class="selection-column">
                                <x-table-checkbox group="transaksi-gaji" label="Pilih semua transaksi gaji di halaman ini" select-all />
                            </th>
                            @endcan
                            <th>Karyawan</th>
                            <th class="table-col-width-120">Periode</th>
                            <th class="text-end table-col-width-150">Gaji Pokok</th>
                            <th class="text-end table-col-width-150">Gaji Bersih</th>
                            <th class="table-col-width-170">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transaksiGaji as $data)
                        <tr>
                            @can('transaksi-gaji.delete')
                            <td class="selection-column">
                                <x-table-checkbox group="transaksi-gaji" :value="$data->id" :label="'Pilih transaksi gaji '.$data->karyawan->nama_lengkap" />
                            </td>
                            @endcan
                            <td><strong>{{ $data->karyawan->nama_lengkap }}</strong></td>
                            <td>{{ $namaBulan[$data->bulan] }} {{ $data->tahun }}</td>
                            <td class="text-end">Rp {{ number_format($data->gaji_pokok, 0, ',', '.') }}</td>
                            <td class="text-end fw-bold">Rp {{ number_format($data->gaji_bersih, 0, ',', '.') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a
                                        href="{{ route('transaksi-gaji.show', $data->id) }}"
                                        class="btn btn-sm btn-action btn-action-neutral"
                                        title="Lihat"
                                        aria-label="Lihat transaksi gaji {{ $data->karyawan->nama_lengkap }}">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a
                                        href="{{ route('transaksi-gaji.cetak', $data->id) }}"
                                        class="btn btn-sm btn-action btn-action-neutral"
                                        title="Cetak Slip Gaji"
                                        aria-label="Cetak slip gaji {{ $data->karyawan->nama_lengkap }}"
                                        target="_blank">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <a
                                        href="{{ route('transaksi-gaji.edit', $data->id) }}"
                                        class="btn btn-sm btn-action btn-action-neutral"
                                        title="Edit"
                                        aria-label="Edit transaksi gaji {{ $data->karyawan->nama_lengkap }}">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <x-delete-button
                                        :url="route('transaksi-gaji.destroy', $data->id)"
                                        :message="'Yakin ingin menghapus transaksi gaji '.$data->karyawan->nama_lengkap.' periode '.$namaBulan[$data->bulan].' '.$data->tahun.'?'"
                                        :label="'Hapus transaksi gaji '.$data->karyawan->nama_lengkap"
                                    />
                                </div>
                            </td>
                        </tr>
                        @empty
                        <x-empty-row :colspan="auth()->user()->can('transaksi-gaji.delete') ? 6 : 5">Belum ada transaksi gaji.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
        </x-data-table-card>
</x-app-page>
@endsection
