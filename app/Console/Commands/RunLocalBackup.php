<?php

namespace App\Console\Commands;

use App\Services\LocalBackupService;
use Illuminate\Console\Command;

class RunLocalBackup extends Command
{
    protected $signature = 'system:backup';

    protected $description = 'Backup database dan storage aplikasi ke repository Restic lokal';

    public function handle(LocalBackupService $backup): int
    {
        try {
            $backup->backup();
            $this->components->info('Backup Restic selesai.');

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->components->error('Backup Restic gagal; lihat health check dan log service terlindungi.');

            return self::FAILURE;
        }
    }
}
