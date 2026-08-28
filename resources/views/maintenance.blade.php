@extends('layouts.auth')

@section('title', 'Platform Sedang Dipelihara')

@section('content')
<main class="container py-5">
    <div class="mx-auto text-center" style="max-width: 42rem">
        <i class="bi bi-tools display-4 text-warning" aria-hidden="true"></i>
        <h1 class="h2 mt-3">Platform sedang dalam pemeliharaan</h1>
        <p class="text-body-secondary">
            {{ $setting?->message ?: 'Kami sedang melakukan pembaruan agar layanan tetap aman dan stabil.' }}
        </p>
        @if($setting?->ends_at)
            <p>Perkiraan selesai: <strong>{{ $setting->ends_at->translatedFormat('d F Y H:i') }} WIB</strong></p>
        @endif
        @auth
            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                @csrf
                <button class="btn btn-outline-secondary" type="submit">Keluar dan ganti akun</button>
            </form>
        @else
            <a class="btn btn-primary mt-4" href="{{ route('login') }}">Masuk sebagai System Owner</a>
        @endauth
    </div>
</main>
@endsection
