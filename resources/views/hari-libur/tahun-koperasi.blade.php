@extends('layouts.app')

@section('title', 'Koperasi Primer — Hari Libur '.$tahun)

@section('content')
    <x-app-page long-footer>
        <x-page-header
            title="Hari Libur {{ $tahun }}"
            subtitle="Pilih koperasi primer untuk melihat daftar hari liburnya."
        >
            <x-slot:actions>
                <a href="{{ route('hari-libur.index') }}" class="btn btn-light">
                    <i class="bi bi-arrow-left"></i>
                    Kembali ke Tahun
                </a>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table
            title="Daftar Koperasi Primer"
            subtitle="{{ $koperasis->count() }} koperasi tersedia. Jumlah hari libur dihitung khusus untuk tahun {{ $tahun }}."
        >
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Koperasi Primer</th>
                        <th class="table-col-width-150">Status</th>
                        <th class="table-col-width-180">Jumlah Hari Libur</th>
                        <th class="table-col-width-180">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($koperasis as $koperasi)
                        <tr>
                            <td><strong>{{ $koperasi->nama }}</strong></td>
                            <td>
                                @if($koperasi->is_active)
                                    <span class="badge text-bg-success">Aktif</span>
                                @else
                                    <span class="badge text-bg-secondary">Nonaktif</span>
                                @endif
                            </td>
                            <td>{{ $koperasi->jumlah_hari_libur }} hari libur</td>
                            <td>
                                <a
                                    href="{{ route('hari-libur.koperasi', ['tahun' => $tahun, 'koperasi' => $koperasi]) }}"
                                    class="btn btn-sm btn-light"
                                    aria-label="Lihat hari libur {{ $koperasi->nama }} tahun {{ $tahun }}"
                                >
                                    <i class="bi bi-eye"></i>
                                    Lihat Hari Libur
                                </a>
                            </td>
                        </tr>
                    @empty
                        <x-empty-row :colspan="4">
                            Belum ada koperasi primer yang dapat dipilih.
                            <x-slot:action>
                                <a href="{{ route('koperasi.create') }}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-plus-circle"></i>
                                    Tambah Koperasi
                                </a>
                            </x-slot:action>
                        </x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </x-data-table>
    </x-app-page>
@endsection
