<?php

namespace App\Console\Commands;

use App\Services\PlatformMaintenanceService;
use Illuminate\Console\Command;

class PlatformMaintenance extends Command
{
    protected $signature = 'platform:maintenance
        {action : enable, disable, atau status}
        {--message= : Pesan maintenance}
        {--until= : Waktu selesai (format tanggal yang valid)}';

    protected $description = 'Kelola soft maintenance platform sebagai jalur pemulihan server';

    public function handle(PlatformMaintenanceService $service): int
    {
        $action = strtolower((string) $this->argument('action'));

        if ($action === 'status') {
            $setting = $service->setting();
            $this->line($service->isActive($setting) ? 'ACTIVE' : 'INACTIVE');

            return self::SUCCESS;
        }

        if ($action === 'enable') {
            $service->enable(null, [
                'message' => $this->option('message'),
                'starts_at' => now(),
                'ends_at' => $this->option('until'),
            ]);
            $this->info('Soft maintenance aktif.');

            return self::SUCCESS;
        }

        if ($action === 'disable') {
            $service->disable(null);
            $this->info('Soft maintenance nonaktif.');

            return self::SUCCESS;
        }

        $this->error('Action harus enable, disable, atau status.');

        return self::INVALID;
    }
}
