<?php

namespace Tests\Feature\Alumno;

use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlumnoExtractoQueryTest extends TestCase
{
    /**
     * La vista de sh_movimientos no expone aci_idhal, así que ese filtro siempre fallaba y
     * caía al fallback que trae el extracto completo del alumno (~50 s). El filtro correcto
     * es hal_id sobre la vista equivalente de sh_academico (~100 ms).
     */
    public function test_extracto_por_habilitacion_filtra_por_hal_id_sobre_la_vista_rapida(): void
    {
        $sql = $this->capturarSql(function (AlumnoExternoService $service): void {
            $service->extractoPorHabilitacion(42178, 58655);
        });

        $this->assertStringContainsString('sh_academico', $sql);
        $this->assertStringContainsString('vw_extracto_academico_11', $sql);
        $this->assertStringContainsString('hal_id', $sql);
        $this->assertStringNotContainsString('aci_idhal', $sql);
        $this->assertStringNotContainsString('vw_extracto_academico_01', $sql);
    }

    /**
     * fn_busca_alumnos_habilitacion_extracto recibe hal_id como primer parámetro, no alu_id:
     * llamarla con el alu_id devuelve el extracto de la habilitación de otra persona. El
     * servicio no debe usarla.
     */
    public function test_el_servicio_no_llama_a_la_funcion_legacy_de_extracto(): void
    {
        $sql = $this->capturarSql(function (AlumnoExternoService $service): void {
            $service->extractoImpresionPorHabilitacion(42178, 58655);
        });

        $this->assertStringNotContainsString('fn_busca_alumnos_habilitacion_extracto', $sql);
    }

    public function test_extracto_academico_consulta_la_vista_rapida_por_las_habilitaciones_del_alumno(): void
    {
        $service = new class extends AlumnoExternoService
        {
            public function carreras(int $aluId): Collection
            {
                return collect([
                    (object) ['hal_id' => 58655],
                    (object) ['hal_id' => 77291],
                ]);
            }
        };

        $queries = DB::connection('pgsql_externa')->pretend(function () use ($service): void {
            $service->extractoAcademico(42178);
        });

        $sql = collect($queries)->pluck('query')->implode(' ');
        $bindings = collect($queries)->pluck('bindings')->flatten()->all();

        $this->assertStringContainsString('vw_extracto_academico_11', $sql);
        $this->assertStringNotContainsString('vw_extracto_academico_01', $sql);
        $this->assertSame([58655, 77291], $bindings);
    }

    /**
     * Si no se conocen habilitaciones no hay nada por donde filtrar la vista rápida,
     * así que se conserva la consulta histórica por alumno.
     */
    public function test_extracto_academico_cae_a_la_vista_por_alumno_sin_habilitaciones(): void
    {
        $service = new class extends AlumnoExternoService
        {
            public function carreras(int $aluId): Collection
            {
                return collect();
            }
        };

        $queries = DB::connection('pgsql_externa')->pretend(function () use ($service): void {
            $service->extractoAcademico(42178);
        });

        $sql = collect($queries)->pluck('query')->implode(' ');

        $this->assertStringContainsString('vw_extracto_academico_01', $sql);
        $this->assertStringContainsString('aci_idalu', $sql);
    }

    protected function capturarSql(callable $callback): string
    {
        $service = app(AlumnoExternoService::class);

        $queries = DB::connection('pgsql_externa')->pretend(function () use ($callback, $service): void {
            $callback($service);
        });

        return collect($queries)->pluck('query')->implode(' ');
    }
}
