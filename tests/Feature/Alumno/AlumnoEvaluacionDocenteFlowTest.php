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
use Livewire\Volt\Volt;
use Mockery;
use Spatie\Permission\Models\Role;
use stdClass;
use Tests\TestCase;

class AlumnoEvaluacionDocenteFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: PeriodoEvaluacion, 1: FormularioEvaluacion, 2: DocenteContexto, 3: Docente, 4: User}
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

        $contexto = DocenteContexto::query()->create([
            'docente_id' => $docente->id,
            'car_id' => 14,
            'sed_id' => 8,
            'ple_id' => 2026,
            'mi2_id' => 301,
            'tur_id' => 2,
            'sec_id' => 4,
            'activo' => true,
        ]);

        $evaluador = User::factory()->create([
            'documento' => '5413971',
        ]);

        return [$periodo, $formulario, $contexto, $docente, $evaluador];
    }

    /**
     * @param  array<int, array<string, int>>|null  $materias
     * @param  string|null  $expectedPleCodigo  si se pasa, exige que las inscripciones se consulten con ese periodo lectivo
     */
    protected function mockAlumnoContext(User $user, ?array $materias = null, ?string $expectedPleCodigo = null): void
    {
        $alumno = new stdClass;
        $alumno->alu_id = 42178;
        $alumno->alu_perdoc = $user->documento;

        $carrera = new stdClass;
        $carrera->car_id = 14;
        $carrera->sed_id = 8;
        $carrera->ple_id = 2026;

        $materias ??= [
            [
                'rsc_idcar' => 14,
                'rsc_idsed' => 8,
                'inm_idple' => 2026,
                'imi_idmi2' => 301,
                'imi_idtur' => 2,
                'imi_idsec' => 4,
            ],
        ];

        $materiasRows = array_map(fn (array $materia): stdClass => (object) $materia, $materias);

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('resolverAlumno')->andReturn($alumno);
        $service->shouldReceive('carreras')->andReturn(new Collection([$carrera]));

        if ($expectedPleCodigo !== null) {
            $service->shouldReceive('materiasInscriptas')
                ->with(42178, $expectedPleCodigo)
                ->andReturn(new Collection($materiasRows));
        } else {
            $service->shouldReceive('materiasInscriptas')->andReturn(new Collection($materiasRows));
        }
        $service->shouldReceive('catCarreras')->andReturn([14 => 'Ingeniería Informática']);
        $service->shouldReceive('catTurnos')->andReturn([1 => 'Mañana', 2 => 'Tarde']);
        $service->shouldReceive('catMateriasPorIds')->andReturn([301 => 'Algoritmos', 302 => 'Bases de Datos']);

        $this->app->instance(AlumnoExternoService::class, $service);
    }

    public function test_guarda_cabecera_y_detalle_calculando_puntaje_ponderado(): void
    {
        [$periodo, $formulario, $contexto, $docente, $evaluador] = $this->setUpScenario();
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
            $contexto,
        );

        $this->assertModelExists($evaluacion);
        $this->assertSame('4.35', $evaluacion->puntaje_total);
        $this->assertSame(EvaluacionDocente::ESTADO_ENVIADA, $evaluacion->estado);
        $this->assertSame('Ada Lovelace', $evaluacion->docente_nombre_snapshot);
        $this->assertSame('1234567', $evaluacion->docente_documento_snapshot);
        $this->assertSame(['hal_id' => 77291, 'car_id' => 14], $evaluacion->contexto_snapshot);
        $this->assertEquals($contexto->id, $evaluacion->docente_contexto_id);
        $this->assertCount(7, $evaluacion->respuestas);

        $this->assertDatabaseCount('evaluaciones_docentes', 1);
        $this->assertDatabaseCount('evaluacion_respuestas', 7);
    }

    public function test_no_permita_duplicar_una_evaluacion_del_mismo_contexto_en_el_mismo_periodo(): void
    {
        [$periodo, $formulario, $contexto, $docente, $evaluador] = $this->setUpScenario();
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

        // Same docente, same contexto — should fail
        $service->guardar($periodo, $formulario, $docente, $evaluador, FormularioEvaluacion::TIPO_ALUMNO, $payload, [], $contexto);

        try {
            // Second call with same contexto — should trigger duplicate
            $service->guardar($periodo, $formulario, $docente, $evaluador, FormularioEvaluacion::TIPO_ALUMNO, $payload, [], $contexto);

            $this->fail('Se esperaba una ValidationException por duplicado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('evaluacion', $exception->errors());
        }
    }

    public function test_permita_evaluar_al_mismo_docente_en_distintos_contextos(): void
    {
        [$periodo, $formulario, $contexto1, $docente, $evaluador] = $this->setUpScenario();

        // Create a second contexto (different matter) for the same docente
        $contexto2 = DocenteContexto::query()->create([
            'docente_id' => $docente->id,
            'car_id' => 14,
            'sed_id' => 8,
            'ple_id' => 2026,
            'mi2_id' => 302,
            'tur_id' => 1,
            'sec_id' => 4,
            'activo' => true,
        ]);

        $criterios = $formulario->criterios()->get()->keyBy('orden');
        $payload = [
            ['formulario_criterio_id' => $criterios[1]->id, 'valor_numerico' => 5],
            ['formulario_criterio_id' => $criterios[2]->id, 'valor_numerico' => 5],
            ['formulario_criterio_id' => $criterios[3]->id, 'valor_numerico' => 5],
            ['formulario_criterio_id' => $criterios[4]->id, 'valor_numerico' => 5],
            ['formulario_criterio_id' => $criterios[5]->id, 'valor_numerico' => 5],
            ['formulario_criterio_id' => $criterios[6]->id, 'valor_numerico' => 5],
        ];

        $service = app(GuardarEvaluacionDocente::class);

        // First contexto — should work
        $service->guardar($periodo, $formulario, $docente, $evaluador, FormularioEvaluacion::TIPO_ALUMNO, $payload, [], $contexto1);
        $this->assertDatabaseCount('evaluaciones_docentes', 1);
        $this->assertDatabaseHas('evaluaciones_docentes', ['docente_contexto_id' => $contexto1->id]);

        // Second contexto — different materia, should also work
        $service->guardar($periodo, $formulario, $docente, $evaluador, FormularioEvaluacion::TIPO_ALUMNO, $payload, [], $contexto2);
        $this->assertDatabaseCount('evaluaciones_docentes', 2);
        $this->assertDatabaseHas('evaluaciones_docentes', ['docente_contexto_id' => $contexto2->id]);
    }

    public function test_no_permita_enviar_criterios_obligatorios_incompletos(): void
    {
        [$periodo, $formulario, , $docente, $evaluador] = $this->setUpScenario();
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
        [$periodo, , , $docente, $evaluador] = $this->setUpScenario();

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

    public function test_alumno_ve_solo_los_docentes_elegibles_por_materia_en_la_pantalla_de_evaluacion(): void
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
            'mi2_id' => null,
            'activo' => true,
        ]);

        $this->mockAlumnoContext($user);

        $this->actingAs($user);

        Volt::test('alumno.evaluacion-docente.index')
            ->assertSeeText('Evaluación Docente')
            ->call('cargarDocentes')
            ->assertSeeText('Grace Hopper')
            ->assertDontSeeText('Barbara Liskov');
    }

    public function test_alumno_puede_abrir_el_formulario_de_un_docente_habilitado_por_materia(): void
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

        $contexto = DocenteContexto::query()->create([
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

        $this->actingAs($user);

        // El primer render muestra el skeleton (cabecera visible, criterios aún no).
        $this->get(route('alumno.evaluacion-docente.form', [$docente, $contexto]))
            ->assertOk()
            ->assertSeeText('Formulario de evaluación')
            ->assertSeeText('Katherine Johnson');

        // Tras wire:init (cargarDatos) aparecen los criterios y el botón de envío.
        Volt::test('alumno.evaluacion-docente.form', ['docente' => $docente, 'contexto' => $contexto])
            ->call('cargarDatos')
            ->assertSeeText('Explica los contenidos con claridad.')
            ->assertSeeText('Enviar evaluación');
    }

    public function test_muestra_aviso_y_no_renderiza_el_form_si_el_periodo_ya_finalizo(): void
    {
        $this->seed(FormularioEvaluacionSeeder::class);

        /** @var User $user */
        $user = User::factory()->create(['documento' => '5413971']);
        Role::findOrCreate('ALUMNO', 'web');
        $user->assignRole('ALUMNO');

        // Periodo marcado activo pero con fecha_fin en el pasado.
        PeriodoEvaluacion::query()->create([
            'nombre' => 'Periodo vencido',
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-02-10',
            'activo' => true,
        ]);

        $docente = Docente::query()->create([
            'documento' => '4444444', 'nombre' => 'Katherine Johnson', 'activo' => true,
        ]);

        $contexto = DocenteContexto::query()->create([
            'docente_id' => $docente->id,
            'car_id' => 14, 'sed_id' => 8, 'ple_id' => 2026,
            'mi2_id' => 301, 'tur_id' => 2, 'sec_id' => 4,
            'activo' => true,
        ]);

        // Sin mock del servicio externo: la guarda de fechas corta antes del gate de elegibilidad.
        $this->actingAs($user)
            ->get(route('alumno.evaluacion-docente.form', [$docente, $contexto]))
            ->assertOk()
            ->assertSeeText('El periodo de evaluación ya ha finalizado.')
            ->assertDontSeeText('Enviar evaluación');
    }

    public function test_no_ofrece_contextos_que_mezclan_atributos_de_inscripciones_distintas(): void
    {
        $this->seed(FormularioEvaluacionSeeder::class);

        /** @var User $user */
        $user = User::factory()->create(['documento' => '5413971']);
        Role::findOrCreate('ALUMNO', 'web');
        $user->assignRole('ALUMNO');

        PeriodoEvaluacion::query()->create([
            'nombre' => 'Periodo Lectivo 2026',
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-11-30',
            'activo' => true,
        ]);

        // Inscripto en: Algoritmos (301) turno Tarde/sección 4 y Bases de Datos (302) turno Mañana/sección 9.
        $this->mockAlumnoContext($user, [
            ['rsc_idcar' => 14, 'rsc_idsed' => 8, 'inm_idple' => 2026, 'imi_idmi2' => 301, 'imi_idtur' => 2, 'imi_idsec' => 4],
            ['rsc_idcar' => 14, 'rsc_idsed' => 8, 'inm_idple' => 2026, 'imi_idmi2' => 302, 'imi_idtur' => 1, 'imi_idsec' => 9],
        ]);

        $docenteElegible = Docente::query()->create([
            'documento' => '2222222', 'nombre' => 'Grace Hopper', 'activo' => true,
        ]);

        // Coincide con la inscripción real de Algoritmos (turno 2).
        DocenteContexto::query()->create([
            'docente_id' => $docenteElegible->id,
            'car_id' => 14, 'sed_id' => 8, 'ple_id' => 2026,
            'mi2_id' => 301, 'tur_id' => 2, 'sec_id' => 4,
            'activo' => true,
        ]);

        $docenteNoElegible = Docente::query()->create([
            'documento' => '3333333', 'nombre' => 'Barbara Liskov', 'activo' => true,
        ]);

        // Mezcla la materia de una inscripción (301) con el turno/sección de otra (1/9):
        // el matching aplanado la aceptaba, el matching por tupla debe rechazarla.
        DocenteContexto::query()->create([
            'docente_id' => $docenteNoElegible->id,
            'car_id' => 14, 'sed_id' => 8, 'ple_id' => 2026,
            'mi2_id' => 301, 'tur_id' => 1, 'sec_id' => 9,
            'activo' => true,
        ]);

        $this->actingAs($user);

        Volt::test('alumno.evaluacion-docente.index')
            ->call('cargarDocentes')
            ->assertSeeText('Grace Hopper')
            ->assertDontSeeText('Barbara Liskov');
    }

    public function test_no_ofrece_contextos_asignados_a_otra_campania_de_evaluacion(): void
    {
        $this->seed(FormularioEvaluacionSeeder::class);

        /** @var User $user */
        $user = User::factory()->create(['documento' => '5413971']);
        Role::findOrCreate('ALUMNO', 'web');
        $user->assignRole('ALUMNO');

        $campaniaVieja = PeriodoEvaluacion::query()->create([
            'nombre' => 'Campaña 2025',
            'fecha_inicio' => '2025-02-01',
            'fecha_fin' => '2025-11-30',
            'activo' => false,
        ]);

        $campaniaActiva = PeriodoEvaluacion::query()->create([
            'nombre' => 'Campaña 2026',
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-11-30',
            'activo' => true,
        ]);

        $this->mockAlumnoContext($user);

        $docenteCampaniaActiva = Docente::query()->create([
            'documento' => '2222222', 'nombre' => 'Grace Hopper', 'activo' => true,
        ]);

        DocenteContexto::query()->create([
            'docente_id' => $docenteCampaniaActiva->id,
            'car_id' => 14, 'sed_id' => 8, 'ple_id' => 2026,
            'mi2_id' => 301, 'tur_id' => 2, 'sec_id' => 4,
            'periodo_evaluacion_id' => $campaniaActiva->id,
            'activo' => true,
        ]);

        $docenteSinCampania = Docente::query()->create([
            'documento' => '4444444', 'nombre' => 'Katherine Johnson', 'activo' => true,
        ]);

        // Sin campaña asignada: comodín, vale para cualquier campaña.
        DocenteContexto::query()->create([
            'docente_id' => $docenteSinCampania->id,
            'car_id' => 14, 'sed_id' => 8, 'ple_id' => 2026,
            'mi2_id' => 301, 'tur_id' => 2, 'sec_id' => 4,
            'periodo_evaluacion_id' => null,
            'activo' => true,
        ]);

        $docenteCampaniaVieja = Docente::query()->create([
            'documento' => '3333333', 'nombre' => 'Barbara Liskov', 'activo' => true,
        ]);

        // Mismo contexto académico pero asignado a la campaña 2025: no debe aparecer.
        DocenteContexto::query()->create([
            'docente_id' => $docenteCampaniaVieja->id,
            'car_id' => 14, 'sed_id' => 8, 'ple_id' => 2026,
            'mi2_id' => 301, 'tur_id' => 2, 'sec_id' => 4,
            'periodo_evaluacion_id' => $campaniaVieja->id,
            'activo' => true,
        ]);

        $this->actingAs($user);

        Volt::test('alumno.evaluacion-docente.index')
            ->call('cargarDocentes')
            ->assertSeeText('Grace Hopper')
            ->assertSeeText('Katherine Johnson')
            ->assertDontSeeText('Barbara Liskov');
    }

    public function test_consulta_las_inscripciones_por_el_periodo_lectivo_vinculado_a_la_campania(): void
    {
        $this->seed(FormularioEvaluacionSeeder::class);

        /** @var User $user */
        $user = User::factory()->create(['documento' => '5413971']);
        Role::findOrCreate('ALUMNO', 'web');
        $user->assignRole('ALUMNO');

        PeriodoEvaluacion::query()->create([
            'nombre' => 'Campaña 2026',
            'ple_codigo' => '2026',
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-11-30',
            'activo' => true,
        ]);

        $docente = Docente::query()->create([
            'documento' => '2222222', 'nombre' => 'Grace Hopper', 'activo' => true,
        ]);

        DocenteContexto::query()->create([
            'docente_id' => $docente->id,
            'car_id' => 14, 'sed_id' => 8, 'ple_id' => 2026,
            'mi2_id' => 301, 'tur_id' => 2, 'sec_id' => 4,
            'activo' => true,
        ]);

        // El mock falla si materiasInscriptas no se consulta con el ple_codigo de la campaña.
        $this->mockAlumnoContext($user, null, '2026');

        $this->actingAs($user);

        Volt::test('alumno.evaluacion-docente.index')
            ->call('cargarDocentes')
            ->assertSeeText('Grace Hopper');
    }

    public function test_no_permita_abrir_el_formulario_de_una_materia_en_la_que_no_esta_inscripto(): void
    {
        $this->seed(FormularioEvaluacionSeeder::class);

        /** @var User $user */
        $user = User::factory()->create(['documento' => '5413971']);
        Role::findOrCreate('ALUMNO', 'web');
        $user->assignRole('ALUMNO');

        PeriodoEvaluacion::query()->create([
            'nombre' => 'Periodo Lectivo 2026',
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-11-30',
            'activo' => true,
        ]);

        $docente = Docente::query()->create([
            'documento' => '4444444', 'nombre' => 'Katherine Johnson', 'activo' => true,
        ]);

        // Contexto de una materia (999) en la que el alumno NO está inscripto.
        $contexto = DocenteContexto::query()->create([
            'docente_id' => $docente->id,
            'car_id' => 14, 'sed_id' => 8, 'ple_id' => 2026,
            'mi2_id' => 999, 'tur_id' => 2, 'sec_id' => 4,
            'activo' => true,
        ]);

        $this->mockAlumnoContext($user);

        $this->actingAs($user)
            ->get(route('alumno.evaluacion-docente.form', [$docente, $contexto]))
            ->assertStatus(403);
    }

    public function test_no_permita_acceder_al_formulario_con_contexto_de_otro_docente(): void
    {
        $this->seed(FormularioEvaluacionSeeder::class);

        /** @var User $user */
        $user = User::factory()->create(['documento' => '5413971']);
        Role::findOrCreate('ALUMNO', 'web');
        $user->assignRole('ALUMNO');

        PeriodoEvaluacion::query()->create([
            'nombre' => 'Periodo Lectivo 2026',
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-11-30',
            'activo' => true,
        ]);

        $docente1 = Docente::query()->create(['nombre' => 'A', 'activo' => true, 'documento' => '111']);
        $contextoDeOtroDocente = DocenteContexto::query()->create([
            'docente_id' => $docente1->id,
            'car_id' => 14, 'mi2_id' => 301, 'activo' => true,
        ]);

        $docente2 = Docente::query()->create(['nombre' => 'B', 'activo' => true, 'documento' => '222']);

        $this->actingAs($user)
            ->get(route('alumno.evaluacion-docente.form', [$docente2, $contextoDeOtroDocente]))
            ->assertStatus(403);
    }

    public function test_no_permita_evaluar_fuera_del_rango_de_fechas_del_periodo(): void
    {
        $this->seed(FormularioEvaluacionSeeder::class);

        $periodo = PeriodoEvaluacion::query()->create([
            'nombre' => 'Periodo Pasado',
            'fecha_inicio' => '2020-01-01',
            'fecha_fin' => '2020-12-31',
            'activo' => true,
        ]);

        $formulario = FormularioEvaluacion::query()
            ->where('tipo_evaluador', FormularioEvaluacion::TIPO_ALUMNO)
            ->firstOrFail();

        $docente = Docente::query()->create([
            'docente_externo_id' => 9901,
            'documento' => '5555555',
            'nombre' => 'Docente Fuera de Plazo',
            'activo' => true,
        ]);

        /** @var User $evaluador */
        $evaluador = User::factory()->create(['documento' => '9999999']);
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

            $this->fail('Se esperaba una ValidationException por periodo fuera de rango.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('periodo', $exception->errors());
            $this->assertStringContainsString('ha finalizado', $exception->errors()['periodo'][0]);
        }
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
        Schema::drop('docente_contextos');
        Schema::drop('periodos_evaluacion');
        Schema::enableForeignKeyConstraints();

        $this->actingAs($user)
            ->get(route('alumno.evaluacion-docente'))
            ->assertOk()
            ->assertSeeText('El módulo de evaluación docente todavía no está disponible en este entorno.');
    }
}
