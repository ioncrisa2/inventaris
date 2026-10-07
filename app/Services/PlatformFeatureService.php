<?php

namespace App\Services;

use App\Models\PlatformFeatureAuditLog;
use App\Models\PlatformFeatureSetting;
use App\Models\Koperasi;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class PlatformFeatureService
{
    private array $koperasiOverridesCache = [];

    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        return config('platform_features.features', []);
    }

    public function isEnabled(string $featureKey, ?int $koperasiId = null): bool
    {
        if (! array_key_exists($featureKey, $this->definitions())) {
            return true;
        }

        $globalStatus = $this->statuses()[$featureKey] ?? true;

        if ($koperasiId !== null) {
            $override = $this->getKoperasiOverride($koperasiId, $featureKey);
            if ($override !== null) {
                return $override;
            }
        }

        return $globalStatus;
    }

    private function getKoperasiOverride(int $koperasiId, string $featureKey): ?bool
    {
        if (! array_key_exists($koperasiId, $this->koperasiOverridesCache)) {
            $koperasi = Koperasi::find($koperasiId, ['id', 'feature_overrides']);
            $this->koperasiOverridesCache[$koperasiId] = $koperasi?->feature_overrides ?? [];
        }

        $overrides = $this->koperasiOverridesCache[$koperasiId];
        
        return array_key_exists($featureKey, $overrides) ? (bool) $overrides[$featureKey] : null;
    }

    public function employeeSelfServiceEnabled(?int $koperasiId = null): bool
    {
        $featureKeys = config('platform_features.employee_self_service_features', []);

        // Fail-open bila konfigurasi grup belum tersedia agar deploy parsial
        // tidak memutus pengelolaan akun karyawan.
        if ($featureKeys === []) {
            return true;
        }

        foreach ($featureKeys as $featureKey) {
            if ($this->isEnabled($featureKey, $koperasiId)) {
                return true;
            }
        }

        return false;
    }

    public function featureForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        foreach ($this->definitions() as $key => $definition) {
            if (Str::is($definition['routes'] ?? [], $routeName)) {
                return $key;
            }
        }

        return null;
    }

    /** @return array<string, bool> */
    public function statuses(): array
    {
        $defaults = array_fill_keys(array_keys($this->definitions()), true);

        try {
            if (! Schema::hasTable('platform_feature_settings')) {
                return $defaults;
            }

            return Cache::remember(
                config('platform_features.cache_key', 'platform.features.statuses.v1'),
                now()->addMinutes(5),
                fn () => array_replace(
                    $defaults,
                    PlatformFeatureSetting::query()
                        ->pluck('enabled', 'feature_key')
                        ->map(fn ($enabled) => (bool) $enabled)
                        ->all(),
                ),
            );
        } catch (Throwable) {
            // Fail-open menjaga aplikasi tetap dapat dipulihkan bila deploy
            // kode selesai sebelum migrasi atau cache database tersedia.
            return $defaults;
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function catalog(): array
    {
        $statuses = $this->statuses();
        $settings = Schema::hasTable('platform_feature_settings')
            ? PlatformFeatureSetting::query()->with('updater:id,name')->get()->keyBy('feature_key')
            : collect();

        return collect($this->definitions())
            ->map(function (array $definition, string $key) use ($settings, $statuses) {
                $setting = $settings->get($key);

                return array_merge($definition, [
                    'key' => $key,
                    'enabled' => $statuses[$key] ?? true,
                    'updated_at' => $setting?->updated_at,
                    'updated_by' => $setting?->updater?->name,
                ]);
            })
            ->all();
    }

    public function setEnabled(string $featureKey, bool $enabled, ?User $actor): PlatformFeatureSetting
    {
        if (! array_key_exists($featureKey, $this->definitions())) {
            throw new InvalidArgumentException('Fitur platform tidak dikenal.');
        }

        return DB::transaction(function () use ($featureKey, $enabled, $actor) {
            $setting = PlatformFeatureSetting::query()->lockForUpdate()->find($featureKey)
                ?? new PlatformFeatureSetting(['feature_key' => $featureKey, 'enabled' => true]);
            $changed = (bool) $setting->enabled !== $enabled;

            $setting->forceFill([
                'feature_key' => $featureKey,
                'enabled' => $enabled,
                'updated_by' => $actor?->id,
            ])->save();

            if ($changed) {
                PlatformFeatureAuditLog::query()->create([
                    'feature_key' => $featureKey,
                    'actor_user_id' => $actor?->id,
                    'action' => $enabled ? 'enabled' : 'disabled',
                ]);
            }

            DB::afterCommit(fn () => Cache::forget(
                config('platform_features.cache_key', 'platform.features.statuses.v1'),
            ));

            return $setting;
        }, 3);
    }
}
