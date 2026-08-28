<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemHealthService
{
    private const CACHE_KEY = 'owner-observability:v1:system-health';

    public function __construct(private StorageUsageService $storageUsageService) {}

    /**
     * Snapshot hanya membawa status dan metrik yang telah dipilih. Exception,
     * payload queue, isi log, kredensial, dan path lokal tidak pernah menjadi
     * bagian response.
     *
     * @return array<string, mixed>
     */
    public function snapshot(bool $fresh = false): array
    {
        if (! $fresh) {
            try {
                $cached = Cache::get(self::CACHE_KEY);

                if ($this->isValidSnapshot($cached)) {
                    $cached['is_cached_snapshot'] = true;

                    return $cached;
                }
            } catch (Throwable) {
                // Cache tidak boleh menjadi single point of failure health page.
            }
        }

        $snapshot = $this->inspect();

        try {
            Cache::put(
                self::CACHE_KEY,
                $snapshot,
                max(1, (int) config('owner_observability.health_cache_seconds', 45)),
            );
        } catch (Throwable) {
            // Hasil tetap dapat digunakan walaupun tidak berhasil di-cache.
        }

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    private function inspect(): array
    {
        $checks = [
            'application' => $this->safely('Aplikasi', 'warning', fn (): array => $this->applicationCheck()),
            'database' => $this->safely('Database', 'critical', fn (): array => $this->databaseCheck()),
            'cache' => $this->safely('Cache', 'warning', fn (): array => $this->cacheCheck()),
            'queue' => $this->safely('Queue', 'warning', fn (): array => $this->queueCheck()),
            'scheduler' => $this->safely('Scheduler', 'warning', fn (): array => $this->schedulerCheck()),
            'backup' => $this->safely('Backup', 'warning', fn (): array => $this->backupCheck()),
            'storage' => $this->safely('Storage', 'critical', fn (): array => $this->storageCheck()),
            'mail' => $this->safely('Mail', 'warning', fn (): array => $this->mailCheck()),
            'application_errors' => $this->safely('Error aplikasi', 'warning', fn (): array => $this->errorCheck()),
        ];

        $counts = array_fill_keys(['healthy', 'warning', 'critical', 'unknown'], 0);
        foreach ($checks as $check) {
            $counts[$check['status']]++;
        }

        $overall = match (true) {
            $counts['critical'] > 0 => 'critical',
            $counts['warning'] > 0 => 'warning',
            $counts['healthy'] > 0 => 'healthy',
            default => 'unknown',
        };

        return [
            'schema_version' => 1,
            'overall_status' => $overall,
            'checked_at' => now()->toIso8601String(),
            'is_cached_snapshot' => false,
            'status_counts' => $counts,
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationCheck(): array
    {
        $release = $this->safeText(config('owner_observability.deployment.release'));
        $environment = $this->safeText(app()->environment(), 40) ?? 'unknown';
        $deployedAt = $this->safeDate(config('owner_observability.deployment.deployed_at'));

        return $this->result(
            'healthy',
            'Aplikasi aktif.',
            [
                'release' => $release,
                'environment' => $environment,
                'deployed_at' => $deployedAt?->toIso8601String(),
                'uptime_seconds' => $deployedAt ? max(0, $deployedAt->diffInSeconds(now(), true)) : null,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseCheck(): array
    {
        $started = hrtime(true);
        $row = DB::connection()->selectOne('SELECT 1 AS health_check');
        $latency = round((hrtime(true) - $started) / 1_000_000, 2);

        if ((int) ($row?->health_check ?? 0) !== 1) {
            return $this->result('critical', 'Database tidak dapat memverifikasi koneksi.');
        }

        $warning = max(1, (float) config('owner_observability.database.warning_latency_ms', 250));
        $critical = max($warning, (float) config('owner_observability.database.critical_latency_ms', 1000));
        $status = match (true) {
            $latency >= $critical => 'critical',
            $latency >= $warning => 'warning',
            default => 'healthy',
        };

        return $this->result(
            $status,
            $status === 'healthy' ? 'Koneksi database normal.' : 'Respons database lebih lambat dari batas yang ditetapkan.',
            [
                'latency_ms' => $latency,
                'connections' => $this->databaseConnectionCount(),
            ],
        );
    }

    private function databaseConnectionCount(): ?int
    {
        try {
            return match (DB::connection()->getDriverName()) {
                'mysql', 'mariadb' => $this->mysqlConnectionCount(),
                'pgsql' => $this->numericValue(DB::selectOne('SELECT COUNT(*) AS value FROM pg_stat_activity')?->value ?? null),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    private function mysqlConnectionCount(): ?int
    {
        $row = DB::selectOne("SHOW STATUS LIKE 'Threads_connected'");

        return $this->numericValue($row?->Value ?? $row?->value ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function cacheCheck(): array
    {
        $key = 'owner-observability:v1:probe:'.bin2hex(random_bytes(8));
        $value = bin2hex(random_bytes(12));

        try {
            Cache::put($key, $value, 10);
            $readBack = Cache::get($key);
        } finally {
            try {
                Cache::forget($key);
            } catch (Throwable) {
                // Probe tetap dilaporkan gagal oleh wrapper bila operasi utama gagal.
            }
        }

        return $readBack === $value
            ? $this->result('healthy', 'Operasi baca dan tulis cache berhasil.')
            : $this->result('critical', 'Cache tidak mengembalikan nilai probe yang benar.');
    }

    /**
     * @return array<string, mixed>
     */
    private function queueCheck(): array
    {
        $connectionName = (string) config('queue.default', 'sync');
        $driver = (string) config("queue.connections.{$connectionName}.driver", 'unknown');
        $pending = null;
        $oldestAge = null;
        $telemetryAvailable = false;

        if ($driver === 'database') {
            $databaseConnection = config("queue.connections.{$connectionName}.connection")
                ?: config('database.default');
            $table = config("queue.connections.{$connectionName}.table", 'jobs');

            if (! $this->safeIdentifier($table) || ! Schema::connection($databaseConnection)->hasTable($table)) {
                return $this->result('critical', 'Tabel antrean database tidak tersedia.', [
                    'pending_jobs' => null,
                    'failed_jobs' => $this->failedJobsCount(),
                    'oldest_pending_age_seconds' => null,
                ]);
            }

            $queue = DB::connection($databaseConnection)->table($table);
            $pending = $queue->count();
            $oldestCreatedAt = $queue->min('created_at');
            $oldestAge = is_numeric($oldestCreatedAt)
                ? max(0, now()->timestamp - (int) $oldestCreatedAt)
                : null;
            $telemetryAvailable = true;
        } elseif (in_array($driver, ['sync', 'deferred', 'background', 'null'], true)) {
            $pending = 0;
            $oldestAge = null;
            $telemetryAvailable = true;
        }

        $failed = $this->failedJobsCount();

        if (! $telemetryAvailable && $failed === null) {
            return $this->result('unknown', 'Statistik antrean tidak tersedia untuk driver yang digunakan.', [
                'pending_jobs' => null,
                'failed_jobs' => null,
                'oldest_pending_age_seconds' => null,
            ]);
        }

        $warningPending = max(1, (int) config('owner_observability.queue.warning_pending_jobs', 100));
        $criticalPending = max($warningPending, (int) config('owner_observability.queue.critical_pending_jobs', 1000));
        $warningFailed = max(1, (int) config('owner_observability.queue.warning_failed_jobs', 1));
        $criticalFailed = max($warningFailed, (int) config('owner_observability.queue.critical_failed_jobs', 10));
        $status = match (true) {
            $pending !== null && $pending >= $criticalPending => 'critical',
            $failed !== null && $failed >= $criticalFailed => 'critical',
            $pending !== null && $pending >= $warningPending => 'warning',
            $failed !== null && $failed >= $warningFailed => 'warning',
            default => 'healthy',
        };

        return $this->result(
            $status,
            $status === 'healthy' ? 'Antrean tidak menunjukkan backlog bermasalah.' : 'Antrean membutuhkan perhatian.',
            [
                'pending_jobs' => $pending,
                'failed_jobs' => $failed,
                'oldest_pending_age_seconds' => $oldestAge,
            ],
        );
    }

    private function failedJobsCount(): ?int
    {
        try {
            $driver = config('queue.failed.driver');
            if (! in_array($driver, ['database', 'database-uuids'], true)) {
                return null;
            }

            $connection = config('queue.failed.database') ?: config('database.default');
            $table = config('queue.failed.table', 'failed_jobs');

            if (! $this->safeIdentifier($table) || ! Schema::connection($connection)->hasTable($table)) {
                return null;
            }

            return DB::connection($connection)->table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function schedulerCheck(): array
    {
        $key = (string) config('owner_observability.scheduler.cache_key');
        $heartbeat = Cache::get($key);
        $timestamp = is_array($heartbeat) ? ($heartbeat['recorded_at'] ?? null) : null;

        if (! is_numeric($timestamp) || (int) $timestamp <= 0) {
            return $this->result('unknown', 'Heartbeat scheduler belum tersedia.', [
                'last_heartbeat_at' => null,
                'age_seconds' => null,
            ]);
        }

        $recordedAt = CarbonImmutable::createFromTimestamp((int) $timestamp, config('app.timezone'));
        $age = max(0, now()->timestamp - $recordedAt->timestamp);
        $warning = max(1, (int) config('owner_observability.scheduler.warning_after_seconds', 150));
        $critical = max($warning, (int) config('owner_observability.scheduler.critical_after_seconds', 300));
        $status = match (true) {
            $age >= $critical => 'critical',
            $age >= $warning => 'warning',
            default => 'healthy',
        };

        return $this->result(
            $status,
            $status === 'healthy' ? 'Scheduler mengirim heartbeat tepat waktu.' : 'Heartbeat scheduler terlambat.',
            [
                'last_heartbeat_at' => $recordedAt->toIso8601String(),
                'age_seconds' => $age,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function backupCheck(): array
    {
        $metadata = $this->readBackupMetadata();
        if ($metadata === []) {
            return $this->result('unknown', 'Metadata proses backup belum diintegrasikan.', [
                'last_completed_at' => null,
                'age_hours' => null,
                'latest_size_bytes' => null,
            ]);
        }

        $backupStatus = $metadata['status'] ?? null;
        if (! is_string($backupStatus) || ! in_array($backupStatus, ['success', 'failed', 'running'], true)) {
            return $this->result('unknown', 'Metadata proses backup tidak valid.', [
                'last_completed_at' => null,
                'age_hours' => null,
                'latest_size_bytes' => null,
            ]);
        }

        if ($backupStatus === 'failed') {
            return $this->result('critical', 'Proses backup terakhir dilaporkan gagal.', [
                'last_completed_at' => null,
                'age_hours' => null,
                'latest_size_bytes' => null,
            ]);
        }

        if ($backupStatus === 'running') {
            return $this->result('healthy', 'Proses backup sedang berjalan.', [
                'last_completed_at' => null,
                'age_hours' => null,
                'latest_size_bytes' => null,
            ]);
        }

        $completedAt = $this->safeDate($metadata['completed_at'] ?? null);
        $size = $this->numericValue($metadata['size_bytes'] ?? null);

        if (! $completedAt) {
            return $this->result('unknown', 'Waktu penyelesaian backup tidak tersedia.', [
                'last_completed_at' => null,
                'age_hours' => null,
                'latest_size_bytes' => $size,
            ]);
        }

        $ageHours = round(max(0, $completedAt->diffInSeconds(now(), true)) / 3600, 2);
        $warning = max(1, (int) config('owner_observability.backup.warning_after_hours', 26));
        $critical = max($warning, (int) config('owner_observability.backup.critical_after_hours', 72));
        $status = match (true) {
            $ageHours >= $critical => 'critical',
            $ageHours >= $warning => 'warning',
            default => 'healthy',
        };

        return $this->result(
            $status,
            $status === 'healthy' ? 'Backup terakhir masih dalam batas waktu.' : 'Backup terakhir melewati batas waktu yang ditetapkan.',
            [
                'last_completed_at' => $completedAt->toIso8601String(),
                'age_hours' => $ageHours,
                'latest_size_bytes' => $size,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function storageCheck(): array
    {
        $capacity = $this->storageUsageService->capacitySnapshot();
        $freePercent = $capacity['free_percent'] ?? null;

        if (($capacity['status'] ?? null) !== 'available' || ! is_numeric($freePercent)) {
            return $this->result('unknown', 'Kapasitas volume tidak tersedia untuk storage yang digunakan.', [
                'total_bytes' => null,
                'used_bytes' => null,
                'available_bytes' => null,
                'used_percent' => null,
            ]);
        }

        $warning = max(0, (float) config('owner_observability.storage.warning_free_percent', 15));
        $critical = max(0, min($warning, (float) config('owner_observability.storage.critical_free_percent', 5)));
        $status = match (true) {
            (float) $freePercent <= $critical => 'critical',
            (float) $freePercent <= $warning => 'warning',
            default => 'healthy',
        };

        return $this->result(
            $status,
            $status === 'healthy' ? 'Ruang volume masih mencukupi.' : 'Ruang volume yang tersedia menipis.',
            [
                'total_bytes' => $capacity['total_bytes'],
                'used_bytes' => $capacity['used_bytes'],
                'available_bytes' => $capacity['available_bytes'],
                'used_percent' => $capacity['used_percent'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mailCheck(): array
    {
        $mailer = (string) config('mail.default', '');
        $transport = config("mail.mailers.{$mailer}.transport");

        if ($mailer === '' || ! is_string($transport) || $transport === '') {
            return $this->result('unknown', 'Transport mail belum dikonfigurasi.', [
                'configured' => false,
                'last_error_at' => null,
            ]);
        }

        $lastErrorAt = null;
        $errorKey = config('owner_observability.metrics.mail_last_error_cache_key');
        if (is_string($errorKey) && $errorKey !== '') {
            $record = Cache::get($errorKey);
            $lastErrorAt = $this->safeDate(is_array($record) ? ($record['recorded_at'] ?? null) : $record);
        }

        return $this->result(
            $lastErrorAt ? 'warning' : 'healthy',
            $lastErrorAt
                ? 'Kegagalan pengiriman mail pernah dicatat oleh integrasi monitoring.'
                : 'Transport mail terkonfigurasi; pengiriman tidak diuji secara aktif.',
            [
                'configured' => true,
                'last_error_at' => $lastErrorAt?->toIso8601String(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function errorCheck(): array
    {
        $key = config('owner_observability.metrics.error_count_cache_key');
        if (! is_string($key) || $key === '') {
            return $this->result('unknown', 'Counter error tersanitasi belum diintegrasikan.', [
                'count' => null,
            ]);
        }

        $count = $this->numericValue(Cache::get($key));
        if ($count === null) {
            return $this->result('unknown', 'Counter error tersanitasi belum tersedia.', [
                'count' => null,
            ]);
        }

        return $this->result(
            $count > 0 ? 'warning' : 'healthy',
            $count > 0 ? 'Error aplikasi tercatat dalam periode monitoring.' : 'Tidak ada error pada counter monitoring.',
            ['count' => $count],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function safely(string $label, string $failureStatus, callable $check): array
    {
        $started = hrtime(true);

        try {
            $result = $check();
        } catch (Throwable) {
            $result = $this->result($failureStatus, "Pemeriksaan {$label} tidak berhasil.");
        }

        $duration = round((hrtime(true) - $started) / 1_000_000, 2);
        $maxDuration = max(1, (int) config('owner_observability.max_check_duration_ms', 2000));
        if ($duration > $maxDuration && ($result['status'] ?? null) === 'healthy') {
            $result['status'] = 'warning';
            $result['message'] = "Pemeriksaan {$label} melebihi batas waktu respons.";
        }

        return [
            'label' => $label,
            'status' => in_array($result['status'] ?? null, ['healthy', 'warning', 'critical', 'unknown'], true)
                ? $result['status']
                : 'unknown',
            'message' => $this->safeText($result['message'] ?? null, 180) ?? 'Status tidak tersedia.',
            'metrics' => is_array($result['metrics'] ?? null) ? $result['metrics'] : [],
            'duration_ms' => $duration,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, int|float|string|bool|null>  $metrics
     * @return array{status: string, message: string, metrics: array<string, int|float|string|bool|null>}
     */
    private function result(string $status, string $message, array $metrics = []): array
    {
        return compact('status', 'message', 'metrics');
    }

    /**
     * @return array<string, mixed>
     */
    private function readBackupMetadata(): array
    {
        $path = config('owner_observability.backup.metadata_path');
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return [];
        }

        $maxBytes = max(1024, (int) config('owner_observability.backup.max_metadata_bytes', 65536));
        $size = @filesize($path);
        if (! is_int($size) || $size < 1 || $size > $maxBytes) {
            return [];
        }

        try {
            $content = @file_get_contents($path, false, null, 0, $maxBytes + 1);
            if (! is_string($content) || strlen($content) > $maxBytes) {
                return [];
            }

            $decoded = json_decode($content, true, 8, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function safeDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '' || strlen($value) > 80) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    private function safeText(mixed $value, int $limit = 100): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    private function numericValue(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (int) $value;

        return $number >= 0 ? $number : null;
    }

    private function safeIdentifier(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    private function isValidSnapshot(mixed $value): bool
    {
        return is_array($value)
            && ($value['schema_version'] ?? null) === 1
            && isset($value['overall_status'], $value['checked_at'], $value['checks']);
    }
}
