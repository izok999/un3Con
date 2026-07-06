<?php

// =============================================================================
// config/horizon.php — Configuración de Laravel Horizon para producción
// =============================================================================

return [

    'name' => env('HORIZON_NAME'),

    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', 'consultor_horizon:'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    | Proteger el dashboard con auth. El gate se define en
    | App\Providers\HorizonServiceProvider::gate()
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Waits — Alertar cuando una cola supera N segundos de espera
    |--------------------------------------------------------------------------
    */
    'waits' => [
        'redis:default' => 60,   // alerta si la cola 'default' tiene >60s de backlog
        'redis:emails' => 30,
        'redis:reports' => 120,
        'redis:critical' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trimming — Cuánto tiempo conservar jobs en el historial de Horizon
    |--------------------------------------------------------------------------
    */
    'trim' => [
        'recent' => 60,    // minutos
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080, // 7 días
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs — Jobs que no aparecen en el dashboard (muy frecuentes)
    |--------------------------------------------------------------------------
    */
    'silenced' => [
        // App\Jobs\SendPingJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Métricas — Cuántas muestras conservar
    |--------------------------------------------------------------------------
    */
    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    */
    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB) — Horizon reinicia el worker si supera este límite
    |--------------------------------------------------------------------------
    */
    'memory_limit' => 256,

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    | Configuración por entorno. En producción:
    |
    | Colas definidas (de mayor a menor prioridad):
    |   critical  → pagos Bancard, operaciones bloqueantes para el usuario
    |   default   → acciones generales del sistema
    |   emails    → notificaciones, correos
    |   reports   → exportaciones y reportes pesados (pueden esperar)
    |
    | balance=auto → Horizon distribuye workers según la carga real de cada cola.
    | Escala entre minProcesses y maxProcesses automáticamente.
    */
    'environments' => [

        'production' => [

            // Supervisor 1: colas de alta prioridad (tiempo real)
            'supervisor-critical' => [
                'connection' => 'redis',
                'queue' => ['critical', 'default'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'minProcesses' => 1,
                'maxProcesses' => 6,        // ajustar según núcleos del servidor
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 3,
                'timeout' => 90,       // segundos antes de matar el job
                'memory' => 256,
                'sleep' => 0,
                'force' => false,
            ],

            // Supervisor 2: colas lentas (emails, reportes)
            'supervisor-slow' => [
                'connection' => 'redis',
                'queue' => ['emails', 'reports'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'size',
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 5,
                'tries' => 2,
                'timeout' => 300,      // reportes pueden tardar hasta 5 min
                'memory' => 256,
                'sleep' => 3,
                'force' => false,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'simple',
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'tries' => 1,
                'timeout' => 60,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watcher Configuration
    |--------------------------------------------------------------------------
    */
    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],

];
