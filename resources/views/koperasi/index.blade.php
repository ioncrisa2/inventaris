@extends('layouts.app')

@section('title', 'Manajemen Koperasi - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        <x-page-header title="Manajemen Koperasi" subtitle="Kelola koperasi pelanggan dan masa aktif langganannya.">
            <x-slot:actions>
                <a class="btn btn-primary" href="{{ route('koperasi.create') }}">
                    <i class="bi bi-building-add"></i>
                    Tambah Koperasi
                </a>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table :paginator="$koperasis">
            <x-slot:toolbar>
                <x-filter-form
                    :action="route('koperasi.index')"
                    :reset-route="route('koperasi.index')"
                    :has-filters="request()->hasAny(['search'])"
                    submit-label="Cari"
                    submit-icon="bi-search"
                >
                    <div class="col-12 col-sm-auto">
                        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Cari nama koperasi...">
                    </div>
                </x-filter-form>
            </x-slot:toolbar>

                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nama Koperasi</th>
                            <th class="table-col-width-120">Status</th>
                            <th class="table-col-width-160">Masa Aktif</th>
                            <th class="text-end table-col-width-120">Jumlah Pengguna</th>
                            <th class="table-col-width-100">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($koperasis as $koperasi)
                            @php
                                $expired = $koperasi->expires_at && $koperasi->expires_at->isPast();
                            @endphp
                            <tr>
                                <td><strong>{{ $koperasi->nama }}</strong></td>
                                <td>
                                    @if(! $koperasi->is_active)
                                        <x-badge color="bg-secondary">Nonaktif</x-badge>
                                    @elseif($expired)
                                        <x-badge color="bg-danger">Lewat Masa Aktif</x-badge>
                                    @else
                                        <x-badge color="bg-success">Aktif</x-badge>
                                    @endif
                                </td>
                                <td>{{ $koperasi->expires_at?->translatedFormat('d F Y') ?? 'Tanpa batas' }}</td>
                                <td class="text-end">{{ $koperasi->users_count }}</td>
                                <td class="text-nowrap">
                                    <div class="table-actions">
                                        <a
                                            class="btn btn-sm btn-action btn-action-neutral"
                                            href="{{ route('koperasi.edit', $koperasi) }}"
                                            aria-label="Edit {{ $koperasi->nama }}"
                                            title="Edit"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-empty-row :colspan="5">Belum ada koperasi terdaftar.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
        </x-data-table>
</x-app-page>
@endsection
