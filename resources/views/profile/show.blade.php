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

                        <form id="profileInformationForm" method="POST" action="{{ route('profile.update') }}">
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
                                <button
                                    class="btn btn-primary"
                                    type="button"
                                    data-bs-toggle="modal"
                                    data-bs-target="#confirmProfileUpdateModal"
                                >
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
                                    <x-form.input
                                        name="current_password"
                                        label="Password Saat Ini"
                                        type="password"
                                        autocomplete="current-password"
                                        error-bag="updatePassword"
                                        required
                                    />
                                </div>

                                <div class="col-md-6">
                                    <x-form.input
                                        name="password"
                                        label="Password Baru"
                                        type="password"
                                        autocomplete="new-password"
                                        error-bag="updatePassword"
                                        required
                                    />
                                </div>

                                <div class="col-md-6">
                                    <x-form.input
                                        name="password_confirmation"
                                        label="Konfirmasi Password Baru"
                                        type="password"
                                        autocomplete="new-password"
                                        required
                                    />
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

<div
    class="modal fade"
    id="confirmProfileUpdateModal"
    tabindex="-1"
    aria-labelledby="confirmProfileUpdateModalLabel"
    aria-describedby="confirmProfileUpdateModalDescription"
    aria-hidden="true"
    data-profile-confirmation-modal
    @if($errors->getBag('updateProfile')->has('current_password')) data-auto-show-modal @endif
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="confirmProfileUpdateModalLabel">
                    <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>
                    Konfirmasi Perubahan Profil
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p id="confirmProfileUpdateModalDescription" class="text-body-secondary mb-3">
                    Masukkan password saat ini untuk memastikan perubahan dilakukan oleh Anda.
                </p>

                <x-form.input
                    name="current_password"
                    id="profile_current_password"
                    label="Password Saat Ini"
                    type="password"
                    autocomplete="current-password"
                    error-bag="updateProfile"
                    form="profileInformationForm"
                    required
                />
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary" form="profileInformationForm">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                    Konfirmasi &amp; Simpan
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
