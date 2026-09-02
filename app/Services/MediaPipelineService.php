<?php

namespace App\Services;

use App\Jobs\BuildDocumentPreview;
use App\Jobs\ProcessCalendarImport;
use App\Jobs\ProcessStoredImage;
use App\Models\StoredFile;
use Illuminate\Support\Facades\DB;

class MediaPipelineService
{
    public function __construct(private StoredFileService $storedFiles) {}

    public function continue(StoredFile $file): void
    {
        $file->refresh();
        if (in_array($file->status, ['ready', 'infected', 'failed', 'canceled'], true)) {
            return;
        }
        if (! in_array($file->scan_status, ['clean', 'not_required'], true)) {
            return;
        }

        if (in_array($file->policy, ['employee_photo', 'asset_photo', 'asset_gallery', 'logo'], true)) {
            ProcessStoredImage::dispatch($file);

            return;
        }
        if ($file->policy === 'calendar_import') {
            if ($file->owner_id === null) {
                return;
            }
            ProcessCalendarImport::dispatch($file);

            return;
        }

        $isPdf = $file->mime_type === 'application/pdf';
        DB::transaction(function () use ($file, $isPdf): void {
            $locked = StoredFile::query()->lockForUpdate()->findOrFail($file->id);
            if ($locked->staging_path) {
                $this->storedFiles->finalizeStagedDocument($locked, ! $isPdf);
            }
        }, 3);

        if ($isPdf) {
            BuildDocumentPreview::dispatch($file->fresh());
        }
    }
}
