@extends('layouts.auth')

@section('title', 'Masa Aktif Berakhir - Sistem Inventaris & Kepegawaian')

@section('content')
<main class="auth-page">
    <div class="auth-card card shadow-sm">
        <div class="card-body p-4 text-center">
            <i class="bi bi-hourglass-bottom text-danger" style="font-size: 2.5rem;"></i>
            <h1 class="h4 mt-3 mb-2">Masa Aktif Berakhir</h1>

            @if($koperasi && ! $koperasi->is_active)
                <p class="text-body-secondary mb-4">
                    Akun koperasi <strong>{{ $koperasi->nama }}</strong> sedang dinonaktifkan.
                    Hubungi admin penyedia layanan untuk mengaktifkannya kembali.
                </p>
            @elseif($koperasi && $koperasi->expires_at)
                <p class="text-body-secondary mb-4">
                    Masa aktif langganan koperasi <strong>{{ $koperasi->nama }}</strong> berakhir
                    pada {{ $koperasi->expires_at->translatedFormat('d F Y') }}.
                    Hubungi admin penyedia layanan untuk memperpanjang masa aktif.
                </p>
            @else
                <p class="text-body-secondary mb-4">
                    Akun koperasi Anda belum/tidak aktif. Hubungi admin penyedia layanan
                    untuk informasi lebih lanjut.
                </p>
            @endif

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-light w-100">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </button>
            </form>
        </div>
    </div>
</main>
@endsection
