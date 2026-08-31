<?php

namespace App\Http\Middleware;

use App\Services\PlatformFeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformFeatureEnabled
{
    public function __construct(private PlatformFeatureService $featureService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSystemOwner()) {
            return $next($request);
        }

        $featureKey = $this->featureService->featureForRoute($request->route()?->getName());

        if ($featureKey && ! $this->featureService->isEnabled($featureKey)) {
            abort(404);
        }

        return $next($request);
    }
}
