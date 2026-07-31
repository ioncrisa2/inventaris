<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsSuperAdmin
{
    /**
     * Kelola koperasi bukan konsep permission per-aksi biasa — tidak masuk
     * akal ada role tenant manapun (termasuk admin_primer) yang diberi izin
     * ini, jadi dikunci ke identitas super_admin langsung, bukan checkbox
     * permission di PermissionCatalog.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return $next($request);
    }
}
