@extends('layouts.app')

@section('title', $guide['title'].' - Puskopdit PHS Sumsel')

@section('content')
<x-app-page class="quick-guide-page">
    <header class="quick-guide-header">
        <div>
            <div class="quick-guide-audience">
                <i class="bi bi-person-check" aria-hidden="true"></i>
                {{ $guide['audience'] }}
            </div>
            <h1>{{ $guide['title'] }}</h1>
            <p>{{ $guide['subtitle'] }}</p>
        </div>
        <div class="quick-guide-header__actions">
            <a href="{{ route('panduan-singkat.cetak') }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-printer me-1" aria-hidden="true"></i>
                Cetak Panduan
            </a>
            <a href="{{ route('dashboard') }}" class="btn btn-light btn-sm border">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
                Kembali ke Dashboard
            </a>
        </div>
    </header>

    <section class="quick-guide-context" aria-labelledby="guide-context-title">
        <div class="quick-guide-context__icon" aria-hidden="true">
            <i class="bi {{ $guide['key'] === 'admin-primer' ? 'bi-building-check' : 'bi-buildings' }}"></i>
        </div>
        <div class="quick-guide-context__copy">
            <h2 id="guide-context-title">Mulai dari lingkup yang benar</h2>
            <p>Panduan ini hanya memuat tugas dan batas akses yang sesuai dengan akun Anda.</p>
            <dl>
                <div>
                    <dt>Lingkup</dt>
                    <dd>{{ $guide['scope'] }}</dd>
                </div>
            </dl>
        </div>
        <nav class="quick-guide-actions" aria-label="Tindakan awal yang disarankan">
            @foreach($guide['actions'] as $action)
                <a href="{{ route($action['route']) }}" class="btn btn-primary btn-sm">
                    <i class="bi {{ $action['icon'] }} me-1" aria-hidden="true"></i>
                    {{ $action['label'] }}
                </a>
            @endforeach
        </nav>
    </section>

    <div class="quick-guide-document">
        @include($guide['content_view'])
    </div>

    @include('panduan.partials.footer')
</x-app-page>
@endsection
