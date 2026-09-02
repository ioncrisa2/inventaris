<?php

namespace App\Services;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class LocalBackupService
{
    /** @return array<string,mixed> */
    public function backup(): array
    {
        $this->writeMetadata(['status' => 'running', 'started_at' => now()->toIso8601String()]);
        $temporary = $this->temporaryDirectory('inventaris-backup-');
        $dumpPath = $temporary.'/database.sql';

        try {
            $this->ensureRepository();
            $this->dumpDatabase($dumpPath);
            $storagePath = $this->storagePath();
            $process = $this->restic([
                'backup', $dumpPath, $storagePath,
                '--tag', 'inventaris-daily',
                '--json',
            ]);
            $summary = $this->backupSummary($process->getOutput());
            $this->restic([
                'forget', '--tag', 'inventaris-daily',
                '--keep-daily', (string) max(1, (int) config('backup.keep_daily', 7)),
                '--keep-weekly', (string) max(1, (int) config('backup.keep_weekly', 4)),
                '--prune',
            ]);

            $metadata = [
                'status' => 'success',
                'completed_at' => now()->toIso8601String(),
                'size_bytes' => $summary['size_bytes'],
                'files_count' => $summary['files_count'],
                'snapshot_id' => $summary['snapshot_id'],
            ];
            $this->writeMetadata($metadata);

            return $metadata;
        } catch (Throwable $exception) {
            $this->writeMetadata(['status' => 'failed', 'completed_at' => now()->toIso8601String()]);
            throw new \RuntimeException('Backup lokal gagal. Periksa log service backup yang terlindungi.', 0, $exception);
        } finally {
            $this->removeTemporaryDirectory($temporary);
        }
    }

    public function check(): bool
    {
        try {
            $this->ensureRepository();
            $this->restic(['check']);
            $this->mergeMetadata([
                'restic_check_status' => 'success',
                'restic_checked_at' => now()->toIso8601String(),
            ]);

            return true;
        } catch (Throwable $exception) {
            $this->mergeMetadata([
                'restic_check_status' => 'failed',
                'restic_checked_at' => now()->toIso8601String(),
            ]);
            throw new \RuntimeException('Pemeriksaan repository backup gagal.', 0, $exception);
        }
    }

    public function restoreTest(): bool
    {
        $temporary = $this->temporaryDirectory('inventaris-restore-');
        try {
            $this->ensureRepository();
            $this->restic(['restore', 'latest', '--tag', 'inventaris-daily', '--target', $temporary]);
            $databaseFound = false;
            $storageFound = false;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($temporary, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($iterator as $item) {
                if ($item->isFile() && $item->getFilename() === 'database.sql' && $item->getSize() > 0) {
                    $databaseFound = true;
                }
                if ($item->isDir() && $item->getFilename() === 'app' && str_contains($item->getPathname(), '/storage/app')) {
                    $storageFound = true;
                }
            }
            if (! $databaseFound || ! $storageFound) {
                throw new \RuntimeException('Isi hasil restore tidak lengkap.');
            }
            $this->mergeMetadata([
                'restore_test_status' => 'success',
                'restore_tested_at' => now()->toIso8601String(),
            ]);

            return true;
        } catch (Throwable $exception) {
            $this->mergeMetadata([
                'restore_test_status' => 'failed',
                'restore_tested_at' => now()->toIso8601String(),
            ]);
            throw new \RuntimeException('Uji restore backup gagal.', 0, $exception);
        } finally {
            $this->removeTemporaryDirectory($temporary);
        }
    }

    private function ensureRepository(): void
    {
        $probe = $this->restic(['snapshots', '--json'], false);
        if ($probe->isSuccessful()) {
            return;
        }
        $this->restic(['init']);
    }

    private function dumpDatabase(string $target): void
    {
        $driver = (string) config('database.default');
        $connection = config("database.connections.{$driver}");
        if (! is_array($connection) || ! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new \RuntimeException('Backup database saat ini hanya mendukung MySQL/MariaDB.');
        }

        $process = new Process([
            'mariadb-dump',
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--host='.(string) ($connection['host'] ?? 'db'),
            '--port='.(string) ($connection['port'] ?? 3306),
            '--user='.(string) ($connection['username'] ?? ''),
            '--result-file='.$target,
            (string) ($connection['database'] ?? ''),
        ], null, ['MYSQL_PWD' => (string) ($connection['password'] ?? '')]);
        $process->setTimeout($this->timeout());
        $process->mustRun();
        if (! is_file($target) || filesize($target) === 0) {
            throw new \RuntimeException('Dump database kosong.');
        }
    }

    private function restic(array $arguments, bool $mustSucceed = true): Process
    {
        $process = new Process(['restic', ...$arguments], null, $this->resticEnvironment());
        $process->setTimeout($this->timeout());
        $process->run();
        if ($mustSucceed && ! $process->isSuccessful()) {
            throw new \RuntimeException('Perintah Restic gagal.');
        }

        return $process;
    }

    /** @return array{size_bytes:?int,files_count:?int,snapshot_id:?string} */
    private function backupSummary(string $output): array
    {
        $summary = ['size_bytes' => null, 'files_count' => null, 'snapshot_id' => null];
        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            try {
                $item = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                continue;
            }
            if (! is_array($item) || ($item['message_type'] ?? null) !== 'summary') {
                continue;
            }
            $summary['size_bytes'] = isset($item['total_bytes_processed']) ? (int) $item['total_bytes_processed'] : null;
            $summary['files_count'] = isset($item['total_files_processed']) ? (int) $item['total_files_processed'] : null;
            $snapshot = $item['snapshot_id'] ?? null;
            $summary['snapshot_id'] = is_string($snapshot) ? substr($snapshot, 0, 64) : null;
        }

        return $summary;
    }

    /** @return array<string,string> */
    private function resticEnvironment(): array
    {
        $repository = config('backup.repository');
        $password = config('backup.password');
        $passwordFile = config('backup.password_file');
        if (! is_string($repository) || $repository === '') {
            throw new \RuntimeException('RESTIC_REPOSITORY belum dikonfigurasi.');
        }
        if ((! is_string($password) || $password === '') && (! is_string($passwordFile) || $passwordFile === '')) {
            throw new \RuntimeException('Password Restic belum dikonfigurasi.');
        }

        $environment = ['RESTIC_REPOSITORY' => $repository];
        if (is_string($passwordFile) && $passwordFile !== '') {
            $environment['RESTIC_PASSWORD_FILE'] = $passwordFile;
        } else {
            $environment['RESTIC_PASSWORD'] = (string) $password;
        }

        return $environment;
    }

    private function storagePath(): string
    {
        $path = config('backup.storage_path');
        if (! is_string($path) || ! is_dir($path)) {
            throw new \RuntimeException('Direktori storage untuk backup tidak tersedia.');
        }

        return $path;
    }

    private function timeout(): int
    {
        return max(60, (int) config('backup.timeout_seconds', 3600));
    }

    private function temporaryDirectory(string $prefix): string
    {
        $path = rtrim(sys_get_temp_dir(), '/').'/'.$prefix.Str::random(20);
        if (! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new \RuntimeException('Direktori sementara backup tidak dapat dibuat.');
        }

        return $path;
    }

    private function removeTemporaryDirectory(string $path): void
    {
        $prefix = rtrim(sys_get_temp_dir(), '/').'/inventaris-';
        if (! str_starts_with($path, $prefix) || ! is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }

    /** @param array<string,mixed> $metadata */
    private function writeMetadata(array $metadata): void
    {
        $path = (string) config('backup.metadata_path');
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Direktori metadata backup tidak dapat dibuat.');
        }
        $temporary = $path.'.'.Str::random(12).'.tmp';
        $encoded = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($temporary, $encoded, LOCK_EX) === false || ! rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('Metadata backup tidak dapat ditulis.');
        }
        @chmod($path, 0600);
    }

    /** @param array<string,mixed> $changes */
    private function mergeMetadata(array $changes): void
    {
        $path = (string) config('backup.metadata_path');
        $current = [];
        if (is_file($path)) {
            try {
                $decoded = json_decode((string) file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
                $current = is_array($decoded) ? $decoded : [];
            } catch (Throwable) {
                $current = [];
            }
        }
        $this->writeMetadata(array_merge($current, $changes));
    }
}
