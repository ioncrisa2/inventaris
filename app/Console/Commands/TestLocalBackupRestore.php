<?php

namespace App\Console\Commands;

use App\Services\LocalBackupService;
use Illuminate\Console\Command;

class TestLocalBackupRestore extends Command
{
    protected $signature = 'system:backup-restore-test';

    protected $description = 'Memulihkan snapshot terbaru ke direktori sementara dan memverifikasi isinya';

    public function handle(LocalBackupService $backup): int
    {
        try {
            $backup->restoreTest();
            $this->components->info('Uji restore Restic selesai.');

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->components->error('Uji restore Restic gagal.');

            return self::FAILURE;
        }
    }
}
