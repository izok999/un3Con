<?php

// =============================================================================
// config/octane.php — Configuración de Laravel Octane (FrankenPHP)
// Reemplazar el archivo publicado por: php artisan octane:install --server=frankenphp
// =============================================================================

return [

    /*
    |--------------------------------------------------------------------------
    | Octane Server
    |--------------------------------------------------------------------------
    */
    'server' => env('OCTANE_SERVER', 'frankenphp'),

    /*
    |--------------------------------------------------------------------------
    | FrankenPHP Options
    |--------------------------------------------------------------------------
    | https termina en Cloudflare Tunnel — FrankenPHP recibe HTTP plano.
    | Nginx escucha en :80/:443 y hace proxy a FrankenPHP en :8000.
    */
    'frankenphp' => [
        'https' => env('OCTANE_HTTPS', false),
        'http2' => env('OCTANE_HTTP2', false),
        'num_threads' => null,    // null = auto (núcleos × 2)
        'worker_count' => null,   // null = controlado por workers abajo
    ],

    /*
    |--------------------------------------------------------------------------
    | Workers
    |--------------------------------------------------------------------------
    | 'auto' = núcleos del servidor.
    | En producción con varios núcleos, auto es el valor correcto.
    | Ajustar manualmente si la app es muy memory-heavy (bajar) o I/O-heavy (subir).
    */
    'workers' => env('OCTANE_WORKERS', 'auto'),
    'task_workers' => env('OCTANE_TASK_WORKERS', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Max Requests
    |--------------------------------------------------------------------------
    | El worker se reinicia luego de este número de requests.
    | Previene memory leaks acumulativos.
    | 500 es un buen balance entre estabilidad y overhead de reinicio.
    */
    'max_requests' => env('OCTANE_MAX_REQUESTS', 500),

    /*
    |--------------------------------------------------------------------------
    | Garbage Collection Threshold
    |--------------------------------------------------------------------------
    */
    'garbage' => 50,

    /*
    |--------------------------------------------------------------------------
    | Warm — Clases a resolver del container antes de recibir requests.
    | Acelera el primer request de cada worker.
    |--------------------------------------------------------------------------
    */
    'warm' => [
        ...Octane::defaultServicesToWarm(),
        // Agregar servicios custom que se usan en casi todos los requests:
        // App\Services\BancardService::class,
        // App\Services\UnisysService::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Flush — Instancias que se reinician entre requests (evita state leakage).
    | CRÍTICO con Livewire 3: cualquier singleton con estado debe estar aquí.
    |--------------------------------------------------------------------------
    */
    'flush' => [
        // Ejemplo: si usás un servicio con estado interno:
        // App\Services\CartService::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Listeners de eventos del ciclo de vida de Octane
    |--------------------------------------------------------------------------
    */
    'listeners' => [
        WorkerStarting::class => [
            EnsureUploadedFilesAreValid::class,
            EnsureUploadedFilesCanBeValidated::class,
        ],

        RequestReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
            ...Octane::prepareApplicationForNextRequest(),
            //
        ],

        RequestHandled::class => [
            //
        ],

        RequestTerminated::class => [
            // FlushUploadedFiles::class,
        ],

        TaskReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
            //
        ],

        TaskTerminated::class => [
            //
        ],

        OperationTerminated::class => [
            FlushTemporaryContainerInstances::class,
            // DisconnectFromDatabases::class,
            CollectGarbage::class,
        ],

        WorkerErrorOccurred::class => [
            ReportException::class,
            StopWorkerIfNecessary::class,
        ],

        WorkerStopping::class => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tablas en memoria compartida entre workers (Swoole solamente)
    |--------------------------------------------------------------------------
    */
    'tables' => [
        // 'example:1000' => [
        //     'name'  => Table::TYPE_STRING,
        //     'votes' => Table::TYPE_INT,
        // ],
    ],

];

// =============================================================================
// IMPORTS necesarios al inicio del archivo (agregar debajo de <?php):
// =============================================================================
/*
use Laravel\Octane\Contracts\OperationTerminated;
use Laravel\Octane\Events\RequestHandled;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\TaskTerminated;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Laravel\Octane\Events\WorkerStarting;
use Laravel\Octane\Events\WorkerStopping;
use Laravel\Octane\Listeners\CollectGarbage;
use Laravel\Octane\Listeners\DisconnectFromDatabases;
use Laravel\Octane\Listeners\EnsureUploadedFilesAreValid;
use Laravel\Octane\Listeners\EnsureUploadedFilesCanBeValidated;
use Laravel\Octane\Listeners\FlushTemporaryContainerInstances;
use Laravel\Octane\Listeners\FlushUploadedFiles;
use Laravel\Octane\Listeners\ReportException;
use Laravel\Octane\Listeners\StopWorkerIfNecessary;
use Laravel\Octane\Octane;
*/
