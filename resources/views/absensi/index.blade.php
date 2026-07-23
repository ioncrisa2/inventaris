@extends('layouts.app')

@section('title', 'Absensi - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
    <x-page-header title="Absensi" subtitle="Pilih karyawan untuk melihat dan mengisi kalender absensinya." />

    <x-section-card title="Daftar Karyawan" subtitle="Buka profil karyawan untuk mengelola kalender absensi." flush>
        <x-slot:actions>
            <x-filter-form
                :action="route('absensi.index')"
                :reset-route="route('absensi.index')"
                :has-filters="request()->hasAny(['search'])"
            >
                <div class="col-12 col-sm-auto filter-form__search">
                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="{{ request('search') }}"
                        placeholder="Cari NIK, nama, jabatan…">
                </div>
            </x-filter-form>
        </x-slot:actions>

        <div class="list-group list-group-flush">
            @forelse($karyawans as $karyawan)
            <a
                href="{{ route('absensi.show', $karyawan) }}"
                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3 py-3">
                <div>
                    <div class="fw-bold">{{ $karyawan->nama_lengkap }}</div>
                    <div class="text-body-secondary small">
                        {{ $karyawan->nik }} &middot; {{ $karyawan->jabatan }} &middot;
                        {{ $karyawan->unitKerja?->nama_unit ?? 'Belum ditentukan' }}
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <x-badge :color="\App\Models\Karyawan::STATUS_COLORS[$karyawan->status_karyawan] ?? 'bg-secondary'">{{ $karyawan->status_karyawan }}</x-badge>
                    <i class="bi bi-chevron-right text-body-secondary"></i>
                </div>
            </a>
            @empty
            <x-empty-state icon="bi-person-x" title="Karyawan tidak ditemukan">
                @if(request()->hasAny(['search']))
                    Tidak ada karyawan yang cocok. <a href="{{ route('absensi.index') }}">Hapus pencarian</a>.
                @else
                    Data karyawan belum tersedia.
                @endif
            </x-empty-state>
            @endforelse
        </div>
    </x-section-card>
</x-app-page>
@endsection
