<?php

namespace App\Providers;

use App\Connectors\TimeoutPostgresConnector;
use Illuminate\Database\Connection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('db.connector.pgsql', TimeoutPostgresConnector::class);

        // Con PDO::ATTR_EMULATE_PREPARES=true (requerido por PgBouncer en modo
        // transaction), los bindings se interpolan como literales SQL.
        // Connection::prepareBindings() castea los booleanos PHP a (int), y
        // Postgres no convierte implícitamente integer -> boolean (a diferencia
        // de MySQL/SQLite), rompiendo cualquier insert/update sobre columnas
        // boolean. Se reescriben como 'true'/'false', que Postgres sí acepta.
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new class($connection, $database, $prefix, $config) extends PostgresConnection
            {
                public function prepareBindings(array $bindings)
                {
                    $grammar = $this->getQueryGrammar();

                    foreach ($bindings as $key => $value) {
                        if ($value instanceof \DateTimeInterface) {
                            $bindings[$key] = $value->format($grammar->getDateFormat());
                        } elseif (is_bool($value)) {
                            $bindings[$key] = $value ? 'true' : 'false';
                        }
                    }

                    return $bindings;
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // En producción la app vive detrás de Nginx (y eventualmente bajo el
        // subpath /consultor), así que Laravel no puede deducir su URL raíz del
        // Host de la petición. Se fuerza desde APP_URL; el scheme solo se fuerza
        // a https si APP_URL lo usa (permite probar por http://IP sin TLS).
        if ($this->app->environment('production')) {
            URL::forceRootUrl(config('app.url'));

            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
