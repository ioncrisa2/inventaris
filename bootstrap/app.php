<?php

use App\Http\Middleware\EnsureIsSuperAdmin;
use App\Http\Middleware\EnsureIsSystemOwner;
use App\Http\Middleware\EnsureKoperasiActive;
use App\Http\Middleware\EnsurePlatformAvailable;
use App\Http\Middleware\EnsurePlatformFeatureEnabled;
use App\Http\Middleware\EnsureUploadCapacity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            EnsurePlatformAvailable::class,
            EnsurePlatformFeatureEnabled::class,
            EnsureUploadCapacity::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'koperasi.active' => EnsureKoperasiActive::class,
            'super_admin' => EnsureIsSuperAdmin::class,
            'system_owner' => EnsureIsSystemOwner::class,
            'upload.capacity' => EnsureUploadCapacity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            $message = 'Ukuran unggahan melebihi batas server. Maksimal 20 MB per file dan 64 MB per permintaan.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return response($message, 413, ['Content-Type' => 'text/plain; charset=UTF-8']);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->is('uploads')
                || $request->is('uploads/*')
                || $request->expectsJson(),
        );
    })->create();
