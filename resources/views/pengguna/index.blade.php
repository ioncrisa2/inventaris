@extends('layouts.app')

@section('title', 'Manajemen Pengguna - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        <x-page-header title="Manajemen Pengguna">
            <x-slot:actions>
                @can('pengguna.create')
                <a class="btn btn-primary" href="{{ route('pengguna.create') }}">
                    <i class="bi bi-person-plus"></i>
                    Tambah Pengguna
                </a>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table-card :paginator="$users">
            <x-slot:toolbar>
                <x-filter-form
                    :action="route('pengguna.index')"
                    :reset-route="route('pengguna.index')"
                    :has-filters="request()->hasAny(['search', 'role'])"
                >
                    <div class="col-12 col-lg-auto">
                        <label class="visually-hidden" for="search">Cari pengguna</label>
                        <input
                            class="form-control"
                            id="search"
                            name="search"
                            type="search"
                            value="{{ request('search') }}"
                            placeholder="Cari nama atau email…"
                        >
                    </div>
                    <div class="col-12 col-sm-6 col-lg-auto">
                        <label class="visually-hidden" for="role">Role</label>
                        <select class="form-select" id="role" name="role">
                            <option value="">Semua role</option>
                            @foreach($roles as $r)
                                <option value="{{ $r->name }}" @selected(request('role') === $r->name)>{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </x-filter-form>
            </x-slot:toolbar>

            <x-slot:bulkActions>
                @can('pengguna.delete')
                    <x-bulk-action-bar
                        id="pengguna"
                        noun="pengguna"
                        :delete-action="route('pengguna.bulk-destroy')"
                        delete-message="Akun pengguna terpilih akan dihapus dan tidak dapat digunakan untuk masuk kembali." />
                @endcan
            </x-slot:bulkActions>

                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            @can('pengguna.delete')
                            <th class="selection-column">
                                <x-table-checkbox group="pengguna" label="Pilih semua pengguna yang dapat dihapus di halaman ini" select-all />
                            </th>
                            @endcan
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Unit Kerja</th>
                            <th class="table-col-width-120">Role</th>
                            <th class="text-nowrap table-col-width-100">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $data)
                            <tr>
                                @can('pengguna.delete')
                                <td class="selection-column">
                                    @unless($data->is(auth()->user()))
                                        <x-table-checkbox group="pengguna" :value="$data->id" :label="'Pilih '.$data->name" />
                                    @else
                                        <i class="bi bi-lock text-body-tertiary" aria-label="Akun aktif tidak dapat dipilih"></i>
                                    @endunless
                                </td>
                                @endcan
                                <td><strong>{{ $data->name }}</strong></td>
                                <td>{{ $data->email }}</td>
                                <td>{{ $data->unitKerja?->nama_unit ?? '-' }}</td>
                                <td>
                                    @forelse($data->roles as $role)
                                        <x-badge color="bg-primary">{{ $role->name }}</x-badge>
                                    @empty
                                        <x-badge color="bg-secondary">Belum ada role</x-badge>
                                    @endforelse
                                </td>
                                <td class="text-nowrap">
                                    <div class="table-actions">
                                        @can('pengguna.update')
                                        <a
                                            class="btn btn-sm btn-action btn-action-neutral"
                                            href="{{ route('pengguna.edit', $data) }}"
                                            aria-label="Edit {{ $data->name }}"
                                            title="Edit"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @endcan
                                        @can('pengguna.delete')
                                        @unless($data->is(auth()->user()))
                                        <x-delete-button
                                            :url="route('pengguna.destroy', $data)"
                                            :message="'Yakin ingin menghapus pengguna &quot;'.$data->name.'&quot;?'"
                                            :label="'Hapus '.$data->name"
                                        />
                                        @endunless
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-empty-row :colspan="auth()->user()->can('pengguna.delete') ? 6 : 5">
                                @if(request()->hasAny(['search', 'role']))
                                    Tidak ada pengguna yang cocok dengan filter.
                                    <a href="{{ route('pengguna.index') }}">Hapus filter</a>.
                                @else
                                    Belum ada pengguna terdaftar.
                                @endif
                            </x-empty-row>
                        @endforelse
                    </tbody>
                </table>
        </x-data-table-card>
</x-app-page>
@endsection
