<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardCache
{
    private const VERSION_KEY = 'dashboard:generation';

    private ?string $version = null;

    public function __construct(private CacheFactory $cache) {}

    public function remember(string $key, Closure $resolver): mixed
    {
        $store = $this->cache->store();
        $version = $this->version($store);

        return $store->remember(
            "dashboard:{$version}:{$key}",
            now()->addSeconds((int) config('inventaris.dashboard_cache_ttl_seconds', 60)),
            $resolver,
        );
    }

    public function invalidateAfterCommit(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->invalidate());

            return;
        }

        $this->invalidate();
    }

    public function invalidate(): void
    {
        $this->version = (string) Str::uuid();
        $this->cache->store()->forever(self::VERSION_KEY, $this->version);
    }

    private function version(CacheRepository $store): string
    {
        if ($this->version !== null) {
            return $this->version;
        }

        $version = $store->get(self::VERSION_KEY);

        if (! is_string($version) || $version === '') {
            $version = (string) Str::uuid();
            $store->forever(self::VERSION_KEY, $version);
        }

        return $this->version = $version;
    }
}
