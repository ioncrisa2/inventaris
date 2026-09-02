@extends('layouts.app')

@section('title', 'Kesehatan Sistem - System Owner')

@section('content')
    @php
        $statusLabels = [
            'healthy' => 'Sehat',
            'warning' => 'Peringatan',
            'critical' => 'Kritis',
            'unknown' => 'Tidak diketahui',
        ];
        $statusIcons = [
            'healthy' => 'bi-check-circle',
            'warning' => 'bi-exclamation-triangle',
            'critical' => 'bi-x-octagon',
            'unknown' => 'bi-question-circle',
        ];
        $metricLabels = [
            'release' => 'Release',
            'environment' => 'Environment',
            'deployed_at' => 'Deploy terakhir',
            'uptime_seconds' => 'Uptime',
            'latency_ms' => 'Latency',
            'connections' => 'Koneksi',
            'pending_jobs' => 'Job tertunda',
            'failed_jobs' => 'Job gagal',
            'oldest_pending_age_seconds' => 'Umur job tertua',
            'scan_required' => 'Scan wajib',
            'reachable' => 'Dapat dijangkau',
            'pending_scan_files' => 'Menunggu scan',
            'oldest_pending_scan_age_seconds' => 'Umur scan tertua',
            'failed_media_files' => 'File gagal',
            'failed_media_jobs' => 'Job media gagal',
            'staging_backlog' => 'File di staging',
            'orphan_files' => 'File tanpa owner',
            'missing_files' => 'File hilang',
            'last_heartbeat_at' => 'Heartbeat terakhir',
            'age_seconds' => 'Usia heartbeat',
            'last_completed_at' => 'Backup terakhir',
            'age_hours' => 'Usia backup',
            'latest_size_bytes' => 'Ukuran backup',
            'restic_check_status' => 'Integritas repository',
            'restic_checked_at' => 'Pemeriksaan repository',
            'restore_test_status' => 'Uji restore',
            'restore_tested_at' => 'Uji restore terakhir',
            'total_bytes' => 'Kapasitas',
            'used_bytes' => 'Terpakai',
            'available_bytes' => 'Tersedia',
            'used_percent' => 'Persentase terpakai',
            'configured' => 'Terkonfigurasi',
            'last_error_at' => 'Error terakhir',
            'count' => 'Jumlah error',
        ];
        $formatBytes = static function ($bytes): string {
            if (!is_numeric($bytes)) {
                return 'Tidak tersedia';
            }
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $value = (float) $bytes;
            $unit = 0;
            while ($value >= 1024 && $unit < count($units) - 1) {
                $value /= 1024;
                $unit++;
            }
            return number_format($value, $unit === 0 ? 0 : 1, ',', '.') . ' ' . $units[$unit];
        };
        $formatMetric = static function (string $key, $value) use ($formatBytes): string {
            if ($value === null) {
                return 'Tidak tersedia';
            }
            if (in_array($key, ['total_bytes', 'used_bytes', 'available_bytes', 'latest_size_bytes'], true)) {
                return $formatBytes($value);
            }
            if ($key === 'latency_ms') {
                return number_format((float) $value, 2, ',', '.') . ' ms';
            }
            if (in_array($key, ['uptime_seconds', 'oldest_pending_age_seconds', 'oldest_pending_scan_age_seconds', 'age_seconds'], true)) {
                return number_format((int) $value, 0, ',', '.') . ' detik';
            }
            if ($key === 'age_hours') {
                return number_format((float) $value, 1, ',', '.') . ' jam';
            }
            if ($key === 'used_percent') {
                return number_format((float) $value, 1, ',', '.') . '%';
            }
            if (is_bool($value)) {
                return $value ? 'Ya' : 'Tidak';
            }
            if (is_numeric($value)) {
                return number_format((float) $value, 0, ',', '.');
            }
            if (str_ends_with($key, '_at')) {
                try {
                    return \Carbon\CarbonImmutable::parse($value)->locale('id')->translatedFormat('d M Y, H:i');
                } catch (\Throwable) {
                }
            }
            return (string) $value;
        };
        $overall = $health['overall_status'];
    @endphp

    <x-app-page long-footer>
        <x-page-header title="Kesehatan Sistem" subtitle="Status dependency penting dengan keluaran yang sudah disanitasi.">
            <x-slot:actions>
                <a class="btn btn-outline-secondary" href="{{ route('owner.system-health') }}">
                    <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Periksa ulang
                </a>
            </x-slot:actions>
        </x-page-header>

        <section class="owner-health-summary owner-status--{{ $overall }}" aria-labelledby="ownerHealthOverallTitle">
            <span class="owner-health-summary__icon"><i class="bi {{ $statusIcons[$overall] }}" aria-hidden="true"></i></span>
            <div>
                <p>Status keseluruhan</p>
                <h2 id="ownerHealthOverallTitle">{{ $statusLabels[$overall] }}</h2>
                <span>Diperiksa
                    {{ \Carbon\CarbonImmutable::parse($health['checked_at'])->locale('id')->diffForHumans() }}{{ $health['is_cached_snapshot'] ? ' · hasil cache' : '' }}</span>
            </div>
            <dl class="owner-health-summary__counts">
                <div>
                    <dt>Sehat</dt>
                    <dd>{{ $health['status_counts']['healthy'] }}</dd>
                </div>
                <div>
                    <dt>Peringatan</dt>
                    <dd>{{ $health['status_counts']['warning'] }}</dd>
                </div>
                <div>
                    <dt>Kritis</dt>
                    <dd>{{ $health['status_counts']['critical'] }}</dd>
                </div>
                <div>
                    <dt>Belum diketahui</dt>
                    <dd>{{ $health['status_counts']['unknown'] }}</dd>
                </div>
            </dl>
        </section>

        <div class="owner-health-list mt-4">
            @foreach ($health['checks'] as $key => $check)
                <article class="owner-health-check owner-status--{{ $check['status'] }}">
                    <div class="owner-health-check__summary">
                        <span class="owner-health-check__icon"><i class="bi {{ $statusIcons[$check['status']] }}"
                                aria-hidden="true"></i></span>
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <h2>{{ $check['label'] }}</h2>
                                <span class="owner-status-badge">{{ $statusLabels[$check['status']] }}</span>
                            </div>
                            <p>{{ $check['message'] }}</p>
                        </div>
                        <small>{{ number_format($check['duration_ms'], 1, ',', '.') }} ms</small>
                    </div>
                    @if (!empty($check['metrics']))
                        <dl class="owner-health-check__metrics">
                            @foreach ($check['metrics'] as $metric => $value)
                                <div>
                                    <dt>{{ $metricLabels[$metric] ?? str($metric)->replace('_', ' ')->headline() }}</dt>
                                    <dd>{{ $formatMetric($metric, $value) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </article>
            @endforeach
        </div>

        <p class="owner-observability-footnote">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            Halaman ini tidak menjalankan pengiriman email percobaan dan tidak menampilkan exception, payload queue,
            kredensial, atau path server.
        </p>
    </x-app-page>
@endsection
