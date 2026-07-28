<?php

namespace Tests\Feature\Alumno;

use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Mockery;
use stdClass;
use Tests\TestCase;

class AlumnoAplazosTest extends TestCase
{
    /**
     * El conteo replica la definición legacy de vw_alumnos_aplazos_01: cada fila del
     * extracto con cal_situac = 2, incluidas las repeticiones de una misma materia.
     */
    public function test_aplazos_por_habilitacion_cuenta_situacion_dos_y_calcula_el_porcentaje_del_tope(): void
    {
        $this->seedExtractoCache(42178, 58655, [
            ['mat_descri' => 'ÁLGEBRA', 'cal_situac' => 2],
            ['mat_descri' => 'ÁLGEBRA', 'cal_situac' => 2],
            ['mat_descri' => 'ÁLGEBRA', 'cal_situac' => 1],
            ['mat_descri' => 'FÍSICA', 'cal_situac' => 2],
            ['mat_descri' => 'BASE DE DATOS I', 'cal_situac' => 1],
            ['cur_descri' => 'PRIMER SEMESTRE', 'cur_print' => true],
        ]);

        Cache::put('limite_aplazos_58655', 15, 3600);

        $aplazos = app(AlumnoExternoService::class)->aplazosPorHabilitacion(42178, 58655);

        $this->assertSame(3, $aplazos['aplazos']);
        $this->assertSame(15, $aplazos['limite']);
        $this->assertSame(20.0, $aplazos['porcentaje']);
    }

    public function test_aplazos_por_habilitacion_no_calcula_porcentaje_sin_tope_de_malla(): void
    {
        $this->seedExtractoCache(42178, 58655, [
            ['mat_descri' => 'ÁLGEBRA', 'cal_situac' => 2],
        ]);

        Cache::put('limite_aplazos_58655', 0, 3600);

        $aplazos = app(AlumnoExternoService::class)->aplazosPorHabilitacion(42178, 58655);

        $this->assertSame(1, $aplazos['aplazos']);
        $this->assertNull($aplazos['porcentaje']);
    }

    public function test_extracto_muestra_el_porcentaje_de_aplazos_sobre_el_tope(): void
    {
        $extracto = new stdClass;
        $extracto->mat_descri = 'BASE DE DATOS I';
        $extracto->tev_descri = 'SEGUNDO FINAL';
        $extracto->act_periodo = '2025';
        $extracto->act_fecha = '09/12/2025';
        $extracto->cal_notaci = '5';
        $extracto->cal_situac = 1;

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('extractoImpresionPorHabilitacion')->with(42178, 58655)->andReturn(new Collection([$extracto]));
        $service->shouldReceive('aplazosPorHabilitacion')->with(42178, 58655)->andReturn([
            'aplazos' => 12,
            'limite' => 15,
            'porcentaje' => 80.0,
        ]);

        $this->app->instance(AlumnoExternoService::class, $service);

        Livewire::withoutLazyLoading()
            ->test('alumno.detalle-carrera.extracto', ['aluId' => 42178, 'halId' => 58655])
            ->assertSet('aplazos', 12)
            ->assertSet('limiteAplazos', 15)
            ->assertSet('porcentajeAplazos', 80.0)
            ->assertSee('Aplazos acumulados en la carrera')
            ->assertSee('Tope de la malla')
            ->assertSee('80,0%')
            ->assertSee('progress-warning', false)
            ->assertDontSee('Superaste el tope de aplazos');
    }

    public function test_extracto_avisa_cuando_se_supera_el_tope_de_aplazos(): void
    {
        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('extractoImpresionPorHabilitacion')->with(42178, 58655)->andReturn(new Collection);
        $service->shouldReceive('aplazosPorHabilitacion')->with(42178, 58655)->andReturn([
            'aplazos' => 16,
            'limite' => 15,
            'porcentaje' => 106.7,
        ]);

        $this->app->instance(AlumnoExternoService::class, $service);

        Livewire::withoutLazyLoading()
            ->test('alumno.detalle-carrera.extracto', ['aluId' => 42178, 'halId' => 58655])
            ->assertSee('106,7%')
            ->assertSee('progress-error', false)
            ->assertSee('Superaste el tope de aplazos');
    }

    /**
     * Estar exactamente en el tope todavía no infringe la regla legacy
     * (sp_get_verifica_limite_aplazos compara con >), pero sí se avisa.
     */
    public function test_extracto_avisa_sin_marcar_infraccion_cuando_esta_justo_en_el_tope(): void
    {
        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('extractoImpresionPorHabilitacion')->with(42178, 58655)->andReturn(new Collection);
        $service->shouldReceive('aplazosPorHabilitacion')->with(42178, 58655)->andReturn([
            'aplazos' => 15,
            'limite' => 15,
            'porcentaje' => 100.0,
        ]);

        $this->app->instance(AlumnoExternoService::class, $service);

        Livewire::withoutLazyLoading()
            ->test('alumno.detalle-carrera.extracto', ['aluId' => 42178, 'halId' => 58655])
            ->assertSee('100,0%')
            ->assertSee('progress-warning', false)
            ->assertDontSee('progress-error', false)
            ->assertSee('Alcanzaste el tope de aplazos')
            ->assertDontSee('Superaste el tope de aplazos');
    }

    /**
     * Sin permiso sobre mcu_aplazo el tope llega en null: se muestra la cantidad
     * de aplazos pero no el porcentaje.
     */
    public function test_extracto_muestra_solo_la_cantidad_cuando_no_hay_tope_disponible(): void
    {
        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('extractoImpresionPorHabilitacion')->with(42178, 58655)->andReturn(new Collection);
        $service->shouldReceive('aplazosPorHabilitacion')->with(42178, 58655)->andReturn([
            'aplazos' => 4,
            'limite' => null,
            'porcentaje' => null,
        ]);

        $this->app->instance(AlumnoExternoService::class, $service);

        Livewire::withoutLazyLoading()
            ->test('alumno.detalle-carrera.extracto', ['aluId' => 42178, 'halId' => 58655])
            ->assertSet('aplazos', 4)
            ->assertSet('porcentajeAplazos', null)
            ->assertSee('El tope de aplazos de la malla no está disponible para esta carrera.')
            ->assertDontSee('progress-error', false);
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     */
    protected function seedExtractoCache(int $aluId, int $halId, array $filas): void
    {
        Cache::put("extracto_impresion_{$aluId}_{$halId}", $filas, 900);
    }
}
