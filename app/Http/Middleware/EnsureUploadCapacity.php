<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUploadCapacity
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->allFiles() === []) {
            return $next($request);
        }

        $disk = (string) config('uploads.capacity.disk', 'local');
        $root = config("filesystems.disks.{$disk}.root");
        $root = is_string($root) ? realpath($root) : false;

        if ($root !== false) {
            $total = @disk_total_space($root);
            $free = @disk_free_space($root);
            if (is_float($total) && is_float($free) && $total > 0) {
                $minimumByPercent = $total * ((float) config('uploads.capacity.emergency_min_free_percent', 5) / 100);
                $minimumBytes = (int) config('uploads.capacity.emergency_min_free_bytes', 2147483648);

                if ($free < max($minimumByPercent, $minimumBytes)) {
                    $message = 'Upload sementara ditolak karena kapasitas penyimpanan server hampir habis.';

                    return $request->expectsJson()
                        ? response()->json(['message' => $message], 507)
                        : response($message, 507, ['Content-Type' => 'text/plain; charset=UTF-8']);
                }
            }
        }

        return $next($request);
    }
}
