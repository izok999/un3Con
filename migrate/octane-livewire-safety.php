<?php

// =============================================================================
// octane-livewire-safety.php — Guía de prevención de memory leaks
// Octane + Livewire 3 + MaryUI en producción
// =============================================================================
//
// Con PHP tradicional (FPM), cada request es un proceso independiente.
// Con Octane, el mismo proceso maneja MILES de requests consecutivos.
// El estado que no se limpia entre requests se ACUMULA → memory leak.
//
// Este archivo documenta los patrones a seguir y los que causan problemas.
// =============================================================================

// ─────────────────────────────────────────────────────────────────────────────
// ❌ PATRÓN PELIGROSO: Singleton con estado mutable
// ─────────────────────────────────────────────────────────────────────────────

// En AppServiceProvider:
$this->app->singleton(CartService::class, function () {
    return new CartService(); // ← Estado persiste entre requests
});

// CartService acumula datos del request anterior en el siguiente usuario
class CartService
{
    private array $items = []; // ← Este array crece indefinidamente

    public function add(Item $item): void
    {
        $this->items[] = $item;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ✅ SOLUCIÓN A: Usar bind en lugar de singleton (nueva instancia por request)
// ─────────────────────────────────────────────────────────────────────────────

$this->app->bind(CartService::class, fn() => new CartService());

// ─────────────────────────────────────────────────────────────────────────────
// ✅ SOLUCIÓN B: Registrar en octane.flush para reset entre requests
// ─────────────────────────────────────────────────────────────────────────────

// En config/octane.php:
'flush' => [
    CartService::class,
    // Cualquier singleton con estado mutable va acá
],

// ─────────────────────────────────────────────────────────────────────────────
// ✅ SOLUCIÓN C: Limpiar el estado manualmente en el listener
// ─────────────────────────────────────────────────────────────────────────────

// En AppServiceProvider::boot():
use Laravel\Octane\Facades\Octane;

Octane::tick('reset-cart', function () {
    app(CartService::class)->reset();
})->seconds(0); // 0 = entre cada request

// ─────────────────────────────────────────────────────────────────────────────
// Livewire 3 — consideraciones específicas
// ─────────────────────────────────────────────────────────────────────────────

// ✅ Los componentes Livewire son seguros con Octane porque:
//    - Cada request de Livewire instancia el componente fresh desde el estado serializado
//    - Las propiedades se rehidratan desde el snapshot en cada request
//    - No hay estado persistente entre requests en el componente en sí

// ❌ PELIGROSO en componentes Livewire:
class MiComponente extends Component
{
    public static array $cache = []; // ← static persiste entre requests en Octane
}

// ✅ SEGURO:
class MiComponente extends Component
{
    public array $cache = []; // ← instance property, se resetea con el componente
}

// ─────────────────────────────────────────────────────────────────────────────
// Conexiones a la DB — Octane las maneja automáticamente
// ─────────────────────────────────────────────────────────────────────────────

// ✅ Octane desconecta y reconecta la DB automáticamente entre requests
// vía el listener DisconnectFromDatabases (está en el evento OperationTerminated)
// No necesitás hacer nada especial para PgBouncer o la DB externa.

// ─────────────────────────────────────────────────────────────────────────────
// Cache y Redis — seguros con Octane
// ─────────────────────────────────────────────────────────────────────────────

// ✅ Cache::remember(), Cache::put(), Cache::forget() son seguros.
// Redis es stateless por diseño — cada operación es independiente.

// ─────────────────────────────────────────────────────────────────────────────
// Checklist antes de hacer deploy con Octane
// ─────────────────────────────────────────────────────────────────────────────

/*
[ ] No hay propiedades static mutables en clases que se instancian por request
[ ] Los singletons en AppServiceProvider no acumulan estado entre requests
[ ] Los modelos Eloquent no tienen propiedades static con datos de usuario
[ ] Los servicios con estado están en octane.flush o usan bind en lugar de singleton
[ ] La DB externa (pgsql_externa) tiene PDO::ATTR_EMULATE_PREPARES => false (va directo)
[ ] La DB local (pgsql) tiene PDO::ATTR_EMULATE_PREPARES => true (va por PgBouncer)
[ ] max_requests=500 está configurado (reinicio automático como red de seguridad)
*/

// ─────────────────────────────────────────────────────────────────────────────
// Monitorear memory en producción
// ─────────────────────────────────────────────────────────────────────────────

// Ver consumo de memoria de los workers en tiempo real:
//   php artisan octane:status

// Si un worker consume >200MB, bajar max_requests a 200 o buscar el leak
// con php artisan telescope:clear && monitorear en Telescope (solo en staging)
