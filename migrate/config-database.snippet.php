<?php

// =============================================================================
// SNIPPET para config/database.php
// =============================================================================
// Aplicar estos dos cambios al archivo existente en el proyecto.
// No reemplazar todo el archivo, solo modificar las secciones indicadas.
// =============================================================================

// ─────────────────────────────────────────────────────────────────────────────
// CAMBIO 1: Conexión 'pgsql' (DB local vía PgBouncer)
// ─────────────────────────────────────────────────────────────────────────────
//
// PgBouncer en pool_mode=transaction NO soporta prepared statements con nombre.
// Sin PDO::ATTR_EMULATE_PREPARES, Eloquent falla con:
//   "ERROR: prepared statement 's0' already exists"
//
// Buscar la key 'pgsql' en el array 'connections' y agregar 'options':

'pgsql' => [
    'driver'         => 'pgsql',
    'url'            => env('DATABASE_URL'),
    'host'           => env('DB_HOST', '127.0.0.1'),
    'port'           => env('DB_PORT', '6432'),        // ← PgBouncer, NO 5432
    'database'       => env('DB_DATABASE', 'consultor_db'),
    'username'       => env('DB_USERNAME'),
    'password'       => env('DB_PASSWORD', ''),
    'charset'        => 'utf8',
    'prefix'         => '',
    'prefix_indexes' => true,
    'search_path'    => 'public',
    'sslmode'        => 'prefer',

    // ↓ CRÍTICO: deshabilitar prepared statements para PgBouncer transaction mode
    'options' => [
        PDO::ATTR_EMULATE_PREPARES => true,
    ],
],

// ─────────────────────────────────────────────────────────────────────────────
// CAMBIO 2: Conexión 'pgsql_externa' (DB legacy UNESYS — solo lectura)
// ─────────────────────────────────────────────────────────────────────────────
//
// Agregar como nueva entrada en el array 'connections'.
// Esta conexión NO pasa por PgBouncer — conecta directo a 10.10.254.252:5432.
// Es de solo lectura (vistas vw_alumnos_00, vw_alumnos_inscriptos_materias_14, etc.)

'pgsql_externa' => [
    'driver'         => 'pgsql',
    'host'           => env('DB_EXTERNA_HOST', '10.10.254.252'),
    'port'           => env('DB_EXTERNA_PORT', '5432'),        // ← directo, sin PgBouncer
    'database'       => env('DB_EXTERNA_DATABASE'),
    'username'       => env('DB_EXTERNA_USERNAME'),
    'password'       => env('DB_EXTERNA_PASSWORD', ''),
    'charset'        => 'utf8',
    'prefix'         => '',
    'prefix_indexes' => true,
    'search_path'    => env('DB_EXTERNA_SCHEMA', 'public'),
    'sslmode'        => 'prefer',

    // Sin PDO::ATTR_EMULATE_PREPARES — conexión directa, no hay PgBouncer
    'options' => [],
],

// =============================================================================
// CÓMO USAR LA CONEXIÓN EXTERNA EN EL CÓDIGO
// =============================================================================
//
// En queries puntuales:
//   DB::connection('pgsql_externa')->table('vw_alumnos_00')->where(...)->get();
//
// En modelos de solo lectura (convenio: carpeta App\Models\Externa\):
//
//   class Alumno extends Model
//   {
//       protected $connection = 'pgsql_externa';
//       protected $table      = 'vw_alumnos_00';
//       public $timestamps    = false;
//       public $incrementing  = false;  // Las vistas no tienen PK autoincremental
//   }
//
// En Eloquent con la conexión explícita:
//   Alumno::on('pgsql_externa')->where('cod_alumno', $cod)->first();
