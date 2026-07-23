@extends('layouts.app')

@section('title', 'Role & Hak Akses - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        <x-page-header title="Role & Hak Akses">
            <x-slot:actions>
                @can('role.create')
                <a class="btn btn-primary" href="{{ route('role.create') }}">
                    <i class="bi bi-shield-plus"></i>
                    Tambah Role
                </a>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table-card :paginator="$roles">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nama Role</th>
                            <th class="text-end table-col-width-120">Jumlah Permission</th>
                            <th class="text-end table-col-width-120">Jumlah Pengguna</th>
                            <th class="text-nowrap table-col-width-100">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($roles as $role)
                            <tr>
                                <td><strong>{{ $role->name }}</strong></td>
                                <td class="text-end">{{ $role->permissions_count }}</td>
                                <td class="text-end">{{ $role->users_count }}</td>
                                <td class="text-nowrap">
                                    <div class="table-actions">
                                        @can('role.update')
                                        <a
                                            class="btn btn-sm btn-action btn-action-neutral"
                                            href="{{ route('role.edit', $role) }}"
                                            aria-label="Edit {{ $role->name }}"
                                            title="Edit"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @endcan
                                        @can('role.delete')
                                        <x-delete-button
                                            :url="route('role.destroy', $role)"
                                            :message="'Yakin ingin menghapus role &quot;'.$role->name.'&quot;?'"
                                            :label="'Hapus '.$role->name"
                                        />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-empty-row :colspan="4">Belum ada role terdaftar.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
        </x-data-table-card>
</x-app-page>
@endsection
