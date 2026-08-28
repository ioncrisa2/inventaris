<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StorageUsageService
{
    private const CACHE_KEY = 'owner-observability:v1:storage';

    /**
     * Mengembalikan snapshot storage tanpa path file, nama file, atau record
     * operasional. Cache bersifat fail-open agar gangguan cache tidak membuat
     * halaman storage ikut gagal total.
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
                // Cache adalah optimasi; pengukuran tetap harus bisa berjalan.
            }
        }

        $snapshot = $this->measure();

        try {
            Cache::put(
                self::CACHE_KEY,
                $snapshot,
                max(1, (int) config('owner_observability.storage_cache_seconds', 600)),
            );
        } catch (Throwable) {
            // Jangan menggagalkan response hanya karena cache sedang bermasalah.
        }

        return $snapshot;
    }

    /**
     * Kapasitas fisik hanya tersedia untuk disk local. Angka ini mewakili
     * volume host, bukan ukuran file milik aplikasi.
     *
     * @return array<string, int|float|string|null>
     */
    public function capacitySnapshot(?string $disk = null): array
    {
        $disk ??= (string) config('owner_observability.storage.capacity_disk', config('filesystems.default'));
        $diskConfig = config("filesystems.disks.{$disk}");

        if (! is_array($diskConfig) || ($diskConfig['driver'] ?? null) !== 'local') {
            return [
                'status' => 'unavailable',
                'scope' => 'host_volume',
                'total_bytes' => null,
                'used_bytes' => null,
                'available_bytes' => null,
                'used_percent' => null,
                'free_percent' => null,
            ];
        }

        $configuredRoot = $diskConfig['root'] ?? null;
        if (! is_string($configuredRoot) || $configuredRoot === '') {
            return $this->unavailableCapacity();
        }

        try {
            $root = realpath($configuredRoot);

            if ($root === false || ! is_dir($root)) {
                return $this->unavailableCapacity();
            }

            $total = @disk_total_space($root);
            $available = @disk_free_space($root);

            if (! is_float($total) || ! is_float($available) || $total <= 0) {
                return $this->unavailableCapacity();
            }

            $used = max(0, $total - $available);

            return [
                'status' => 'available',
                'scope' => 'host_volume',
                'total_bytes' => (int) round($total),
                'used_bytes' => (int) round($used),
                'available_bytes' => (int) round($available),
                'used_percent' => round(($used / $total) * 100, 2),
                'free_percent' => round(($available / $total) * 100, 2),
            ];
        } catch (Throwable) {
            return $this->unavailableCapacity();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function measure(): array
    {
        $categories = [
            $this->measureCategory('item_photos', 'Foto barang', [
                [
                    'table' => 'barang',
                    'path' => 'foto_sampul',
                    'disk' => 'public',
                    'tenant' => ['table' => 'barang', 'column' => 'koperasi_id'],
                ],
                [
                    'table' => 'foto_barang',
                    'path' => 'path',
                    'disk' => 'public',
                    'joins' => [['barang', 'barang.id', '=', 'foto_barang.barang_id']],
                    'tenant' => ['table' => 'barang', 'column' => 'koperasi_id'],
                ],
            ]),
            $this->measureCategory('item_documents', 'Dokumen barang', [
                [
                    'table' => 'dokumen_barang',
                    'path' => 'path',
                    'disk' => 'local',
                    'joins' => [['barang', 'barang.id', '=', 'dokumen_barang.barang_id']],
                    'tenant' => ['table' => 'barang', 'column' => 'koperasi_id'],
                ],
            ]),
            $this->measureCategory('employee_photos', 'Foto karyawan', [
                [
                    'table' => 'karyawan',
                    'path' => 'foto_karyawan',
                    'disk' => 'public',
                    'tenant' => ['table' => 'karyawan', 'column' => 'koperasi_id'],
                ],
            ]),
            $this->measureCategory('employee_documents', 'Dokumen karyawan', [
                [
                    'table' => 'dokumen_karyawan',
                    'path' => 'path',
                    'disk' => 'local',
                    'joins' => [['karyawan', 'karyawan.id', '=', 'dokumen_karyawan.karyawan_id']],
                    'tenant' => ['table' => 'karyawan', 'column' => 'koperasi_id'],
                ],
                [
                    'table' => 'dokumen_riwayat_karyawan',
                    'path' => 'path',
                    'disk' => 'local',
                    'size' => 'ukuran',
                    'joins' => [
                        ['riwayat_karyawan', 'riwayat_karyawan.id', '=', 'dokumen_riwayat_karyawan.riwayat_karyawan_id'],
                        ['karyawan', 'karyawan.id', '=', 'riwayat_karyawan.karyawan_id'],
                    ],
                    'tenant' => ['table' => 'karyawan', 'column' => 'koperasi_id'],
                ],
            ]),
            $this->measureCategory('tenant_assets', 'Logo dan aset tenant', [
                [
                    'table' => 'pengaturan',
                    'path' => 'value',
                    'disk' => 'public',
                    'where' => ['key', '=', 'identitas_logo_path'],
                    'tenant' => ['table' => 'pengaturan', 'column' => 'koperasi_id'],
                ],
            ]),
            $this->measureCategory('request_attachments', 'Lampiran request produk', [
                [
                    'table' => 'product_request_attachments',
                    'path' => 'path',
                    'disk_column' => 'disk',
                    'size' => 'size_bytes',
                    'joins' => [['product_requests', 'product_requests.id', '=', 'product_request_attachments.product_request_id']],
                    'tenant' => ['table' => 'product_requests', 'column' => 'koperasi_id'],
                ],
            ]),
            $this->backupCategory(),
        ];

        $measuredCategories = array_filter(
            $categories,
            fn (array $category): bool => in_array($category['status'], ['available', 'partial'], true),
        );
        $cooperativeUsage = $this->cooperativeUsage($categories);
        $publicCategories = array_map(function (array $category): array {
            unset($category['tenant_usage']);

            return $category;
        }, $categories);

        return [
            'schema_version' => 1,
            'measured_at' => now()->toIso8601String(),
            'is_cached_snapshot' => false,
            'logical_application_files' => [
                'scope' => 'application_files',
                'total_bytes' => array_sum(array_column($measuredCategories, 'bytes')),
                'total_files' => array_sum(array_column($measuredCategories, 'files_count')),
                'categories' => array_values($publicCategories),
                'is_complete' => count($measuredCategories) === count($categories)
                    && ! collect($categories)->contains(fn (array $category): bool => $category['status'] === 'partial'),
            ],
            'cooperative_usage' => $cooperativeUsage,
            'database' => $this->databaseSize(),
            'host_volume' => $this->capacitySnapshot(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $references
     * @return array<string, mixed>
     */
    private function measureCategory(string $key, string $label, array $references): array
    {
        $bytes = 0;
        $files = 0;
        $missingFiles = 0;
        $unmeasuredFiles = 0;
        $sourcesAvailable = 0;
        $sourcesUnavailable = 0;
        $truncated = false;
        $seen = [];
        $tenantUsage = [];
        $referencesSeen = 0;
        $limit = max(1, (int) config('owner_observability.storage.max_references_per_category', 50000));

        try {
            foreach ($references as $reference) {
                if (! $this->referenceIsAvailable($reference)) {
                    $sourcesUnavailable++;

                    continue;
                }

                $sourcesAvailable++;
                $query = DB::table($reference['table'])
                    ->select([
                        "{$reference['table']}.id as source_id",
                        "{$reference['table']}.{$reference['path']} as logical_path",
                    ])
                    ->whereNotNull("{$reference['table']}.{$reference['path']}")
                    ->where("{$reference['table']}.{$reference['path']}", '<>', '');

                foreach ($reference['joins'] ?? [] as $join) {
                    $query->join(...$join);
                }

                if (isset($reference['size'])) {
                    $query->addSelect("{$reference['table']}.{$reference['size']} as known_size");
                }
                if (isset($reference['disk_column'])) {
                    $query->addSelect("{$reference['table']}.{$reference['disk_column']} as disk_name");
                }
                if (isset($reference['tenant'])) {
                    $query->addSelect(
                        "{$reference['tenant']['table']}.{$reference['tenant']['column']} as tenant_id"
                    );
                }

                if (isset($reference['where']) && is_array($reference['where']) && count($reference['where']) === 3) {
                    $where = $reference['where'];
                    $query->where("{$reference['table']}.{$where[0]}", $where[1], $where[2]);
                }

                $remaining = max(1, $limit - $referencesSeen);
                foreach ($query->orderBy("{$reference['table']}.id")->limit($remaining + 1)->cursor() as $row) {
                    if ($referencesSeen >= $limit) {
                        $truncated = true;

                        break 2;
                    }
                    $referencesSeen++;

                    $path = $row->logical_path ?? null;
                    $disk = isset($reference['disk_column'])
                        ? ($row->disk_name ?? null)
                        : ($reference['disk'] ?? null);
                    $tenantId = $this->positiveInteger($row->tenant_id ?? null);

                    if (! is_string($disk) || ! is_string($path) || ! $this->isSafeLogicalPath($path)) {
                        $unmeasuredFiles++;
                        $this->incrementTenantUsage($tenantUsage, $tenantId, null, true, false);

                        continue;
                    }

                    $identity = hash('sha256', $disk."\0".$path);
                    if (isset($seen[$identity])) {
                        continue;
                    }
                    $seen[$identity] = true;
                    $files++;

                    $knownSize = isset($reference['size']) ? ($row->known_size ?? null) : null;
                    $size = $this->validByteCount($knownSize) ?? $this->localFileSize($disk, $path);

                    if ($size === null) {
                        if ($this->isLocalDisk($disk)) {
                            $missingFiles++;
                        } else {
                            $unmeasuredFiles++;
                        }
                        $this->incrementTenantUsage($tenantUsage, $tenantId, null, true);

                        continue;
                    }

                    $bytes += $size;
                    $this->incrementTenantUsage($tenantUsage, $tenantId, $size, false);
                }
            }
        } catch (Throwable) {
            return $this->unavailableCategory($key, $label);
        }

        if ($sourcesAvailable === 0) {
            return $this->unavailableCategory($key, $label);
        }

        $partial = $sourcesUnavailable > 0 || $missingFiles > 0 || $unmeasuredFiles > 0 || $truncated;

        return [
            'key' => $key,
            'label' => $label,
            'status' => $partial ? 'partial' : 'available',
            'bytes' => $bytes,
            'files_count' => $files,
            'missing_files_count' => $missingFiles,
            'unmeasured_files_count' => $unmeasuredFiles,
            'is_estimate' => $partial,
            'is_truncated' => $truncated,
            'tenant_usage' => $tenantUsage,
        ];
    }

    /**
     * @param  array<string, mixed>  $reference
     */
    private function referenceIsAvailable(array $reference): bool
    {
        $table = $reference['table'] ?? null;
        $pathColumn = $reference['path'] ?? null;

        if (! is_string($table) || ! is_string($pathColumn) || ! Schema::hasTable($table)) {
            return false;
        }

        $required = ['id', $pathColumn];
        if (isset($reference['size'])) {
            $required[] = $reference['size'];
        }
        if (isset($reference['disk_column'])) {
            $required[] = $reference['disk_column'];
        }

        if (! Schema::hasColumns($table, array_values(array_unique($required)))) {
            return false;
        }

        foreach ($reference['joins'] ?? [] as $join) {
            if (! isset($join[0]) || ! is_string($join[0]) || ! Schema::hasTable($join[0])) {
                return false;
            }
        }

        $tenant = $reference['tenant'] ?? null;

        return ! is_array($tenant)
            || (isset($tenant['table'], $tenant['column'])
                && Schema::hasColumns($tenant['table'], [$tenant['column']]));
    }

    private function localFileSize(string $disk, string $path): ?int
    {
        if (! $this->isLocalDisk($disk)) {
            return null;
        }

        try {
            $filesystem = Storage::disk($disk);

            if (! $filesystem->exists($path)) {
                return null;
            }

            return $this->validByteCount($filesystem->size($path));
        } catch (Throwable) {
            return null;
        }
    }

    private function isLocalDisk(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 'local';
    }

    private function isSafeLogicalPath(string $path): bool
    {
        if ($path === '' || strlen($path) > 1024 || str_contains($path, "\0")) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return false;
        }

        return ! collect(explode('/', $normalized))->contains(
            fn (string $segment): bool => in_array($segment, ['.', '..'], true)
        );
    }

    private function validByteCount(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $bytes = (int) $value;

        return $bytes >= 0 ? $bytes : null;
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (! is_numeric($value) || (int) $value < 1) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param  array<int, array{bytes: int, files_count: int, unmeasured_files_count: int}>  $usage
     */
    private function incrementTenantUsage(
        array &$usage,
        ?int $tenantId,
        ?int $bytes,
        bool $unmeasured,
        bool $countFile = true,
    ): void {
        if ($tenantId === null) {
            return;
        }

        $usage[$tenantId] ??= [
            'bytes' => 0,
            'files_count' => 0,
            'unmeasured_files_count' => 0,
        ];
        if ($countFile) {
            $usage[$tenantId]['files_count']++;
        }
        $usage[$tenantId]['bytes'] += $bytes ?? 0;
        if ($unmeasured) {
            $usage[$tenantId]['unmeasured_files_count']++;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $categories
     * @return array{scope: string, rows: list<array<string, int|string|bool>>, is_complete: bool}
     */
    private function cooperativeUsage(array $categories): array
    {
        $usage = [];
        foreach ($categories as $category) {
            foreach ($category['tenant_usage'] ?? [] as $tenantId => $tenant) {
                $usage[$tenantId] ??= [
                    'bytes' => 0,
                    'files_count' => 0,
                    'unmeasured_files_count' => 0,
                ];
                $usage[$tenantId]['bytes'] += $tenant['bytes'];
                $usage[$tenantId]['files_count'] += $tenant['files_count'];
                $usage[$tenantId]['unmeasured_files_count'] += $tenant['unmeasured_files_count'];
            }
        }

        if ($usage === []) {
            return [
                'scope' => 'application_files_by_cooperative',
                'rows' => [],
                'is_complete' => ! collect($categories)->contains(
                    fn (array $category): bool => in_array($category['status'], ['partial', 'unavailable'], true)
                ),
            ];
        }

        try {
            $names = DB::table('koperasi')
                ->whereIn('id', array_keys($usage))
                ->pluck('nama', 'id')
                ->all();
        } catch (Throwable) {
            $names = [];
        }

        $rows = [];
        foreach ($usage as $tenantId => $tenant) {
            if (! isset($names[$tenantId])) {
                continue;
            }

            $rows[] = [
                'koperasi_id' => (int) $tenantId,
                'koperasi' => mb_substr(strip_tags((string) $names[$tenantId]), 0, 120),
                'bytes' => $tenant['bytes'],
                'files_count' => $tenant['files_count'],
                'unmeasured_files_count' => $tenant['unmeasured_files_count'],
                'is_estimate' => $tenant['unmeasured_files_count'] > 0,
            ];
        }

        usort($rows, function (array $left, array $right): int {
            $byBytes = $right['bytes'] <=> $left['bytes'];

            return $byBytes !== 0 ? $byBytes : $left['koperasi_id'] <=> $right['koperasi_id'];
        });

        return [
            'scope' => 'application_files_by_cooperative',
            'rows' => $rows,
            'is_complete' => ! collect($categories)->contains(
                fn (array $category): bool => in_array($category['status'], ['partial', 'unavailable'], true)
            ),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function databaseSize(): array
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            $bytes = match ($driver) {
                'sqlite' => $this->sqliteDatabaseSize(),
                'mysql', 'mariadb' => $this->scalarByteQuery(
                    'SELECT COALESCE(SUM(data_length + index_length), 0) AS size_bytes FROM information_schema.tables WHERE table_schema = DATABASE()'
                ),
                'pgsql' => $this->scalarByteQuery('SELECT pg_database_size(current_database()) AS size_bytes'),
                'sqlsrv' => $this->scalarByteQuery('SELECT SUM(size) * 8192 AS size_bytes FROM sys.database_files'),
                default => null,
            };

            return [
                'status' => $bytes === null ? 'unavailable' : 'available',
                'scope' => 'database_allocated_size',
                'size_bytes' => $bytes,
            ];
        } catch (Throwable) {
            return [
                'status' => 'unavailable',
                'scope' => 'database_allocated_size',
                'size_bytes' => null,
            ];
        }
    }

    private function sqliteDatabaseSize(): ?int
    {
        $pageCount = DB::selectOne('PRAGMA page_count');
        $pageSize = DB::selectOne('PRAGMA page_size');
        $pages = $pageCount?->page_count ?? null;
        $size = $pageSize?->page_size ?? null;

        if (! is_numeric($pages) || ! is_numeric($size)) {
            return null;
        }

        return max(0, (int) $pages * (int) $size);
    }

    private function scalarByteQuery(string $sql): ?int
    {
        $result = DB::selectOne($sql);

        return $this->validByteCount($result?->size_bytes ?? null);
    }

    /**
     * Backup dihitung hanya dari metadata proses backup yang eksplisit.
     * Folder yang kebetulan bernama backup tidak pernah dianggap bukti backup.
     *
     * @return array<string, mixed>
     */
    private function backupCategory(): array
    {
        $metadata = $this->readBackupMetadata();
        $bytes = $this->validByteCount($metadata['total_size_bytes'] ?? null);

        if ($bytes === null) {
            return $this->unavailableCategory('backups', 'Backup');
        }

        $files = $this->validByteCount($metadata['files_count'] ?? null);

        return [
            'key' => 'backups',
            'label' => 'Backup',
            'status' => 'available',
            'bytes' => $bytes,
            'files_count' => $files ?? 0,
            'missing_files_count' => 0,
            'unmeasured_files_count' => 0,
            'is_estimate' => false,
            'is_truncated' => false,
        ];
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

    /**
     * @return array<string, mixed>
     */
    private function unavailableCategory(string $key, string $label): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => 'unavailable',
            'bytes' => 0,
            'files_count' => 0,
            'missing_files_count' => 0,
            'unmeasured_files_count' => 0,
            'is_estimate' => true,
            'is_truncated' => false,
            'tenant_usage' => [],
        ];
    }

    /**
     * @return array<string, int|float|string|null>
     */
    private function unavailableCapacity(): array
    {
        return [
            'status' => 'unavailable',
            'scope' => 'host_volume',
            'total_bytes' => null,
            'used_bytes' => null,
            'available_bytes' => null,
            'used_percent' => null,
            'free_percent' => null,
        ];
    }

    private function isValidSnapshot(mixed $value): bool
    {
        return is_array($value)
            && ($value['schema_version'] ?? null) === 1
            && isset($value['measured_at'], $value['logical_application_files'], $value['host_volume']);
    }
}
