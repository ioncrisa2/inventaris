<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Jobs\RunLocalBackupJob;
use App\Services\LocalBackupService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BackupController extends Controller
{
    public function __construct(private LocalBackupService $backupService) {}

    public function index(): View
    {
        $metadata = $this->backupService->readMetadata();

        $status = $metadata['status'] ?? 'none';
        $startedAt = isset($metadata['started_at']) ? Carbon::parse($metadata['started_at']) : null;
        $finishedAt = isset($metadata['finished_at']) ? Carbon::parse($metadata['finished_at']) : null;
        
        $duration = null;
        if ($startedAt && $finishedAt && $status === 'success') {
            $duration = $startedAt->diffInSeconds($finishedAt);
        }

        return view('owner.backup.index', compact('metadata', 'status', 'startedAt', 'finishedAt', 'duration'));
    }

    public function store(): RedirectResponse
    {
        $metadata = $this->backupService->readMetadata();

        if (isset($metadata['status']) && $metadata['status'] === 'running') {
            return redirect()->route('owner.backup.index')->withErrors(['Backup sedang berjalan, harap tunggu hingga selesai.']);
        }

        RunLocalBackupJob::dispatch();

        return redirect()->route('owner.backup.index')->with('success', 'Proses backup telah dimulai di latar belakang.');
    }
}
