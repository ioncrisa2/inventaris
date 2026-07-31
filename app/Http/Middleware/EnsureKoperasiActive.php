<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKoperasiActive
{
    /**
     * Blokir akses kalau koperasi milik user sudah nonaktif/lewat masa
     * aktif. User tanpa koperasi (super_admin, atau belum ada koperasi
     * ter-provisioning) tidak terpengaruh sama sekali.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->koperasi_id === null) {
            return $next($request);
        }

        $koperasi = $user->koperasi;

        if ($koperasi && $koperasi->is_active && (! $koperasi->expires_at || ! $koperasi->expires_at->isPast())) {
            return $next($request);
        }

        return redirect()->route('koperasi.expired');
    }
}
