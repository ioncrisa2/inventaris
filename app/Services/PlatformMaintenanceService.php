<?php

namespace App\Services;

use App\Models\PlatformMaintenanceAuditLog;
use App\Models\PlatformMaintenanceSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PlatformMaintenanceService
{
    private const CACHE_KEY = 'platform.maintenance.status';

    public function setting(): ?PlatformMaintenanceSetting
    {
        try {
            if (! Schema::hasTable('platform_maintenance_settings')) {
                return null;
            }

            $data = Cache::remember(self::CACHE_KEY, 5, fn () => PlatformMaintenanceSetting::query()->find(1)?->toArray());

            return $data ? (new PlatformMaintenanceSetting)->forceFill($data) : null;
        } catch (Throwable) {
            // Fail-open saat deploy sebelum migration tersedia agar middleware
            // tidak membuat seluruh aplikasi tidak dapat dipulihkan.
            return null;
        }
    }

    public function isActive(?PlatformMaintenanceSetting $setting = null): bool
    {
        $setting ??= $this->setting();

        if (! $setting?->enabled) {
            return false;
        }

        $now = now();

        return (! $setting->starts_at || $setting->starts_at->lte($now))
            && (! $setting->ends_at || $setting->ends_at->gt($now));
    }

    /** @param array{message?: ?string, starts_at?: mixed, ends_at?: mixed} $data */
    public function enable(?User $actor, array $data): PlatformMaintenanceSetting
    {
        return DB::transaction(function () use ($actor, $data) {
            $setting = PlatformMaintenanceSetting::query()->lockForUpdate()->find(1)
                ?? new PlatformMaintenanceSetting;
            $setting->id = 1;
            $setting->forceFill([
                'enabled' => true,
                'message' => $data['message'] ?? null,
                'starts_at' => $data['starts_at'] ?? now(),
                'ends_at' => $data['ends_at'] ?? null,
                'enabled_by' => $actor?->id,
                'disabled_by' => null,
            ])->save();

            PlatformMaintenanceAuditLog::query()->create([
                'actor_user_id' => $actor?->id,
                'action' => 'enabled',
                'message' => $setting->message,
                'starts_at' => $setting->starts_at,
                'ends_at' => $setting->ends_at,
            ]);

            DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));

            return $setting;
        }, 3);
    }

    public function disable(?User $actor): PlatformMaintenanceSetting
    {
        return DB::transaction(function () use ($actor) {
            $setting = PlatformMaintenanceSetting::query()->lockForUpdate()->find(1)
                ?? new PlatformMaintenanceSetting;
            $setting->id = 1;
            $setting->forceFill([
                'enabled' => false,
                'disabled_by' => $actor?->id,
            ])->save();

            PlatformMaintenanceAuditLog::query()->create([
                'actor_user_id' => $actor?->id,
                'action' => 'disabled',
                'message' => $setting->message,
                'starts_at' => $setting->starts_at,
                'ends_at' => $setting->ends_at,
            ]);

            DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));

            return $setting;
        }, 3);
    }
}
