<?php

namespace App\Connectors;

use Illuminate\Database\Connectors\PostgresConnector;
use PDO;

class TimeoutPostgresConnector extends PostgresConnector
{
    /**
     * Establish a database connection with socket-level timeout.
     */
    public function connect(array $config): PDO
    {
        $timeout = $config['connect_timeout'] ?? 60;
        $previous = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', (string) $timeout);

        try {
            return parent::connect($config);
        } finally {
            ini_set('default_socket_timeout', (string) $previous);
        }
    }

    /**
     * Laravel no incluye connect_timeout en el DSN de pgsql, así que libpq
     * nunca lo ve (y default_socket_timeout tampoco le afecta: libpq usa sus
     * propios sockets, no los streams de PHP). Se agrega como parámetro
     * conninfo nativo para que la conexión falle rápido en vez de colgarse
     * hasta el max_execution_time.
     */
    protected function getDsn(array $config)
    {
        $dsn = parent::getDsn($config);

        if (isset($config['connect_timeout'])) {
            $dsn .= ';connect_timeout='.(int) $config['connect_timeout'];
        }

        return $dsn;
    }
}
