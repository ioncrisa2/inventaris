@extends('layouts.app')

@section('title', 'Backup & Restore - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
    <x-page-header title="Backup & Restore Lokal" />

    <div class="row g-4">
        <div class="col-12 col-lg-8 mx-auto">
            <x-section-card title="Status Backup Terakhir">
                @if ($status === 'none')
                    <div class="alert alert-secondary">
                        Belum ada metadata backup yang ditemukan. Sistem mungkin belum pernah di-backup.
                    </div>
                @elseif ($status === 'running')
                    <div class="alert alert-info d-flex align-items-center gap-3">
                        <span class="spinner-border text-info" role="status" aria-hidden="true"></span>
                        <div>
                            <strong>Backup sedang berjalan...</strong>
                            <p class="mb-0">Dimulai sejak: {{ $startedAt ? $startedAt->translatedFormat('d F Y H:i:s') : 'Tidak diketahui' }}</p>
                        </div>
                    </div>
                @elseif ($status === 'success')
                    <div class="alert alert-success">
                        <strong><i class="bi bi-check-circle me-1"></i> Backup Berhasil</strong>
                        <ul class="mb-0 mt-2">
                            <li>Waktu Selesai: {{ $finishedAt ? $finishedAt->translatedFormat('d F Y H:i:s') : '-' }}</li>
                            <li>Durasi: {{ $duration !== null ? $duration . ' detik' : '-' }}</li>
                            <li>Snapshot ID: <code>{{ $metadata['snapshot_id'] ?? '-' }}</code></li>
                            <li>Files Di-backup: {{ number_format($metadata['files_new'] ?? 0 + $metadata['files_changed'] ?? 0 + $metadata['files_unmodified'] ?? 0) }}</li>
                        </ul>
                    </div>
                @elseif ($status === 'failed')
                    <div class="alert alert-danger">
                        <strong><i class="bi bi-x-circle me-1"></i> Backup Gagal</strong>
                        <p class="mb-0 mt-1">Backup terakhir gagal. Waktu Selesai: {{ $finishedAt ? $finishedAt->translatedFormat('d F Y H:i:s') : '-' }}</p>
                    </div>
                @else
                    <div class="alert alert-warning">
                        Status backup tidak dikenali: {{ $status }}
                    </div>
                @endif

                <x-slot:footer>
                    <form action="{{ route('owner.backup.store') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary" {{ $status === 'running' ? 'disabled' : '' }}>
                            <i class="bi bi-cloud-download me-1"></i> Jalankan Backup Sekarang
                        </button>
                    </form>
                </x-slot:footer>
            </x-section-card>
        </div>
    </div>
</x-app-page>
@endsection
