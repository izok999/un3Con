<?php

namespace Tests\Feature\Alumno;

use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use stdClass;
use Tests\TestCase;

class AlumnoDeudasPeriodoTest extends TestCase
{
    /**
     * La columna de periodo en vw_alumnos_deudas_saldos_12 es inm_idple; deu_idple no
     * existe, así que filtrar por ella lanzaba excepción y el fallback tampoco aplicaba
     * el periodo (ningún candidato coincidía). El filtro debe usar inm_idple.
     */
    public function test_deudas_por_habilitacion_filtra_el_periodo_con_inm_idple(): void
    {
        $queries = DB::connection('pgsql_externa')->pretend(function (): void {
            app(AlumnoExternoService::class)->deudasPorHabilitacion(42178, 971, 20261);
        });

        $sql = collect($queries)->pluck('query')->implode(' ');

        $this->assertStringContainsString('inm_idple', $sql);
        $this->assertStringNotContainsString('deu_idple', $sql);
        $this->assertContains(20261, collect($queries)->pluck('bindings')->flatten()->all());
    }

    public function test_periodos_con_deudas_lista_periodos_distintos_de_la_carrera(): void
    {
        $queries = DB::connection('pgsql_externa')->pretend(function (): void {
            app(AlumnoExternoService::class)->periodosConDeudas(42178, 971);
        });

        $sql = collect($queries)->pluck('query')->implode(' ');

        $this->assertStringContainsString('vw_alumnos_deudas_saldos_12', $sql);
        $this->assertStringContainsString('distinct', $sql);
        $this->assertStringContainsString('inm_idple', $sql);
    }

    public function test_estado_de_cuenta_arranca_en_el_periodo_vigente_cuando_tiene_deudas(): void
    {
        $service = $this->mockServiceConPeriodos([
            20262 => '2026-2 — SEGUNDO PERIODO 2026',
            20261 => '2026 — PERIODO LECTIVO 2026',
        ]);

        $service->shouldReceive('deudasPorHabilitacion')
            ->once()->with(42178, 971, 20261)
            ->andReturn(new Collection([$this->deuda('Cuota abril')]));

        $this->app->instance(AlumnoExternoService::class, $service);

        Livewire::withoutLazyLoading()
            ->test('alumno.detalle-carrera.deudas', ['aluId' => 42178, 'rscId' => 971, 'periodoId' => 20261])
            ->assertSet('periodoSeleccionado', '20261')
            ->assertSee('data-testid="deudas-periodo-select"', false)
            ->assertSee('Todos los periodos')
            ->assertSee('SEGUNDO PERIODO 2026')
            ->assertSee('Cuota abril');
    }

    /**
     * Si el periodo vigente no registra deudas se muestran todos los periodos, para no
     * esconder saldos viejos detrás de un periodo vacío.
     */
    public function test_estado_de_cuenta_muestra_todos_los_periodos_si_el_vigente_no_tiene_deudas(): void
    {
        $service = $this->mockServiceConPeriodos([20250 => '2025 — PERIODO LECTIVO 2025']);

        $service->shouldReceive('deudasPorHabilitacion')
            ->once()->with(42178, 971, null)
            ->andReturn(new Collection([$this->deuda('Cuota vieja')]));

        $this->app->instance(AlumnoExternoService::class, $service);

        Livewire::withoutLazyLoading()
            ->test('alumno.detalle-carrera.deudas', ['aluId' => 42178, 'rscId' => 971, 'periodoId' => 20261])
            ->assertSet('periodoSeleccionado', '')
            ->assertSee('Cuota vieja');
    }

    public function test_cambiar_el_periodo_reconsulta_las_deudas_de_ese_periodo(): void
    {
        $service = $this->mockServiceConPeriodos([
            20261 => '2026 — PERIODO LECTIVO 2026',
            20250 => '2025 — PERIODO LECTIVO 2025',
        ]);

        $service->shouldReceive('deudasPorHabilitacion')
            ->once()->with(42178, 971, 20261)
            ->andReturn(new Collection([$this->deuda('Cuota abril')]));

        $service->shouldReceive('deudasPorHabilitacion')
            ->once()->with(42178, 971, 20250)
            ->andReturn(new Collection([$this->deuda('Cuota 2025')]));

        $this->app->instance(AlumnoExternoService::class, $service);

        Livewire::withoutLazyLoading()
            ->test('alumno.detalle-carrera.deudas', ['aluId' => 42178, 'rscId' => 971, 'periodoId' => 20261])
            ->assertSee('Cuota abril')
            ->set('periodoSeleccionado', '20250')
            ->assertSee('Cuota 2025')
            ->assertDontSee('Cuota abril');
    }

    public function test_seleccionar_todos_consulta_sin_filtro_de_periodo(): void
    {
        $service = $this->mockServiceConPeriodos([20261 => '2026 — PERIODO LECTIVO 2026']);

        $service->shouldReceive('deudasPorHabilitacion')
            ->once()->with(42178, 971, 20261)
            ->andReturn(new Collection([$this->deuda('Cuota abril')]));

        $service->shouldReceive('deudasPorHabilitacion')
            ->once()->with(42178, 971, null)
            ->andReturn(new Collection([$this->deuda('Cuota abril'), $this->deuda('Cuota 2019')]));

        $this->app->instance(AlumnoExternoService::class, $service);

        Livewire::withoutLazyLoading()
            ->test('alumno.detalle-carrera.deudas', ['aluId' => 42178, 'rscId' => 971, 'periodoId' => 20261])
            ->set('periodoSeleccionado', '')
            ->assertSee('Cuota abril')
            ->assertSee('Cuota 2019');
    }

    /**
     * @param  array<int, string>  $periodos
     */
    protected function mockServiceConPeriodos(array $periodos): Mockery\MockInterface
    {
        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('periodosConDeudas')->with(42178, 971)->andReturn($periodos);
        $service->shouldReceive('pagosAlumno')->with(42178)->andReturn(new Collection);

        return $service;
    }

    protected function deuda(string $concepto): stdClass
    {
        $deuda = new stdClass;
        $deuda->aca_descri = $concepto;
        $deuda->dit_vencim = '30/04/2026';
        $deuda->dit_saldo = 150000;

        return $deuda;
    }
}
