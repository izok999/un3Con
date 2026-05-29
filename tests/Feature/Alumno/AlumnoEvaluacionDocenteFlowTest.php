<?php

namespace Tests\Feature\Alumno;

use App\Models\Docente;
use App\Models\DocenteContexto;
use App\Models\EvaluacionDocente;
use App\Models\FormularioEvaluacion;
use App\Models\PeriodoEvaluacion;
use App\Models\User;
use App\Services\AlumnoExternoService;
use App\Services\EvaluacionDocente\GuardarEvaluacionDocente;
use Database\Seeders\FormularioEvaluacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Spatie\Permission\Models\Role;
use stdClass;
use Tests\TestCase;

class AlumnoEvaluacionDocenteFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: PeriodoEvaluacion, 1: FormularioEvaluacion, 2: Docente, 3: User}
     */
    protected function setUpScenario(): array
    {
        $this->seed(FormularioEvaluacionSeeder::class);

        $periodo = PeriodoEvaluacion::query()->create([
            'nombre' => 'Periodo Lectivo 2026',
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-11-30',
            'activo' => true,
        ]);

        $formulario = FormularioEvaluacion::query()
            ->where('tipo_evaluador', FormularioEvaluacion::TIPO_ALUMNO)
            ->firstOrFail();

        $docente = Docente::query()->create([
            'docente_externo_id' => 8801,
            'documento' => '1234567',
            'nombre' => 'Ada Lovelace',
            'activo' => true,
        ]);

        $evaluador = User::factory()->create([
            'documento' => '5413971',
        ]);

        return [$periodo, $formulario, $docente, $evaluador];
    }

    protected function mockAlumnoContext(User $user): void
    {
        $alumno = new stdClass;
        $alumno->alu_id = 42178;
        $alumno->alu_perdoc = $user->documento;

        $carrera = new stdClass;
        $carrera->car_id = 14;
        $carrera->sed_id = 8;
        $carrera->ple_id = 2026;

        $materia = new stdClass;
        $materia->rsc_idcar = 14;
        $materia->rsc_idsed = 8;
        $materia->inm_idple = 2026;
        $materia->imi_idmi2 = 301;
        $materia->imi_idtur = 2;
        $materia->imi_idsec = 4;

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('resolverAlumno')->andReturn($alumno);
        $service->shouldReceive('carreras')->andReturn(new Collection([$carrera]));
        $service->shouldReceive('materiasInscriptas')->andReturn(new Collection([$materia]));

        $this->app->instance(AlumnoExternoService::class, $service);
    }

    public function test_guarda_cabecera_y_detalle_calculando_puntaje_ponderado(): void
    {
        [$periodo, $formulario, $docente, $evaluador] = $this->setUpScenario();
        $criterios = $formulario->criterios()->get()->keyBy('orden');

        $evaluacion = app(GuardarEvaluacionDocente::class)->guardar(
            $periodo,
            $formulario,
            $docente,
            $evaluador,
            FormularioEvaluacion::TIPO_ALUMNO,
            [
                ['formulario_criterio_id' => $criterios[1]->id, 'valor_numerico' => 5],
                ['formulario_criterio_id' => $criterios[2]->id, 'valor_numerico' => 4],
                ['formulario_criterio_id' => $criterios[3]->id, 'valor_numerico' => 5],
                ['formulario_criterio_id' => $criterios[4]->id, 'valor_numerico' => 4],
                ['formulario_criterio_id' => $criterios[5]->id, 'valor_numerico' => 3],
                ['formulario_criterio_id' => $criterios[6]->id, 'valor_numerico' => 5],
                ['formulario_criterio_id' => $criterios[7]->id, 'valor_texto' => 'Excelente comunicación con el grupo.'],
            ],
            ['hal_id' => 77291, 'car_id' => 14],
        );

        $this->assertModelExists($evaluacion);
        $this->assertSame('4.35', $evaluacion->puntaje_total);
        $this->assertSame(EvaluacionDocente::ESTADO_ENVIADA, $evaluacion->estado);
        $this->assertSame('Ada Lovelace', $evaluacion->docente_nombre_snapshot);
        $this->assertSame('1234567', $evaluacion->docente_documento_snapshot);
        $this->assertSame(['hal_id' => 77291, 'car_id' => 14], $evaluacion->contexto_snapshot);
        $this->assertCount(7, $evaluacion->respuestas);

        $this->assertDatabaseCount('evaluaciones_docentes', 1);
        $this->assertDatabaseCount('evaluacion_respuestas', 7);
    }

    public function test_no_permita_duplicar_una_evaluacion_del_mismo_docente_en_el_mismo_periodo_y_formulario(): void
    {
        [$periodo, $formulario, $docente, $evaluador] = $this->setUpScenario();
        $criterios = $formulario->criterios()->get()->keyBy('orden');
        $service = app(GuardarEvaluacionDocente::class);

        $payload = [
            ['formulario_criterio_id' => $criterios[1]->id, 'valor_numerico' => 5],
            ['formulario_criterio_id' => $criterios[2]->id, 'valor_numerico' => 5],
            ['formulario_criterio_id' => $criterios[3]->id, 'valor_numerico' => 5],
            ['formulario_criterio_id' => $criterios[4]->id, 'valor_numerico' => 5],
            ['formulario_criterio_id' => $criterios[5]->id, 'valor_numerico' => 5],
            ['formulario_criterio_id' => $criterios[6]->id, 'valor_numerico' => 5],
        ];

        $service->guardar($periodo, $formulario, $docente, $evaluador, FormularioEvaluacion::TIPO_ALUMNO, $payload);

        try {
            $service->guardar($periodo, $formulario, $docente, $evaluador, FormularioEvaluacion::TIPO_ALUMNO, $payload);

            $this->fail('Se esperaba una ValidationException por duplicado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('evaluacion', $exception->errors());
        }
    }

    public function test_no_permita_enviar_criterios_obligatorios_incompletos(): void
    {
        [$periodo, $formulario, $docente, $evaluador] = $this->setUpScenario();
        $criterios = $formulario->criterios()->get()->keyBy('orden');

        try {
            app(GuardarEvaluacionDocente::class)->guardar(
                $periodo,
                $formulario,
                $docente,
                $evaluador,
                FormularioEvaluacion::TIPO_ALUMNO,
                [
                    ['formulario_criterio_id' => $criterios[1]->id, 'valor_numerico' => 5],
                    ['formulario_criterio_id' => $criterios[2]->id, 'valor_numerico' => 5],
                    ['formulario_criterio_id' => $criterios[4]->id, 'valor_numerico' => 5],
                    ['formulario_criterio_id' => $criterios[5]->id, 'valor_numerico' => 5],
                    ['formulario_criterio_id' => $criterios[6]->id, 'valor_numerico' => 5],
                ],
            );

            $this->fail('Se esperaba una ValidationException por criterio obligatorio faltante.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('respuestas.'.$criterios[3]->id, $exception->errors());
        }
    }

    public function test_no_permita_usar_un_formulario_de_funcionario_con_tipo_alumno(): void
    {
        [$periodo, , $docente, $evaluador] = $this->setUpScenario();

        $formulario = FormularioEvaluacion::query()
            ->where('tipo_evaluador', FormularioEvaluacion::TIPO_FUNCIONARIO)
            ->firstOrFail();

        $criterios = $formulario->criterios()->get()->keyBy('orden');

        try {
            app(GuardarEvaluacionDocente::class)->guardar(
                $periodo,
                $formulario,
                $docente,
                $evaluador,
                FormularioEvaluacion::TIPO_ALUMNO,
                [
                    ['formulario_criterio_id' => $criterios[1]->id, 'valor_numerico' => 5],
                    ['formulario_criterio_id' => $criterios[2]->id, 'valor_numerico' => 5],
                    ['formulario_criterio_id' => $criterios[3]->id, 'valor_numerico' => 5],
                    ['formulario_criterio_id' => $criterios[4]->id, 'valor_numerico' => 5],
                    ['formulario_criterio_id' => $criterios[5]->id, 'valor_numerico' => 5],
                    ['formulario_criterio_id' => $criterios[6]->id, 'valor_numerico' => 5],
                ],
            );

            $this->fail('Se esperaba una ValidationException por tipo de evaluador inválido.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('tipo_evaluador', $exception->errors());
        }
    }

    public function test_alumno_ve_solo_los_docentes_elegibles_en_la_pantalla_de_evaluacion(): void
    {
        $this->seed(FormularioEvaluacionSeeder::class);

        /** @var User $user */
        $user = User::factory()->create([
            'documento' => '5413971',
        ]);

        Role::findOrCreate('ALUMNO', 'web');
        $user->assignRole('ALUMNO');

        PeriodoEvaluacion::query()->create([
            'nombre' => 'Periodo Lectivo 2026',
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-11-30',
            'activo' => true,
        ]);

        $docenteElegible = Docente::query()->create([
            'docente_externo_id' => 9101,
            'documento' => '2222222',
            'nombre' => 'Grace Hopper',
            'activo' => true,
        ]);

        DocenteContexto::query()->create([
            'docente_id' => $docenteElegible->id,
            'car_id' => 14,
            'sed_id' => 8,
            'ple_id' => 2026,
            'mi2_id' => 301,
            'tur_id' => 2,
            'sec_id' => 4,
            'activo' => true,
        ]);

        $docenteNoElegible = Docente::query()->create([
            'docente_externo_id' => 9102,
            'documento' => '3333333',
            'nombre' => 'Barbara Liskov',
            'activo' => true,
        ]);

        DocenteContexto::query()->create([
            'docente_id' => $docenteNoElegible->id,
            'car_id' => 99,
            'activo' => true,
        ]);

        $this->mockAlumnoContext($user);

        $this->actingAs($user)
            ->get(route('alumno.evaluacion-docente'))
            ->assertOk()
            ->assertSeeText('Evaluación Docente')
            ->assertSeeText('Grace Hopper')
            ->assertDontSeeText('Barbara Liskov')
            ->assertSee(route('alumno.evaluacion-docente.form', $docenteElegible), false);
    }

    public function test_alumno_puede_abrir_el_formulario_de_un_docente_habilitado(): void
    {
        $this->seed(FormularioEvaluacionSeeder::class);

        /** @var User $user */
        $user = User::factory()->create([
            'documento' => '5413971',
        ]);

        Role::findOrCreate('ALUMNO', 'web');
        $user->assignRole('ALUMNO');

        PeriodoEvaluacion::query()->create([
            'nombre' => 'Periodo Lectivo 2026',
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-11-30',
            'activo' => true,
        ]);

        $docente = Docente::query()->create([
            'docente_externo_id' => 9103,
            'documento' => '4444444',
            'nombre' => 'Katherine Johnson',
            'activo' => true,
        ]);

        DocenteContexto::query()->create([
            'docente_id' => $docente->id,
            'car_id' => 14,
            'sed_id' => 8,
            'ple_id' => 2026,
            'mi2_id' => 301,
            'tur_id' => 2,
            'sec_id' => 4,
            'activo' => true,
        ]);

        $this->mockAlumnoContext($user);

        $this->actingAs($user)
            ->get(route('alumno.evaluacion-docente.form', $docente))
            ->assertOk()
            ->assertSeeText('Formulario de evaluación')
            ->assertSeeText('Katherine Johnson')
            ->assertSeeText('Explica los contenidos con claridad.')
            ->assertSeeText('Enviar evaluación');
    }

    public function test_muestra_un_aviso_si_el_modulo_de_evaluacion_aun_no_fue_migrado(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'documento' => '5413971',
        ]);

        Role::findOrCreate('ALUMNO', 'web');
        $user->assignRole('ALUMNO');

        Schema::disableForeignKeyConstraints();
        Schema::drop('evaluacion_respuestas');
        Schema::drop('evaluaciones_docentes');
        Schema::drop('periodos_evaluacion');
        Schema::enableForeignKeyConstraints();

        $this->actingAs($user)
            ->get(route('alumno.evaluacion-docente'))
            ->assertOk()
            ->assertSeeText('El módulo de evaluación docente todavía no está disponible en este entorno.');
    }
}
