@extends('layouts.app')

@section('title', 'Penyimpanan - System Owner')

@section('content')
    @php
        $formatBytes = static fn ($bytes): string => \App\Support\OwnerMetricFormatter::bytes($bytes);
        $files = $storage['logical_application_files'];
        $database = $storage['database'];
        $volume = $storage['host_volume'];
    @endphp

    <x-app-page long-footer>
        <x-page-header title="Penyimpanan" subtitle="Kapasitas volume dan penggunaan file aplikasi tanpa akses ke isi file.">
            <x-slot:actions>
                <a class="btn btn-outline-secondary" href="{{ route('owner.storage') }}">
                    <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Ukur ulang
                </a>
            </x-slot:actions>
        </x-page-header>

        <div class="owner-storage-summary">
            <article>
                <span><i class="bi bi-folder2-open" aria-hidden="true"></i></span>
                <div>
                    <p>File aplikasi</p>
                    <strong>{{ $formatBytes($files['total_bytes']) }}</strong><small>{{ number_format($files['total_files'], 0, ',', '.') }}
                        file</small>
                </div>
            </article>
            <article>
                <span><i class="bi bi-database" aria-hidden="true"></i></span>
                <div>
                    <p>Ukuran database</p>
                    <strong>{{ $formatBytes($database['size_bytes']) }}</strong><small>{{ $database['status'] === 'available' ? 'Alokasi database' : 'Tidak tersedia' }}</small>
                </div>
            </article>
            <article class="owner-storage-summary__volume">
                <span><i class="bi bi-device-ssd" aria-hidden="true"></i></span>
                <div>
                    <p>Volume host</p>
                    <strong>{{ $formatBytes($volume['used_bytes']) }} terpakai</strong>
                    @if ($volume['status'] === 'available')
                        <div class="progress" role="progressbar" aria-label="Penggunaan volume"
                            aria-valuenow="{{ $volume['used_percent'] }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: {{ min(100, $volume['used_percent']) }}%"></div>
                        </div>
                        <small>{{ number_format($volume['used_percent'], 1, ',', '.') }}% dari
                            {{ $formatBytes($volume['total_bytes']) }}</small>
                    @else
                        <small>Kapasitas fisik tidak tersedia untuk driver ini.</small>
                    @endif
                </div>
            </article>
        </div>

        @unless ($files['is_complete'])
            <div class="alert alert-warning mt-4" role="status">
                <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
                Sebagian ukuran merupakan estimasi karena ada sumber atau file yang belum dapat diukur.
            </div>
        @endunless

        <div class="owner-storage-grid mt-4">
            <section class="owner-panel" aria-labelledby="storageCategoryTitle">
                <header class="owner-panel__header">
                    <div>
                        <h2 id="storageCategoryTitle">Kategori file</h2>
                        <p>Penggunaan logis yang dikelola aplikasi.</p>
                    </div>
                </header>
                <div class="owner-storage-categories">
                    @foreach ($files['categories'] as $category)
                        <div class="owner-storage-category">
                            <span class="owner-storage-category__icon"><i class="bi bi-folder"
                                    aria-hidden="true"></i></span>
                            <div><strong>{{ $category['label'] }}</strong><small>{{ number_format($category['files_count'], 0, ',', '.') }}
                                    file{{ $category['is_estimate'] ? ' · estimasi' : '' }}</small></div>
                            <b>{{ $category['status'] === 'unavailable' ? 'Tidak tersedia' : $formatBytes($category['bytes']) }}</b>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="owner-panel" aria-labelledby="storageCoopTitle">
                <header class="owner-panel__header">
                    <div>
                        <h2 id="storageCoopTitle">Pemakaian per koperasi</h2>
                        <p>Metadata ukuran saja; tidak ada tautan menuju file.</p>
                    </div>
                </header>
                <div class="owner-storage-categories">
                    @forelse($storage['cooperative_usage']['rows'] as $row)
                        <div class="owner-storage-category">
                            <span class="owner-storage-category__icon"><i class="bi bi-building"
                                    aria-hidden="true"></i></span>
                            <div><strong>{{ $row['koperasi'] }}</strong><small>{{ number_format($row['files_count'], 0, ',', '.') }}
                                    file{{ $row['is_estimate'] ? ' · estimasi' : '' }}</small></div>
                            <b>{{ $formatBytes($row['bytes']) }}</b>
                        </div>
                    @empty
                        <p class="owner-muted-empty m-0">Belum ada file tenant yang dapat diukur.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <p class="owner-observability-footnote">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            Diukur
            {{ \Carbon\CarbonImmutable::parse($storage['measured_at'])->locale('id')->diffForHumans() }}{{ $storage['is_cached_snapshot'] ? ' · hasil cache' : '' }}.
        </p>
    </x-app-page>
@endsection
