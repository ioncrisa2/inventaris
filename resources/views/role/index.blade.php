@extends('layouts.app')

@section('title', 'Role & Hak Akses - Sistem Inventaris & Kepegawaian')

@section('content')
@php
    $routePrefix = $routePrefix ?? 'role';
    $isOwnerRoleManager = $isOwnerRoleManager ?? false;
    $showTenant = $isOwnerRoleManager || auth()->user()->isSuperAdmin();
    $canCreate = $isOwnerRoleManager
        || (auth()->user()->can('role.create') && (auth()->user()->isSuperAdmin() || auth()->user()->isAdminPrimer()));
@endphp
<x-app-page>
        <x-page-header
            title="Role & Hak Akses"
            :subtitle="$isOwnerRoleManager
                ? 'Atur permission role lintas koperasi dari control-plane platform.'
                : 'Atur role tambahan per koperasi dan lihat akun yang menggunakan setiap role.'"
        >
            <x-slot:actions>
                @if($canCreate)
                <a class="btn btn-primary" href="{{ route($routePrefix.'.create') }}">
                    <i class="bi bi-shield-plus"></i>
                    Tambah Role
                </a>
                @endif
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        @if(! $showTenant)
        <div class="alert alert-primary app-alert" role="note">
            <i class="bi bi-building-lock" aria-hidden="true"></i>
            <div>
                <strong>Lingkup {{ auth()->user()->koperasi->nama }}.</strong>
                Halaman ini hanya menampilkan role yang terikat ke koperasi Anda.
                Role Admin Primer tetap ditampilkan sebagai role sistem, sedangkan role custom dapat Anda buat dan ubah.
            </div>
        </div>
        @endif

        <div class="alert alert-info app-alert" role="note">
            <i class="bi bi-lock" aria-hidden="true"></i>
            <div>
                <strong>Role sistem dilindungi.</strong>
                @if($isOwnerRoleManager)
                    Nama Super Admin dan Admin Primer tetap dikunci sebagai jangkar identitas, tetapi permission keduanya dapat Anda atur.
                    Identitas pemilik platform sengaja tidak ditampilkan di halaman ini.
                @elseif(auth()->user()->isSuperAdmin())
                    Role Super Admin tidak dapat diubah. Untuk Admin Primer, nama role tetap dikunci tetapi permission dapat Anda atur.
                    Semua role sistem tidak dapat dihapus.
                @else
                    Super Admin dan Admin Primer menjadi jangkar identitas sehingga tidak dapat diubah atau dihapus dari halaman ini.
                    Klik jumlah pengguna untuk mengelola akun pemegang role tersebut.
                @endif
            </div>
        </div>

        <x-data-table :paginator="$roles">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nama Role</th>
                            @if($showTenant)
                            <th>Koperasi</th>
                            @endif
                            <th class="text-end table-col-width-120">Jumlah Permission</th>
                            <th class="text-end table-col-width-120">Jumlah Pengguna</th>
                            <th class="text-nowrap table-col-width-100">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($roles as $role)
                            <tr>
                                <td>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <strong>{{ $role->displayName() }}</strong>
                                        @if($role->isSystem())
                                            <x-badge
                                                color="bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle"
                                                title="Role sistem dikelola oleh aplikasi"
                                            >
                                                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                                Role Sistem
                                            </x-badge>
                                        @endif
                                    </div>
                                </td>
                                @if($showTenant)
                                <td>{{ $role->koperasi?->nama ?? 'Global' }}</td>
                                @endif
                                <td class="text-end">{{ $role->permissions_count }}</td>
                                <td class="text-end">
                                    @if(! $isOwnerRoleManager && auth()->user()->can('pengguna.view'))
                                        <a
                                            class="btn btn-sm btn-link p-0 fw-semibold text-decoration-none"
                                            href="{{ route('pengguna.index', array_filter([
                                                'role_id' => $role->id,
                                                'koperasi_id' => $role->koperasi_id,
                                            ])) }}"
                                            aria-label="Lihat {{ $role->users_count }} pengguna dengan role {{ $role->displayName() }}"
                                            title="Lihat pengguna"
                                        >
                                            {{ $role->users_count }}
                                            <i class="bi bi-arrow-up-right-square ms-1" aria-hidden="true"></i>
                                        </a>
                                    @else
                                        {{ $role->users_count }}
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <div class="table-actions">
                                        @if(
                                            ($isOwnerRoleManager && ! $role->isSystemOwnerRole())
                                            || (
                                                auth()->user()->isSuperAdmin()
                                                && auth()->user()->can('role.update')
                                                && $role->isAdminPrimerRole()
                                            )
                                            || (
                                                auth()->user()->can('role.update')
                                                && ! $role->isSystem()
                                                && (auth()->user()->isSuperAdmin() || (int) $role->koperasi_id === (int) auth()->user()->koperasi_id)
                                            )
                                        )
                                        <a
                                            class="btn btn-sm btn-action btn-action-neutral"
                                            href="{{ route($routePrefix.'.edit', $role) }}"
                                            aria-label="{{ $role->isSystem() ? 'Atur permission' : 'Edit' }} {{ $role->displayName() }}"
                                            title="{{ $role->isSystem() ? 'Atur permission' : 'Edit' }}"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @endif
                                        @if(($isOwnerRoleManager || auth()->user()->isSuperAdmin()) && ! $role->isSystem())
                                        <x-delete-button
                                            :url="route($routePrefix.'.destroy', $role)"
                                            :message="'Yakin ingin menghapus role &quot;'.$role->displayName().'&quot;?'"
                                            :label="'Hapus '.$role->displayName()"
                                        />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-empty-row :colspan="$showTenant ? 5 : 4">Belum ada role terdaftar.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
        </x-data-table>
</x-app-page>
@endsection
