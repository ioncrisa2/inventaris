<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RecordSchedulerHeartbeat extends Command
{
    protected $signature = 'system:heartbeat';

    protected $description = 'Record a sanitized heartbeat for the system owner health dashboard';

    public function handle(): int
    {
        try {
            Cache::put(
                (string) config('owner_observability.scheduler.cache_key'),
                [
                    'schema_version' => 1,
                    'recorded_at' => now()->timestamp,
                ],
                max(60, (int) config('owner_observability.scheduler.heartbeat_ttl_seconds', 172800)),
            );
        } catch (Throwable) {
            $this->components->error('Scheduler heartbeat could not be recorded.');

            return self::FAILURE;
        }

        $this->components->info('Scheduler heartbeat recorded.');

        return self::SUCCESS;
    }
}
