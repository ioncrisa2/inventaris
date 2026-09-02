<?php

namespace App\Jobs;

use App\Models\StoredFile;
use App\Services\HariLiburService;
use App\Services\StoredFileAuditService;
use App\Services\StoredFileService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessCalendarImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public StoredFile $storedFile)
    {
        $this->onQueue('media-process');
    }

    public function handle(
        StoredFileService $storedFiles,
        HariLiburService $hariLibur,
        StoredFileAuditService $audit,
    ): void {
        $file = StoredFile::query()->find($this->storedFile->id);
        if (! $file || $file->status === 'ready') {
            return;
        }
        if (! in_array($file->scan_status, ['clean', 'not_required'], true)) {
            return;
        }

        if ($file->staging_path) {
            DB::transaction(function () use ($file, $storedFiles): void {
                $locked = StoredFile::query()->lockForUpdate()->findOrFail($file->id);
                $storedFiles->finalizeStagedDocument($locked, false);
            }, 3);
            $file->refresh();
        }

        $actor = $file->uploader;
        if (! $actor || ! $file->disk || ! $file->path) {
            throw new \RuntimeException('Actor atau file impor tidak tersedia.');
        }

        $previous = Auth::user();
        Auth::setUser($actor);
        try {
            $uploaded = new UploadedFile(
                Storage::disk($file->disk)->path($file->path),
                $file->original_name,
                $file->mime_type,
                null,
                true,
            );
            $result = $hariLibur->import($uploaded);
        } finally {
            $previous ? Auth::setUser($previous) : Auth::forgetGuards();
        }

        DB::transaction(function () use ($file, $result, $storedFiles, $audit): void {
            $locked = StoredFile::query()->lockForUpdate()->findOrFail($file->id);
            $storedFiles->markReady($locked, ['import_result' => $result]);
            $audit->record($locked, 'calendar_imported', null, null, json_encode($result));
        }, 3);
    }

    public function failed(?Throwable $exception): void
    {
        StoredFile::query()->whereKey($this->storedFile->id)->where('status', '<>', 'ready')->update([
            'status' => 'failed',
            'failure_code' => 'calendar_import_failed',
            'updated_at' => now(),
        ]);
    }
}
