@extends('layouts.app')

@section('title', 'Absensi - Sistem Inventaris & Kepegawaian')

@section('content')
@php
    $showTenant = auth()->user()->isSuperAdmin();
@endphp
<x-app-page>
    <x-page-header title="Absensi" subtitle="Pilih karyawan untuk melihat dan mengisi kalender absensinya." />

    <x-data-table :paginator="$karyawans">
        <x-slot:toolbar>
            <x-filter-form
                :action="route('absensi.index')"
                :reset-route="route('absensi.index')"
                :has-filters="request()->hasAny(['search', 'koperasi_id'])"
            >
                <div class="col-12 col-sm-auto filter-form__search">
                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="{{ request('search') }}"
                        placeholder="Cari NIK, nama, jabatan…">
                </div>
                <x-tenant-filter :koperasis="$koperasis" :selected="$selectedKoperasiId" id="absensi_koperasi_id" />
            </x-filter-form>
        </x-slot:toolbar>

        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="table-col-width-120">NIK</th>
                    <th>Nama Lengkap</th>
                    @if($showTenant)
                        <th>Koperasi</th>
                    @endif
                    <th>Unit Kerja</th>
                    <th>Jabatan</th>
                    <th class="table-col-width-120">Status</th>
                    <th class="text-nowrap table-col-width-100">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($karyawans as $karyawan)
                    <tr>
                        <td><strong>{{ $karyawan->nik }}</strong></td>
                        <td>{{ $karyawan->nama_lengkap }}</td>
                        @if($showTenant)
                            <td>{{ $karyawan->koperasi?->nama ?? 'Tanpa koperasi' }}</td>
                        @endif
                        <td>{{ $karyawan->unitKerja?->nama_unit ?? 'Belum ditentukan' }}</td>
                        <td>{{ $karyawan->jabatan }}</td>
                        <td><x-badge :color="\App\Models\Karyawan::STATUS_COLORS[$karyawan->status_karyawan] ?? 'bg-secondary'">{{ $karyawan->status_karyawan }}</x-badge></td>
                        <td class="text-nowrap">
                            <div class="table-actions">
                                <a
                                    class="btn btn-sm btn-action btn-action-neutral"
                                    href="{{ route('absensi.show', $karyawan) }}"
                                    aria-label="Detail {{ $karyawan->nama_lengkap }}"
                                    title="Detail"
                                >
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-empty-row :colspan="$showTenant ? 7 : 6">
                        @if(request()->hasAny(['search', 'koperasi_id']))
                            Tidak ada karyawan yang cocok dengan filter.
                            <a href="{{ route('absensi.index') }}">Hapus filter</a>.
                        @else
                            Data karyawan belum tersedia.
                        @endif
                    </x-empty-row>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
</x-app-page>
@endsection
