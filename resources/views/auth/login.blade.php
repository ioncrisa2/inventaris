@extends('layouts.auth')

@section('title', 'Login - Sistem Inventaris & Kepegawaian')

@php($identitas = app(\App\Services\IdentitasAplikasiService::class))

@section('content')
<main class="auth-page">
    <div class="auth-card card shadow-sm">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <img src="{{ $identitas->logoUrl() ?? asset('assets/img/logo-koperasi.png') }}" alt="Logo" class="auth-logo mb-3">
                <h1 class="h4 mb-1">Login Sistem {{ $identitas->nama() }}</h1>
                <p class="text-body-secondary mb-0">Sistem Informasi Inventaris & Kepegawaian</p>
            </div>

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" autocomplete="email" required autofocus>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <x-form.input
                        name="password"
                        label="Password"
                        type="password"
                        autocomplete="current-password"
                        required
                    />
                </div>

                <div class="form-check mb-4">
                    <input type="checkbox" name="remember" id="remember" class="form-check-input" {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label" for="remember">Ingat saya</label>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Masuk
                </button>
            </form>
        </div>
    </div>
</main>
@endsection
