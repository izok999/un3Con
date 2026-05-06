<?php

namespace Tests\Unit;

use Tests\TestCase;

class ExternalLegacyAuthConfigTest extends TestCase
{
    public function test_pgsql_externa_search_path_includes_required_legacy_schemas(): void
    {
        $searchPath = (string) config('database.connections.pgsql_externa.search_path');

        $this->assertStringContainsString('sh_movimientos', $searchPath);
        $this->assertStringContainsString('sh_maestros', $searchPath);
        $this->assertStringContainsString('public', $searchPath);
    }
}
