<?php

namespace App\Services;

use App\Contracts\VirusScanner;
use App\Models\StoredFile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemHealthService
{
    private const CACHE_KEY = 'owner-observability:v1:system-health';

    public function __construct(
        private StorageUsageService $storageUsageService,
        private VirusScanner $virusScanner,
    ) {}
}
