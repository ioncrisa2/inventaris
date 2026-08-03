@extends('layouts.app')

@section('title', 'Manajemen Pengguna - Sistem Inventaris & Kepegawaian')

@section('content')
@php
    $showTenant = auth()->user()->isSuperAdmin();
    $canDelete = auth()->user()->can('pengguna.delete');
    $columnCount = 5 + ($showTenant ? 1 : 0) + ($canDelete ? 1 : 0);
@endphp
<x-app-page>
        <x-page-header
            title="Manajemen Pengguna"
            :subtitle="$showTenant ? 'Kelola akun lintas koperasi dengan konteks tenant yang eksplisit.' : null"
        >
            <x-slot:actions>
                @can('pengguna.create')
                <a class="btn btn-primary" href="{{ route('pengguna.create', request()->only(['koperasi_id', 'role_id'])) }}">
                    <i class="bi bi-person-plus"></i>
                    Tambah Pengguna
                </a>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table :paginator="$users">
            <x-slot:toolbar>
                <x-filter-form
                    :action="route('pengguna.index')"
                    :reset-route="route('pengguna.index')"
                    :has-filters="request()->hasAny(['search', 'role_id', 'koperasi_id'])"
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
                    <x-tenant-filter :koperasis="$koperasis" :selected="request('koperasi_id')" id="pengguna_koperasi_id" />
                    <div class="col-12 col-sm-6 col-lg-auto">
                        <label class="visually-hidden" for="role_id">Role</label>
                        <select class="form-select" id="role_id" name="role_id">
                            <option value="">Semua role</option>
                            @foreach($roles as $r)
                                <option value="{{ $r->id }}" @selected((string) request('role_id') === (string) $r->id)>
                                    {{ $r->displayName() }}{{ auth()->user()->isSuperAdmin() ? ' — '.($r->koperasi?->nama ?? 'Global') : '' }}
                                </option>
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
                            @if($showTenant)
                                <th>Koperasi</th>
                            @endif
                            <th>Email</th>
                            <th>Unit Kerja</th>
                            <th class="table-col-width-120">Role</th>
                            <th class="text-nowrap table-col-width-100">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $data)
                            @php
                                $isProtectedAdminPrimer = $data->isAdminPrimer()
                                    && (int) ($data->koperasi?->admin_primer_users_count ?? 0) <= 1;
                                $canDeleteData = auth()->user()->can('delete', $data)
                                    && ! $isProtectedAdminPrimer
                                    && ! $data->is(auth()->user());
                            @endphp
                            <tr>
                                @can('pengguna.delete')
                                <td class="selection-column">
                                    @if($canDeleteData)
                                        <x-table-checkbox group="pengguna" :value="$data->id" :label="'Pilih '.$data->name" />
                                    @else
                                        <i
                                            class="bi bi-lock text-body-tertiary"
                                            aria-label="{{ $isProtectedAdminPrimer ? 'Admin primer terakhir tidak dapat dihapus' : 'Akun ini tidak dapat dipilih' }}"
                                            title="{{ $isProtectedAdminPrimer ? 'Tambahkan admin primer pengganti sebelum menghapus akun ini.' : 'Akun ini tidak dapat dipilih' }}"
                                        ></i>
                                    @endif
                                </td>
                                @endcan
                                <td><strong>{{ $data->name }}</strong></td>
                                @if($showTenant)
                                    <td>{{ $data->koperasi?->nama ?? 'Global' }}</td>
                                @endif
                                <td>{{ $data->email }}</td>
                                <td>{{ $data->unitKerja?->nama_unit ?? '-' }}</td>
                                <td>
                                    @forelse($data->roles as $role)
                                        <x-badge color="bg-primary">{{ $role->displayName() }}</x-badge>
                                    @empty
                                        <x-badge color="bg-secondary">Belum ada role</x-badge>
                                    @endforelse
                                </td>
                                <td class="text-nowrap">
                                    <div class="table-actions">
                                        @can('update', $data)
                                        <a
                                            class="btn btn-sm btn-action btn-action-neutral"
                                            href="{{ route('pengguna.edit', ['pengguna' => $data] + request()->only(['koperasi_id', 'role_id'])) }}"
                                            aria-label="Edit {{ $data->name }}"
                                            title="Edit"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @endcan
                                        @if($canDeleteData)
                                        <x-delete-button
                                            :url="route('pengguna.destroy', $data)"
                                            :message="'Yakin ingin menghapus pengguna &quot;'.$data->name.'&quot;?'"
                                            :label="'Hapus '.$data->name"
                                        />
                                        @elseif($isProtectedAdminPrimer && auth()->user()->can('pengguna.delete'))
                                            <span
                                                class="btn btn-sm btn-action text-body-tertiary"
                                                aria-label="Admin primer terakhir dilindungi"
                                                title="Tambahkan admin primer pengganti sebelum menghapus akun ini."
                                            >
                                                <i class="bi bi-shield-lock" aria-hidden="true"></i>
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-empty-row :colspan="$columnCount">
                                @if(request()->hasAny(['search', 'role_id', 'koperasi_id']))
                                    Tidak ada pengguna yang cocok dengan filter.
                                    <a href="{{ route('pengguna.index') }}">Hapus filter</a>.
                                @else
                                    Belum ada pengguna terdaftar.
                                @endif
                            </x-empty-row>
                        @endforelse
                    </tbody>
                </table>
        </x-data-table>
</x-app-page>
@endsection
