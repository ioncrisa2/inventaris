@extends('layouts.app')

@section('title', 'Role & Hak Akses - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        <x-page-header
            title="Role & Hak Akses"
            subtitle="Atur role tambahan per koperasi dan lihat akun yang menggunakan setiap role."
        >
            <x-slot:actions>
                @if(auth()->user()->isSuperAdmin())
                <a class="btn btn-primary" href="{{ route('role.create') }}">
                    <i class="bi bi-shield-plus"></i>
                    Tambah Role
                </a>
                @endif
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <div class="alert alert-info app-alert" role="note">
            <i class="bi bi-lock" aria-hidden="true"></i>
            <div>
                <strong>Role sistem dilindungi.</strong>
                Super Admin dan Admin Primer menjadi jangkar identitas sehingga tidak dapat diubah atau dihapus dari halaman ini.
                Klik jumlah pengguna untuk mengelola akun pemegang role tersebut.
            </div>
        </div>

        <x-data-table :paginator="$roles">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nama Role</th>
                            @if(auth()->user()->isSuperAdmin())
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
                                @if(auth()->user()->isSuperAdmin())
                                <td>{{ $role->koperasi?->nama ?? 'Global' }}</td>
                                @endif
                                <td class="text-end">{{ $role->permissions_count }}</td>
                                <td class="text-end">
                                    @can('pengguna.view')
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
                                    @endcan
                                </td>
                                <td class="text-nowrap">
                                    <div class="table-actions">
                                        @if(auth()->user()->can('role.update') && ! $role->isSystem())
                                        <a
                                            class="btn btn-sm btn-action btn-action-neutral"
                                            href="{{ route('role.edit', $role) }}"
                                            aria-label="Edit {{ $role->displayName() }}"
                                            title="Edit"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @endif
                                        @if(auth()->user()->isSuperAdmin() && ! $role->isSystem())
                                        <x-delete-button
                                            :url="route('role.destroy', $role)"
                                            :message="'Yakin ingin menghapus role &quot;'.$role->displayName().'&quot;?'"
                                            :label="'Hapus '.$role->displayName()"
                                        />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-empty-row :colspan="auth()->user()->isSuperAdmin() ? 5 : 4">Belum ada role terdaftar.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
        </x-data-table>
</x-app-page>
@endsection
