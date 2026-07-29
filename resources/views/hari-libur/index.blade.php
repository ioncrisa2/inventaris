@extends('layouts.app')

@section('title', 'Hari Libur')

@section('content')
    <x-app-page long-footer>
        <x-page-header title="Hari Libur" subtitle="Tanggal libur nasional — dasar hitung Uang Makan &amp; kalender Absensi.">
            <x-slot:actions>
                @can('hari-libur.create')
                    <a href="{{ route('hari-libur.sinkronisasi.create') }}" class="btn btn-light">
                        <i class="bi bi-cloud-download"></i>
                        Sinkronkan Tanggal Libur
                    </a>
                @endcan
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createHariLiburModal">
                    <i class="bi bi-plus-circle"></i>
                    Tambah Hari Libur
                </button>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table title="Daftar Tahun">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="table-col-width-150">Tahun</th>
                        <th>Jumlah Hari Libur</th>
                        <th class="table-col-width-150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tahunList as $data)
                        <tr>
                            <td><strong>{{ $data['tahun'] }}</strong></td>
                            <td>{{ $data['jumlah'] }} hari libur</td>
                            <td>
                                <a
                                    href="{{ route('hari-libur.tahun', ['tahun' => $data['tahun']]) }}"
                                    class="btn btn-sm btn-light"
                                    aria-label="Lihat detail tahun {{ $data['tahun'] }}"
                                >
                                    <i class="bi bi-eye"></i>
                                    Lihat Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <x-empty-row :colspan="3">Belum ada data hari libur. Klik "Sinkronkan Tanggal Libur" atau "Tambah Hari Libur" untuk mulai mengisi.</x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </x-data-table>
    </x-app-page>

    @include('hari-libur._modals')
@endsection
