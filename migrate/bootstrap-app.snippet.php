<?php

use Illuminate\Http\Request;

// =============================================================================
// SNIPPET para bootstrap/app.php (Laravel 11+)
// =============================================================================
// Sin esto, Request::ip(), Request::secure() y Request::url() devuelven
// valores incorrectos porque Laravel ve como origen a Nginx (127.0.0.1)
// en lugar del IP real del usuario que viene por Cloudflare Tunnel.
//
// SÍNTOMA sin este fix:
//   - $request->ip() siempre devuelve 127.0.0.1
//   - Las URLs generadas usan http:// en lugar de https://
//   - Los rate limiters no funcionan por IP real
//   - Las cookies secure no se setean correctamente
// =============================================================================

// ─────────────────────────────────────────────────────────────────────────────
// Reemplazar el return Application::configure(...) existente con esto:
// ─────────────────────────────────────────────────────────────────────────────

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ─────────────────────────────────────────────────────────────────────
        // TRUSTED PROXIES
        // ─────────────────────────────────────────────────────────────────────
        // Nginx corre en localhost — es el único proxy que debe ser confiable.
        // Cloudflare Tunnel también entra por localhost (127.0.0.1).
        // El header X-Forwarded-For que viene de Nginx contiene el IP real.
        $middleware->trustProxies(
            at: [
                '127.0.0.1',
                '::1',
            ],
            headers: // Nginx setea estos headers (ver consultor.conf):
                Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_PREFIX
        );

        // ─────────────────────────────────────────────────────────────────────
        // TRUSTED HOSTS
        // ─────────────────────────────────────────────────────────────────────
        // Previene HTTP Host Header injection attacks
        $middleware->trustHosts(at: [
            'www.une.edu.py',
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // Middleware Livewire (si no está ya configurado)
        // ─────────────────────────────────────────────────────────────────────
        // $middleware->web(append: [
        //     \Livewire\Middlewares\ResumeUserSession::class,
        // ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// =============================================================================
// VERIFICACIÓN post-deploy
// =============================================================================
// Desde tinker, verificar que los proxies funcionan:
//
//   php artisan tinker
//   >>> request()->ip()          // debe devolver el IP real del cliente, no 127.0.0.1
//   >>> request()->secure()      // debe devolver true (viene de Cloudflare HTTPS)
//   >>> url('/')                 // debe devolver https://www.une.edu.py/consultor/
//
// Si sigue devolviendo 127.0.0.1, verificar que Nginx está seteando
// X-Forwarded-For correctamente (está en consultor.conf).
// =============================================================================
