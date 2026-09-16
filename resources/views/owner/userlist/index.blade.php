@extends('layouts.app')

@section('title', 'User List - System Owner')

@section('content')
<x-app-page>
    <x-page-header
        title="User List"
        subtitle="Daftar baca-saja seluruh akun pengguna yang terdaftar pada koperasi di platform."
    />

    <x-data-table :paginator="$users" title="Akun Pengguna" subtitle="Gunakan filter untuk meninjau akun berdasarkan koperasi atau role.">
        <x-slot:toolbar>
            <x-filter-form
                :action="route('owner.userlist.index')"
                :reset-route="route('owner.userlist.index')"
                :has-filters="request()->hasAny(['search', 'koperasi_id', 'role_id'])"
            >
                <div class="col-12 col-md-auto">
                    <label class="visually-hidden" for="userlist_search">Cari pengguna</label>
                    <input class="form-control" id="userlist_search" name="search" type="search"
                        value="{{ request('search') }}" placeholder="Cari nama atau email…">
                </div>
                <div class="col-12 col-sm-6 col-md-auto">
                    <label class="visually-hidden" for="userlist_koperasi">Koperasi</label>
                    <select class="form-select" id="userlist_koperasi" name="koperasi_id">
                        <option value="">Semua koperasi</option>
                        @foreach($koperasis as $koperasi)
                            <option value="{{ $koperasi->id }}" @selected((string) request('koperasi_id') === (string) $koperasi->id)>{{ $koperasi->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-auto">
                    <label class="visually-hidden" for="userlist_role">Role</label>
                    <select class="form-select" id="userlist_role" name="role_id">
                        <option value="">Semua role</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) request('role_id') === (string) $role->id)>
                                {{ $role->displayName() }} — {{ $role->koperasi?->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </x-filter-form>
        </x-slot:toolbar>

        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Nama</th>
                    <th scope="col">Koperasi</th>
                    <th scope="col">Email</th>
                    <th scope="col">Unit Kerja</th>
                    <th scope="col">Role</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td data-label="Nama"><strong>{{ $user->name }}</strong></td>
                        <td data-label="Koperasi">{{ $user->koperasi?->nama ?? '-' }}</td>
                        <td data-label="Email">{{ $user->email }}</td>
                        <td data-label="Unit Kerja">{{ $user->unitKerja?->nama_unit ?? '-' }}</td>
                        <td data-label="Role">
                            @forelse($user->roles as $role)
                                <x-badge color="bg-primary">{{ $role->displayName() }}</x-badge>
                            @empty
                                <x-badge color="bg-secondary">Belum ada role</x-badge>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <x-empty-row :colspan="5">
                        @if(request()->hasAny(['search', 'koperasi_id', 'role_id']))
                            Tidak ada pengguna yang cocok dengan filter.
                            <a href="{{ route('owner.userlist.index') }}">Hapus filter</a>.
                        @else
                            Belum ada akun pengguna dari koperasi yang terdaftar.
                        @endif
                    </x-empty-row>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
</x-app-page>
@endsection
