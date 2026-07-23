@extends('layouts.app')

@section('title', $user->exists ? 'Edit Pengguna' : 'Tambah Pengguna')

@section('content')
<x-form-page
    :title="$user->exists ? 'Edit Pengguna' : 'Tambah Pengguna'"
    :action="$user->exists ? route('pengguna.update', $user) : route('pengguna.store')"
    :method="$user->exists ? 'PUT' : 'POST'"
    :cancel-route="route('pengguna.index')"
    :submit-label="$user->exists ? 'Simpan Perubahan' : 'Simpan Pengguna'"
>
    <x-form.section title="Data Pengguna" description="Semua field bertanda bintang wajib diisi." />

    <div class="row g-3">
        <div class="col-md-6">
            <x-form.input name="name" label="Nama" :value="$user->name" required autofocus maxlength="255" />
        </div>

        <div class="col-md-6">
            <x-form.input name="email" label="Email" type="email" :value="$user->email" required maxlength="255" />
        </div>

        <div class="col-md-6">
            <x-form.input
                name="password"
                label="Password"
                type="password"
                autocomplete="new-password"
                :required="! $user->exists"
                :help="$user->exists ? 'Kosongkan jika tidak ingin mengubah password.' : null"
            />
        </div>

        <div class="col-md-6">
            <x-form.select
                name="unit_kerja_id"
                label="Unit Kerja"
                :options="$unitKerjas->pluck('nama_unit', 'id')"
                :value="$user->unit_kerja_id"
                placeholder="Tidak ditentukan"
            />
        </div>

        <div class="col-md-6">
            <x-form.select
                name="role"
                label="Role"
                :options="$roles->pluck('name', 'name')"
                :value="$user->roles->first()?->name"
                required
                placeholder="Pilih role"
            />
            <div class="form-text">
                Role menentukan menu dan aksi apa saja yang bisa diakses pengguna ini.
                <a href="{{ route('role.index') }}">Kelola role &amp; hak akses</a>.
            </div>
        </div>
    </div>
</x-form-page>
@endsection
