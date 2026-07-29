<?php

namespace Tests\Feature\Alumno;

use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AlumnoStatsCarreraTest extends TestCase
{
    public function test_stats_muestran_promedio_y_porcentaje_de_aplazos(): void
    {
        $service = $this->mockStatsService(
            promedio: 3.02,
            aplazos: ['aplazos' => 3, 'limite' => 15, 'porcentaje' => 20.0],
        );

        $this->app->instance(AlumnoExternoService::class, $service);

        Livewire::withoutLazyLoading()
            ->test('alumno.detalle-carrera.stats', ['aluId' => 42178, 'halId' => 58655, 'rscId' => 971, 'periodoId' => 20261])
            ->assertSee('Promedio de la carrera')
            ->assertSee('3,02')
            ->assertSee('Aplazos')
            ->assertSee('20,0%')
            ->assertSee('3 de 15 permitidos por la malla')
            ->assertSee('text-success', false)
            ->assertDontSee('Evaluaciones')
            ->assertDontSee('Asistencia');
    }

    /**
     * Sin permiso sobre mcu_aplazo no hay porcentaje: la tarjeta muestra la cantidad.
     * Sin calificaciones tampoco hay promedio: muestra un guion.
     */
    public function test_stats_sin_tope_ni_promedio_muestran_cantidad_y_guion(): void
    {
        $service = $this->mockStatsService(
            promedio: null,
            aplazos: ['aplazos' => 4, 'limite' => null, 'porcentaje' => null],
        );

        $this->app->instance(AlumnoExternoService::class, $service);

        Livewire::withoutLazyLoading()
            ->test('alumno.detalle-carrera.stats', ['aluId' => 42178, 'halId' => 58655, 'rscId' => 971, 'periodoId' => 20261])
            ->assertSet('promedio', null)
            ->assertSet('porcentajeAplazos', null)
            ->assertSee('—')
            ->assertSee('aplazos acumulados · tope no disponible');
    }

    public function test_stats_marcan_en_rojo_cuando_se_supera_el_tope_de_aplazos(): void
    {
        $service = $this->mockStatsService(
            promedio: 2.1,
            aplazos: ['aplazos' => 16, 'limite' => 15, 'porcentaje' => 106.7],
        );

        $this->app->instance(AlumnoExternoService::class, $service);

        Livewire::withoutLazyLoading()
            ->test('alumno.detalle-carrera.stats', ['aluId' => 42178, 'halId' => 58655, 'rscId' => 971, 'periodoId' => 20261])
            ->assertSee('106,7%')
            ->assertSee('16 de 15 permitidos por la malla')
            ->assertSee('text-error', false);
    }

    /**
     * @param  array{aplazos: int, limite: int|null, porcentaje: float|null}  $aplazos
     */
    protected function mockStatsService(?float $promedio, array $aplazos): Mockery\MockInterface
    {
        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('materiasPorHabilitacion')->with(42178, 58655, 971)->andReturn(new Collection);
        $service->shouldReceive('deudasPorHabilitacion')->with(42178, 971, 20261)->andReturn(new Collection);
        $service->shouldReceive('promedioPorHabilitacion')->with(58655)->andReturn($promedio);
        $service->shouldReceive('aplazosPorHabilitacion')->with(42178, 58655)->andReturn($aplazos);

        return $service;
    }
}
