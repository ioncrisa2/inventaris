<?php

namespace App\Http\Middleware;

use App\Services\SystemOwnerAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditSystemOwnerAccess
{
    public function __construct(private SystemOwnerAuditService $auditService) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->auditService->record(
                $request,
                $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500,
            );

            throw $exception;
        }

        $this->auditService->record($request, $response->getStatusCode());

        return $response;
    }
}
