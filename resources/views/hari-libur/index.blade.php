@extends('layouts.app')

@section('title', 'Hari Libur')

@section('content')
    <x-app-page long-footer>
        <x-page-header title="Hari Libur" subtitle="Tanggal libur nasional — dasar hitung Uang Makan &amp; kalender Absensi.">
            <x-slot:actions>
                @can('hari-libur.create')
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#importHariLiburModal">
                        <i class="bi bi-file-earmark-excel"></i>
                        Import Excel
                    </button>
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
                        <x-empty-row :colspan="3">
                            Belum ada data hari libur. Tambah manual atau import dari file Excel.
                            <x-slot:action>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createHariLiburModal">
                                    <i class="bi bi-plus-circle"></i>
                                    Tambah Hari Libur
                                </button>
                            </x-slot:action>
                        </x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </x-data-table>
    </x-app-page>

    @include('hari-libur._modals')
@endsection
