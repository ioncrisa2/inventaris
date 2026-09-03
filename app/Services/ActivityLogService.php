<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActivityLogService
{
    /** Payload request tidak pernah disimpan; hanya filter aman berikut. */
    private const ALLOWED_FILTERS = [
        'koperasi_id',
        'periode',
        'tanggal_awal',
        'tanggal_mulai',
        'tanggal_selesai',
        'tahun',
        'modul',
        'type',
        'status',
        'priority',
        'assigned_to',
        'date_from',
        'date_to',
    ];

    public function record(Request $request, int $responseStatus, ?User $actor = null): void
    {
        $actor ??= $request->user();

        if (! $actor) {
            return;
        }

        try {
            $filters = collect($request->only(self::ALLOWED_FILTERS))
                ->filter(fn (mixed $value) => is_scalar($value) && filled($value))
                ->map(fn (mixed $value) => mb_substr((string) $value, 0, 100))
                ->all();
            $routeKoperasi = $request->route('koperasi');
            if (is_object($routeKoperasi) && method_exists($routeKoperasi, 'getKey')) {
                $routeKoperasi = $routeKoperasi->getKey();
            }
            if (! isset($filters['koperasi_id']) && is_numeric($routeKoperasi) && (int) $routeKoperasi > 0) {
                $filters['koperasi_id'] = (string) (int) $routeKoperasi;
            }

            $targetKoperasiId = $actor->koperasi_id;
            if ($targetKoperasiId === null
                && isset($filters['koperasi_id'])
                && ctype_digit($filters['koperasi_id'])) {
                $targetKoperasiId = (int) $filters['koperasi_id'];
            }

            ActivityLog::query()->create([
                'actor_user_id' => $actor->getKey(),
                'koperasi_id' => $targetKoperasiId,
                'action' => mb_substr((string) ($request->route()?->getName() ?? 'user.request'), 0, 255),
                'route' => mb_substr($request->method().' /'.$request->path(), 0, 255),
                'response_status' => $responseStatus,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'filters' => $filters ?: null,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Pencatatan activity log gagal.', [
                'exception' => $exception::class,
            ]);
        }
    }
}
