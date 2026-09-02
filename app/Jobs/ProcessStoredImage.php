<?php

namespace App\Jobs;

use App\Models\StoredFile;
use App\Services\StoredFileAuditService;
use App\Services\StoredFileService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessStoredImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public StoredFile $storedFile)
    {
        $this->onQueue('media-process');
    }

    public function handle(StoredFileService $storedFiles, StoredFileAuditService $audit): void
    {
        $file = StoredFile::query()->find($this->storedFile->id);
        if (! $file || $file->status === 'ready') {
            return;
        }
        if (! $file->staging_disk || ! $file->staging_path || ! in_array($file->scan_status, ['clean', 'not_required'], true)) {
            return;
        }

        $sourcePath = Storage::disk($file->staging_disk)->path($file->staging_path);
        $uploaded = new UploadedFile($sourcePath, $file->original_name, $file->mime_type, null, true);
        $prepared = $storedFiles->prepare($uploaded, $file->policy);

        try {
            DB::transaction(function () use ($file, $prepared, $storedFiles, $audit): void {
                $locked = StoredFile::query()->lockForUpdate()->findOrFail($file->id);
                if ($locked->status === 'ready') {
                    return;
                }
                $storedFiles->finalizeStagedImage($locked, $prepared);
                $audit->record($locked, 'process_ready');
            }, 3);
        } finally {
            $prepared->cleanup();
        }
    }

    public function failed(?Throwable $exception): void
    {
        StoredFile::query()->whereKey($this->storedFile->id)->where('status', '<>', 'ready')->update([
            'status' => 'failed',
            'failure_code' => 'image_processing_failed',
            'updated_at' => now(),
        ]);
    }
}
