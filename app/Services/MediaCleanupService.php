<?php

namespace App\Services;

use App\Jobs\ScanStoredFile;
use App\Models\StoredFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaCleanupService
{
    public function __construct(private TransactionalFileStorage $storage) {}

    /**
     * @return array{expired:int,terminal:int,orphaned:int,missing:int,requeued:int}
     */
    public function run(bool $dryRun = false, int $chunk = 200): array
    {
        $stats = ['expired' => 0, 'terminal' => 0, 'orphaned' => 0, 'missing' => 0, 'requeued' => 0];

        $this->purgeQuery(
            StoredFile::query()
                ->whereNull('owner_id')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->where('status', '<>', 'infected'),
            'expired',
            $stats,
            $dryRun,
            $chunk,
        );

        $this->purgeQuery(
            StoredFile::query()
                ->whereIn('status', ['failed', 'canceled'])
                ->where('updated_at', '<=', now()->subDay()),
            'terminal',
            $stats,
            $dryRun,
            $chunk,
        );

        $this->purgeQuery(
            StoredFile::query()
                ->where('status', 'infected')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()),
            'terminal',
            $stats,
            $dryRun,
            $chunk,
        );

        StoredFile::query()
            ->whereNotNull('owner_type')
            ->whereNotNull('owner_id')
            ->with(['owner', 'variants'])
            ->chunkById($chunk, function ($files) use (&$stats, $dryRun): void {
                foreach ($files as $file) {
                    try {
                        $ownerMissing = $file->owner === null;
                    } catch (Throwable) {
                        $ownerMissing = true;
                    }
                    if (! $ownerMissing) {
                        continue;
                    }
                    $stats['orphaned']++;
                    if (! $dryRun) {
                        $this->purge($file);
                    }
                }
            });

        StoredFile::query()
            ->where('status', 'ready')
            ->with('variants')
            ->chunkById($chunk, function ($files) use (&$stats, $dryRun): void {
                foreach ($files as $file) {
                    if ($this->hasMissingBytes($file)) {
                        $stats['missing']++;
                        if (! $dryRun) {
                            $file->forceFill([
                                'status' => 'failed',
                                'failure_code' => 'stored_bytes_missing',
                            ])->save();
                        }
                    }
                }
            });

        $stats['requeued'] = $this->requeueStalled($dryRun, $chunk);

        return $stats;
    }

    public function requeueStalled(bool $dryRun = false, int $chunk = 200): int
    {
        $count = 0;
        StoredFile::query()
            ->where('status', 'pending_scan')
            ->where('scan_status', 'pending')
            ->where('updated_at', '<=', now()->subDay())
            ->chunkById($chunk, function ($files) use (&$count, $dryRun): void {
                foreach ($files as $file) {
                    $count++;
                    if ($dryRun) {
                        continue;
                    }
                    $file->forceFill(['failure_code' => 'scan_stalled'])->save();
                    ScanStoredFile::dispatch($file->fresh());
                }
            });

        return $count;
    }

    /**
     * @param  array{expired:int,terminal:int,orphaned:int,missing:int,requeued:int}  $stats
     */
    private function purgeQuery($query, string $key, array &$stats, bool $dryRun, int $chunk): void
    {
        $query->with('variants')->chunkById($chunk, function ($files) use ($key, &$stats, $dryRun): void {
            foreach ($files as $file) {
                $stats[$key]++;
                if (! $dryRun) {
                    $this->purge($file);
                }
            }
        });
    }

    private function purge(StoredFile $file): void
    {
        DB::transaction(function () use ($file): void {
            $locked = StoredFile::query()->with('variants')->lockForUpdate()->find($file->id);
            if (! $locked) {
                return;
            }
            if ($locked->staging_disk && $locked->staging_path) {
                $this->storage->deleteAfterCommit($locked->staging_disk, $locked->staging_path);
            }
            foreach ($locked->variants as $variant) {
                $this->storage->deleteAfterCommit($variant->disk, $variant->path);
            }
            if ($locked->disk && $locked->path && ! $locked->variants->contains('path', $locked->path)) {
                $this->storage->deleteAfterCommit($locked->disk, $locked->path);
            }
            $locked->delete();
        }, 3);
    }

    private function hasMissingBytes(StoredFile $file): bool
    {
        if (! $file->disk || ! $file->path || ! Storage::disk($file->disk)->exists($file->path)) {
            return true;
        }
        foreach ($file->variants as $variant) {
            if (! Storage::disk($variant->disk)->exists($variant->path)) {
                return true;
            }
        }

        return false;
    }
}
