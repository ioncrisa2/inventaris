<?php

namespace App\Jobs;

use App\Contracts\VirusScanner;
use App\Exceptions\ScannerUnavailableException;
use App\Models\StoredFile;
use App\Notifications\StoredFileRejected;
use App\Services\MediaPipelineService;
use App\Services\StoredFileAuditService;
use App\Services\TransactionalFileStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ScanStoredFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    public function __construct(public StoredFile $storedFile)
    {
        $this->onQueue('media-scan');
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120, 300, 600, 1200];
    }

    public function handle(
        VirusScanner $scanner,
        MediaPipelineService $pipeline,
        TransactionalFileStorage $storage,
        StoredFileAuditService $audit,
    ): void {
        $file = StoredFile::query()->find($this->storedFile->id);
        if (! $file || $file->scan_status !== 'pending' || $file->status !== 'pending_scan') {
            return;
        }
        if (! $file->staging_disk || ! $file->staging_path) {
            $file->forceFill(['status' => 'failed', 'failure_code' => 'staging_missing'])->save();

            return;
        }

        $stream = Storage::disk($file->staging_disk)->readStream($file->staging_path);
        if (! is_resource($stream)) {
            throw new ScannerUnavailableException('Staging tidak dapat dibaca.');
        }

        try {
            $result = $scanner->scan($stream);
        } catch (ScannerUnavailableException $exception) {
            $file->forceFill(['failure_code' => 'scanner_unavailable'])->save();
            throw $exception;
        } finally {
            fclose($stream);
        }

        if ($result === 'infected') {
            DB::transaction(function () use ($file, $storage, $audit): void {
                $locked = StoredFile::query()->lockForUpdate()->findOrFail($file->id);
                if ($locked->staging_disk && $locked->staging_path) {
                    $storage->deleteAfterCommit($locked->staging_disk, $locked->staging_path);
                }
                $locked->forceFill([
                    'status' => 'infected',
                    'scan_status' => 'infected',
                    'staging_disk' => null,
                    'staging_path' => null,
                    'failure_code' => 'virus_detected',
                    'scanned_at' => now(),
                    'expires_at' => now()->addDays(90),
                ])->save();
                $audit->record($locked, 'scan_rejected', null, null, 'virus_detected');
                $locked->uploader?->notify(new StoredFileRejected($locked->uuid));
            }, 3);

            return;
        }

        DB::transaction(function () use ($file, $audit): void {
            $locked = StoredFile::query()->lockForUpdate()->findOrFail($file->id);
            $locked->forceFill([
                'status' => 'processing',
                'scan_status' => 'clean',
                'failure_code' => null,
                'scanned_at' => now(),
            ])->save();
            $audit->record($locked, 'scan_clean');
        }, 3);
        $pipeline->continue($file->fresh());
    }

    public function failed(?Throwable $exception): void
    {
        StoredFile::query()->whereKey($this->storedFile->id)->where('scan_status', 'pending')->update([
            'failure_code' => 'scanner_unavailable',
            'updated_at' => now(),
        ]);
    }
}
