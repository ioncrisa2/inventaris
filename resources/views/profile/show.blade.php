@extends('layouts.app')

@section('title', 'Profil - Sistem Inventaris & Kepegawaian')

@php
    $namaRole = $user->getRoleNames()->join(', ') ?: 'Belum ada role';
@endphp

@section('content')
<x-app-page>
        <x-page-header title="Profil">
            <x-slot:actions>
                <a class="btn btn-light" href="{{ route('dashboard') }}">Kembali ke Dashboard</a>
            </x-slot:actions>
        </x-page-header>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="profile-avatar" aria-hidden="true">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <div class="min-w-0">
                                <h2 class="h5 fw-bold mb-1 text-break">{{ $user->name }}</h2>
                                <div class="text-muted">{{ $namaRole }}</div>
                                <span class="badge bg-success mt-2">Aktif</span>
                            </div>
                        </div>

                        <dl class="mb-0">
                            <div class="profile-meta-item">
                                <dt class="profile-meta-label">Email</dt>
                                <dd class="profile-meta-value text-break">{{ $user->email }}</dd>
                            </div>
                            <div class="profile-meta-item">
                                <dt class="profile-meta-label">Role</dt>
                                <dd class="profile-meta-value">{{ $namaRole }}</dd>
                            </div>
                            <div class="profile-meta-item">
                                <dt class="profile-meta-label">Unit Kerja</dt>
                                <dd class="profile-meta-value">{{ $user->unitKerja?->nama_unit ?? 'Tidak terikat unit kerja' }}</dd>
                            </div>
                            <div class="profile-meta-item">
                                <dt class="profile-meta-label">Terdaftar Sejak</dt>
                                <dd class="profile-meta-value">{{ $user->created_at->translatedFormat('d F Y') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">Informasi Akun</div>
                    <div class="card-body">
                        <x-flash-alert success="profile_success" :error="null" />

                        <form method="POST" action="{{ route('profile.update') }}">
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="name">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input
                                        class="form-control @error('name', 'updateProfile') is-invalid @enderror"
                                        id="name"
                                        name="name"
                                        type="text"
                                        value="{{ old('name', $user->name) }}"
                                        maxlength="255"
                                        autocomplete="name"
                                        required
                                    >
                                    @error('name', 'updateProfile') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
                                    <input
                                        class="form-control @error('email', 'updateProfile') is-invalid @enderror"
                                        id="email"
                                        name="email"
                                        type="email"
                                        value="{{ old('email', $user->email) }}"
                                        maxlength="255"
                                        autocomplete="email"
                                        required
                                    >
                                    @error('email', 'updateProfile') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="role">Role</label>
                                    <input class="form-control" id="role" type="text" value="{{ $namaRole }}" readonly>
                                    <div class="form-text">Role hanya dapat diubah melalui administrasi sistem.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="unit_kerja_id">Unit Kerja</label>
                                    <select
                                        class="form-select @error('unit_kerja_id', 'updateProfile') is-invalid @enderror"
                                        id="unit_kerja_id"
                                        name="unit_kerja_id"
                                    >
                                        <option value="">Tidak terikat unit kerja</option>
                                        @foreach($unitKerjas as $unit)
                                            <option value="{{ $unit->id }}" @selected((string) old('unit_kerja_id', $user->unit_kerja_id) === (string) $unit->id)>
                                                {{ $unit->nama_unit }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('unit_kerja_id', 'updateProfile') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-save"></i>
                                    Simpan Informasi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card" id="keamanan">
                    <div class="card-header">Keamanan</div>
                    <div class="card-body">
                        <x-flash-alert success="password_success" :error="null" />

                        <form method="POST" action="{{ route('profile.password.update') }}">
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="current_password">Password Saat Ini <span class="text-danger">*</span></label>
                                    <input
                                        class="form-control @error('current_password', 'updatePassword') is-invalid @enderror"
                                        id="current_password"
                                        name="current_password"
                                        type="password"
                                        autocomplete="current-password"
                                        required
                                    >
                                    @error('current_password', 'updatePassword') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="password">Password Baru <span class="text-danger">*</span></label>
                                    <input
                                        class="form-control @error('password', 'updatePassword') is-invalid @enderror"
                                        id="password"
                                        name="password"
                                        type="password"
                                        autocomplete="new-password"
                                        required
                                    >
                                    @error('password', 'updatePassword') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="password_confirmation">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                                    <input
                                        class="form-control"
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        type="password"
                                        autocomplete="new-password"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button class="btn btn-outline-primary" type="submit">
                                    <i class="bi bi-shield-check"></i>
                                    Perbarui Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

</x-app-page>
@endsection
