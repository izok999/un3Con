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
}
