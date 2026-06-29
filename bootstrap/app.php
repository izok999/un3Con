<?php

use App\Http\Middleware\EnsureAcademicUnitScope;
use App\Http\Middleware\EnsureLegacyUserHasCompletedAccount;
use App\Http\Middleware\EnsureOAuthUserHasDocumento;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
    ->withMiddleware(function (Middleware $middleware): void {
        // TRUSTED PROXIES — Nginx en localhost, Cloudflare Tunnel
        // Sin esto Request::ip() devuelve 127.0.0.1, las URLs usan http://
        $middleware->trustProxies(
            at: [
                '127.0.0.1',
                '::1',
            ],
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_PREFIX
        );

        // TRUSTED HOSTS — previene HTTP Host Header injection
        $middleware->trustHosts(at: [
            'www.une.edu.py',
        ]);

        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'academic.unit.scope' => EnsureAcademicUnitScope::class,
            'legacy.account.complete' => EnsureLegacyUserHasCompletedAccount::class,
            'oauth.documento' => EnsureOAuthUserHasDocumento::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
