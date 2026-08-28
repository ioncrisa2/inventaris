<?php

namespace App\Services;

use App\Models\SystemOwnerAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SystemOwnerAuditService
{
    /**
     * Hanya filter analitik yang tidak sensitif yang boleh masuk audit log.
     * Hasil agregat dan seluruh payload request sengaja tidak pernah disimpan.
     */
    private const ALLOWED_FILTERS = [
        'koperasi_id',
        'periode',
        'tanggal_awal',
        'tanggal_mulai',
        'tanggal_selesai',
        'tahun',
        'modul',
    ];

    public function record(Request $request, int $responseStatus): void
    {
        $actor = $request->user();

        if (! $actor?->isSystemOwner()) {
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

            SystemOwnerAuditLog::query()->create([
                'actor_user_id' => $actor->getKey(),
                'koperasi_id' => isset($filters['koperasi_id']) && ctype_digit($filters['koperasi_id'])
                    ? (int) $filters['koperasi_id']
                    : null,
                'action' => mb_substr((string) ($request->route()?->getName() ?? 'owner.request'), 0, 255),
                'route' => mb_substr($request->method().' /'.$request->path(), 0, 255),
                'response_status' => $responseStatus,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'filters' => $filters ?: null,
            ]);
        } catch (Throwable $exception) {
            // Audit observability tidak boleh menjatuhkan halaman owner. Pesan
            // dibatasi pada tipe exception agar query/credential tidak bocor.
            Log::warning('Pencatatan audit system owner gagal.', [
                'exception' => $exception::class,
            ]);
        }
    }
}
