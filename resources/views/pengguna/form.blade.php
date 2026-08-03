@extends('layouts.app')

@section('title', $user->exists ? 'Edit Pengguna' : 'Tambah Pengguna')

@section('content')
@php
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    $unitOptions = $unitKerjas->mapWithKeys(fn ($unitKerja) => [
        $unitKerja->id => $unitKerja->nama_unit.($isSuperAdmin ? ' — '.($unitKerja->koperasi?->nama ?? 'Tanpa koperasi') : ''),
    ]);
    $roleOptions = $roles->mapWithKeys(fn ($role) => [
        $role->id => $role->displayName().($isSuperAdmin ? ' — '.($role->koperasi?->nama ?? 'Global') : ''),
    ]);
    $selectedRoleId = $user->roles->first()?->id ?? request('role_id');
    $selectedRole = $roles->firstWhere('id', (int) $selectedRoleId);
    $isProtectedAdminPrimer = $user->exists
        && $user->isAdminPrimer()
        && (int) ($user->koperasi?->admin_primer_users_count ?? 0) <= 1;
    $subtitle = match (true) {
        $isSuperAdmin && $user->exists => 'Konteks akun: '.($user->koperasi?->nama ?? 'Global'),
        $isSuperAdmin && $selectedRole !== null => 'Konteks akun baru: '.($selectedRole->koperasi?->nama ?? 'Global'),
        default => null,
    };
@endphp
<x-form-page
    :title="$user->exists ? 'Edit Pengguna' : 'Tambah Pengguna'"
    :subtitle="$subtitle"
    :action="$user->exists ? route('pengguna.update', $user) : route('pengguna.store')"
    :method="$user->exists ? 'PUT' : 'POST'"
    :cancel-route="route('pengguna.index', request()->only(['koperasi_id', 'role_id']))"
    :submit-label="$user->exists ? 'Simpan Perubahan' : 'Simpan Pengguna'"
>
    <x-form.section title="Data Pengguna" description="Semua field bertanda bintang wajib diisi." />

    <div class="row g-3">
        <div class="col-md-6">
            <x-form.input name="name" label="Nama" :value="$user->name" required autofocus maxlength="255" autocomplete="name" />
        </div>

        <div class="col-md-6">
            <x-form.input name="email" label="Email" type="email" :value="$user->email" required maxlength="255" autocomplete="email" />
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
                :options="$unitOptions"
                :value="$user->unit_kerja_id"
                placeholder="Tidak ditentukan"
            />
        </div>

        <div class="col-md-6">
            @if($isProtectedAdminPrimer)
                <input type="hidden" name="role_id" value="{{ $selectedRoleId }}">
                <x-form.select
                    name="role_id_display"
                    label="Role"
                    :options="$roleOptions"
                    :value="$selectedRoleId"
                    disabled
                    aria-disabled="true"
                />
            @else
                <x-form.select
                    name="role_id"
                    label="Role"
                    :options="$roleOptions"
                    :value="$selectedRoleId"
                    required
                    placeholder="Pilih role"
                />
            @endif
            <div class="form-text">
                Role menentukan menu dan aksi apa saja yang bisa diakses pengguna ini.
                @if($isProtectedAdminPrimer)
                    Role dikunci karena akun ini adalah admin primer terakhir. Tambahkan admin primer pengganti terlebih dahulu untuk memindahkan role.
                @else
                    Admin primer terakhir tidak dapat dipindahkan ke role atau koperasi lain sebelum admin primer pengganti tersedia.
                @endif
                <a href="{{ route('role.index') }}">Kelola role &amp; hak akses</a>.
            </div>
        </div>
    </div>
</x-form-page>
@endsection
