@extends('layouts.app')

@section('title', 'Role & Hak Akses - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        <x-page-header title="Role & Hak Akses">
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
                                <td><strong>{{ $role->displayName() }}</strong></td>
                                @if(auth()->user()->isSuperAdmin())
                                <td>{{ $role->koperasi?->nama ?? 'Global' }}</td>
                                @endif
                                <td class="text-end">{{ $role->permissions_count }}</td>
                                <td class="text-end">{{ $role->users_count }}</td>
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
