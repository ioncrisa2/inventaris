<?php

namespace App\Console\Commands;

use App\Services\LocalBackupService;
use Illuminate\Console\Command;

class CheckLocalBackup extends Command
{
    protected $signature = 'system:backup-check';

    protected $description = 'Memeriksa integritas repository Restic lokal';

    public function handle(LocalBackupService $backup): int
    {
        try {
            $backup->check();
            $this->components->info('Pemeriksaan Restic selesai.');

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->components->error('Pemeriksaan Restic gagal.');

            return self::FAILURE;
        }
    }
}
