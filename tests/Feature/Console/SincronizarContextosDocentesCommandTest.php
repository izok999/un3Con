<?php

namespace Tests\Feature\Console;

use App\Models\Docente;
use App\Models\DocenteContexto;
use App\Services\AlumnoExternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SincronizarContextosDocentesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_contextos_for_docentes_with_documento(): void
    {
        $docente = Docente::query()->create([
            'nombre' => 'Grace Hopper',
            'documento' => '1234567',
            'activo' => true,
        ]);

        $this->mockContextos('1234567', null, [
            ['car_id' => 14, 'sed_id' => 3, 'ple_id' => 68, 'mi2_id' => 6630, 'tur_id' => 2, 'sec_id' => 8],
            ['car_id' => 10, 'sed_id' => 3, 'ple_id' => 68, 'mi2_id' => 5897, 'tur_id' => 3, 'sec_id' => 8],
        ]);

        $this->artisan('evaluacion:sincronizar-contextos')
            ->expectsOutputToContain('Creados: 2')
            ->expectsOutputToContain('Omitidos: 0')
            ->assertSuccessful();

        $this->assertDatabaseHas('docente_contextos', [
            'docente_id' => $docente->id,
            'car_id' => 14,
            'sed_id' => 3,
            'ple_id' => 68,
            'mi2_id' => 6630,
            'tur_id' => 2,
            'sec_id' => 8,
            'activo' => true,
        ]);

        $this->assertDatabaseHas('docente_contextos', [
            'docente_id' => $docente->id,
            'mi2_id' => 5897,
        ]);
    }

    public function test_command_warns_when_no_eligible_docentes(): void
    {
        Docente::query()->create([
            'nombre' => 'Sin Documento',
            'documento' => null,
            'activo' => true,
        ]);

        $this->mock(AlumnoExternoService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('contextosDocentePorDocumento');
        });

        $this->artisan('evaluacion:sincronizar-contextos')
            ->expectsOutputToContain('No hay docentes activos')
            ->assertSuccessful();

        $this->assertDatabaseCount('docente_contextos', 0);
    }

    public function test_command_skips_inactive_docentes(): void
    {
        Docente::query()->create([
            'nombre' => 'Inactivo',
            'documento' => '9999999',
            'activo' => false,
        ]);

        $this->mock(AlumnoExternoService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('contextosDocentePorDocumento');
        });

        $this->artisan('evaluacion:sincronizar-contextos')
            ->expectsOutputToContain('No hay docentes activos')
            ->assertSuccessful();
    }

    public function test_command_skips_duplicate_contextos_without_error(): void
    {
        $docente = Docente::query()->create([
            'nombre' => 'Ada Lovelace',
            'documento' => '7654321',
            'activo' => true,
        ]);

        DocenteContexto::query()->create([
            'docente_id' => $docente->id,
            'car_id' => 14,
            'sed_id' => 3,
            'ple_id' => 68,
            'mi2_id' => 6630,
            'tur_id' => 2,
            'sec_id' => 8,
            'activo' => true,
        ]);

        $this->mockContextos('7654321', null, [
            ['car_id' => 14, 'sed_id' => 3, 'ple_id' => 68, 'mi2_id' => 6630, 'tur_id' => 2, 'sec_id' => 8],
        ]);

        $this->artisan('evaluacion:sincronizar-contextos')
            ->expectsOutputToContain('Omitidos: 1')
            ->expectsOutputToContain('Creados: 0')
            ->assertSuccessful();

        $this->assertDatabaseCount('docente_contextos', 1);
    }

    public function test_command_accepts_periodo_option(): void
    {
        $docente = Docente::query()->create([
            'nombre' => 'Margaret Hamilton',
            'documento' => '1111111',
            'activo' => true,
        ]);

        $this->mockContextos('1111111', '2026', [
            ['car_id' => 9, 'sed_id' => 17, 'ple_id' => 68, 'mi2_id' => 5769, 'tur_id' => 3, 'sec_id' => 1],
        ]);

        $this->artisan('evaluacion:sincronizar-contextos', ['--periodo' => '2026'])
            ->expectsOutputToContain('periodo 2026')
            ->expectsOutputToContain('Creados: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('docente_contextos', [
            'docente_id' => $docente->id,
            'ple_id' => 68,
        ]);
    }

    /**
     * @param  array<int, array<string, int|null>>  $contextos
     */
    protected function mockContextos(string $documento, ?string $pleCodigo, array $contextos): void
    {
        $this->mock(AlumnoExternoService::class, function (MockInterface $mock) use ($documento, $pleCodigo, $contextos): void {
            $mock->shouldReceive('contextosDocentePorDocumento')
                ->once()
                ->with($documento, $pleCodigo)
                ->andReturn(collect($contextos));
        });
    }
}
