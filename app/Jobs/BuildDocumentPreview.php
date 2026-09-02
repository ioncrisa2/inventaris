<?php

namespace App\Jobs;

use App\Models\StoredFile;
use App\Services\StoredFileAuditService;
use App\Services\StoredFileService;
use App\Services\TransactionalFileStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class BuildDocumentPreview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public StoredFile $storedFile)
    {
        $this->onQueue('media-process');
    }

    public function handle(
        StoredFileService $storedFiles,
        TransactionalFileStorage $storage,
        StoredFileAuditService $audit,
    ): void {
        $file = StoredFile::query()->with('variants')->find($this->storedFile->id);
        if (! $file || $file->status === 'ready' || $file->mime_type !== 'application/pdf') {
            return;
        }
        if (! $file->disk || ! $file->path || ! in_array($file->scan_status, ['clean', 'not_required'], true)) {
            return;
        }

        $sourcePath = Storage::disk($file->disk)->path($file->path);
        $info = new Process(['pdfinfo', $sourcePath]);
        $info->setTimeout(30);
        $info->run();
        if (! $info->isSuccessful()) {
            $failure = $this->pdfFailureCode($info->getErrorOutput().' '.$info->getOutput());
            $this->rejectPdf($file, $failure, $storage, $audit);

            return;
        }

        $directory = sys_get_temp_dir().'/pdf-preview-'.$file->uuid;
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Direktori preview PDF tidak dapat dibuat.');
        }
        $outputBase = $directory.'/page';
        $previewPath = $outputBase.'.jpg';

        try {
            $render = new Process([
                'pdftoppm', '-f', '1', '-singlefile', '-jpeg', '-scale-to', '1280', $sourcePath, $outputBase,
            ]);
            $render->setTimeout(60);
            $render->mustRun();
            $dimensions = @getimagesize($previewPath);
            if (! is_array($dimensions)) {
                throw new \RuntimeException('Thumbnail PDF tidak valid.');
            }

            DB::transaction(function () use ($file, $previewPath, $dimensions, $storedFiles, $audit): void {
                $locked = StoredFile::query()->lockForUpdate()->findOrFail($file->id);
                if ($locked->status === 'ready') {
                    return;
                }
                $storedFiles->storeGeneratedVariant(
                    $locked,
                    'preview',
                    $previewPath,
                    'image/jpeg',
                    'jpg',
                    (int) $dimensions[0],
                    (int) $dimensions[1],
                );
                $storedFiles->markReady($locked);
                $audit->record($locked, 'preview_built');
            }, 3);
        } finally {
            if (is_file($previewPath)) {
                @unlink($previewPath);
            }
            @rmdir($directory);
        }
    }

    private function pdfFailureCode(string $output): string
    {
        $output = strtolower($output);

        return str_contains($output, 'password') || str_contains($output, 'encrypted')
            ? 'pdf_password_protected'
            : 'pdf_invalid';
    }

    public function failed(?Throwable $exception): void
    {
        StoredFile::query()->whereKey($this->storedFile->id)->where('status', '<>', 'ready')->update([
            'status' => 'failed',
            'failure_code' => 'pdf_preview_failed',
            'updated_at' => now(),
        ]);
    }

    private function rejectPdf(
        StoredFile $file,
        string $failure,
        TransactionalFileStorage $storage,
        StoredFileAuditService $audit,
    ): void {
        DB::transaction(function () use ($file, $failure, $storage, $audit): void {
            $locked = StoredFile::query()->with('variants')->lockForUpdate()->findOrFail($file->id);
            foreach ($locked->variants as $variant) {
                $storage->deleteAfterCommit($variant->disk, $variant->path);
            }
            $locked->variants()->delete();
            $locked->forceFill([
                'status' => 'failed',
                'disk' => null,
                'path' => null,
                'failure_code' => $failure,
                'expires_at' => now()->addDays(90),
            ])->save();
            $audit->record($locked, 'process_rejected', null, null, $failure);
        }, 3);
    }
}
