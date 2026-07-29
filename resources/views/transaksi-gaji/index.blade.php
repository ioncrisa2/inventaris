@extends('layouts.app')

@section('title', 'Transaksi Gaji')

@section('content')
<x-app-page long-footer>
        <x-page-header title="Transaksi Gaji" subtitle="Riwayat pembayaran per karyawan.">
            <x-slot:actions>
                <a href="{{ route('transaksi-gaji.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i>
                    Buat Transaksi Gaji
                </a>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table :paginator="$karyawanList">
            <x-slot:toolbar>
                <x-filter-form
                    :action="route('transaksi-gaji.index')"
                    :reset-route="route('transaksi-gaji.index')"
                    :has-filters="request()->hasAny(['search'])"
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
                </x-filter-form>
            </x-slot:toolbar>

            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th>Unit Kerja</th>
                        <th class="text-end table-col-width-170">Jumlah Transaksi</th>
                        <th class="text-end table-col-width-150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($karyawanList as $karyawan)
                    <tr>
                        <td><strong>{{ $karyawan->nama_lengkap }}</strong></td>
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
                    <x-empty-row :colspan="4">Belum ada transaksi gaji.</x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </x-data-table>
</x-app-page>
@endsection
