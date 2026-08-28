<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKoperasiActive
{
    /**
     * Blokir akses kalau koperasi milik user sudah nonaktif/lewat masa
     * aktif. Hanya akun platform yang sah boleh tidak memiliki koperasi;
     * akun tenant tanpa koperasi harus ditolak secara fail-closed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->isPlatformAccount()) {
            return $next($request);
        }

        abort_if(
            $user->koperasi_id === null,
            403,
            'Akun Anda belum terhubung dengan koperasi primer.',
        );

        $koperasi = $user->koperasi;

        abort_if(
            $koperasi === null,
            403,
            'Koperasi primer untuk akun ini tidak tersedia.',
        );

        if ($koperasi->is_active && (! $koperasi->expires_at || ! $koperasi->expires_at->isPast())) {
            return $next($request);
        }

        return redirect()->route('koperasi.expired');
    }
}
