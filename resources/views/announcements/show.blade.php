@extends('layouts.app')

@section('title', $announcement->title)

@section('content')
<x-app-page>
    <x-page-header :title="$announcement->title" subtitle="Pengumuman resmi dari pengelola platform">
        <x-slot:actions><a class="btn btn-light" href="{{ route('notifications.index') }}">Kembali ke Notifikasi</a></x-slot:actions>
    </x-page-header>

    <article class="card content-narrow">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge {{ $announcement->priority === 'critical' ? 'bg-danger' : ($announcement->priority === 'warning' ? 'bg-warning text-dark' : 'bg-info text-dark') }}">{{ ucfirst($announcement->priority) }}</span>
                <span class="text-body-secondary small">Diterbitkan {{ $announcement->published_at->translatedFormat('d F Y H:i') }}</span>
            </div>
            <div class="text-prewrap">{{ $announcement->body }}</div>
        </div>
    </article>
</x-app-page>
@endsection
