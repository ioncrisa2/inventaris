<?php

namespace App\Http\Middleware;

use App\Services\PlatformMaintenanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAvailable
{
    public function __construct(private PlatformMaintenanceService $maintenanceService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $setting = $this->maintenanceService->setting();

        if (! $this->maintenanceService->isActive($setting)
            || $request->user()?->isSystemOwner()
            || $request->routeIs('login', 'logout')) {
            return $next($request);
        }

        $retryAfter = $setting?->ends_at?->diffInSeconds(now(), true);
        $headers = $retryAfter ? ['Retry-After' => (string) max(1, (int) $retryAfter)] : [];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $setting?->message ?: 'Platform sedang dalam pemeliharaan.',
                'ends_at' => $setting?->ends_at?->toIso8601String(),
            ], 503, $headers);
        }

        return response()->view('maintenance', compact('setting'), 503, $headers);
    }
}
