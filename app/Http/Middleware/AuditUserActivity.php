<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditUserActivity
{
    public function __construct(private ActivityLogService $activityLog) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldAudit($request)) {
            return $next($request);
        }

        /** @var User|null $actorBefore */
        $actorBefore = $request->user();

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->activityLog->record(
                $request,
                $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500,
                $actorBefore,
            );

            throw $exception;
        }

        $this->activityLog->record($request, $response->getStatusCode(), $actorBefore);

        return $response;
    }

    private function shouldAudit(Request $request): bool
    {
        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        return $request->routeIs(
            '*.download',
            '*.export',
            '*.cetak',
            '*.cetak-*',
            '*.print',
            '*.barcode',
            '*.barcode.*',
            '*.qr-code',
        );
    }
}
