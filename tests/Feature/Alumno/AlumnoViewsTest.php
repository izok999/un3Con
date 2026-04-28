<?php

namespace Tests\Feature\Alumno;

use App\Models\User;
use App\Services\AlumnoExternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;
use Mockery;
use Spatie\Permission\Models\Role;
use stdClass;
use Tests\TestCase;

class AlumnoViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_alumno_routes_render_with_mocked_external_service(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'documento' => '5413971',
        ]);

        Role::findOrCreate('ALUMNO', 'web');
        $user->assignRole('ALUMNO');

        $this->actingAs($user);

        $alumno = new stdClass;
        $alumno->alu_id = 42178;
        $alumno->alu_perdoc = '5413971';
        $alumno->per_nombre = 'ISAAC RAFAEL';
        $alumno->per_apelli = 'BRITEZ PAREDES';

        $carrera = new stdClass;
        $carrera->uac_descri = 'Facultad Politécnica';
        $carrera->pac_descri = 'INGENIERÍA DE SISTEMAS';
        $carrera->ciu_descri = 'CIUDAD DEL ESTE';
        $carrera->ple_codigo = '2018';
        $carrera->ple_descri = 'PERIODO LECTIVO 2018';
        $carrera->hal_vigent = true;

        $materia = new stdClass;
        $materia->mat_descri = 'COMUNICACIÓN ORAL Y ESCRITA';
        $materia->cur_descri = 'PRIMER SEMESTRE';
        $materia->ple_codigo = '2018';
        $materia->tur_descri = 'INTEGRAL';
        $materia->sec_descri = 'ÚNICA';
        $materia->uac_descri = 'Facultad Politécnica';

        $extracto = new stdClass;
        $extracto->mat_descri = 'BASE DE DATOS I';
        $extracto->tev_descri = 'SEGUNDO FINAL';
        $extracto->act_periodo = '2025';
        $extracto->act_fecha = '2025-12-09';
        $extracto->cal_notaci = '1';
        $extracto->cal_situac = '2';

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('resolverAlumno')->times(5)->with('5413971')->andReturn($alumno);
        $service->shouldReceive('carreras')->times(2)->with(42178)->andReturn(new Collection([$carrera]));
        $service->shouldReceive('materiasInscriptas')->once()->with(42178)->andReturn(new Collection([$materia]));
        $service->shouldReceive('deudas')->once()->with(42178)->andReturn(collect());
        $service->shouldReceive('extractoAcademico')->once()->with(42178)->andReturn(new Collection([$extracto]));

        $this->app->instance(AlumnoExternoService::class, $service);

        Volt::test('alumno.mis-carreras')
            ->assertSee('Mis Carreras')
            ->assertSee('INGENIERÍA DE SISTEMAS')
            ->assertSee('Vigente');

        Volt::test('alumno.mis-materias')
            ->assertSee('Materias Inscriptas')
            ->assertSee('COMUNICACIÓN ORAL Y ESCRITA');

        Volt::test('alumno.mis-deudas')
            ->assertSee('Mis Deudas')
            ->assertSee('No tenés deudas pendientes.');

        Volt::test('alumno.extracto-academico')
            ->assertSee('Extracto Académico')
            ->assertSee('BASE DE DATOS I');

        $this->get(route('alumno.carreras'))
            ->assertOk()
            ->assertSee('Mis Carreras')
            ->assertSee('INGENIERÍA DE SISTEMAS');
    }

    public function test_detalle_carrera_loads_lazy_sections_with_per_habilitacion_service_methods(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'documento' => '5413971',
        ]);

        Role::findOrCreate('ALUMNO', 'web');
        $user->assignRole('ALUMNO');

        $this->actingAs($user);

        $alumno = new stdClass;
        $alumno->alu_id = 42178;
        $alumno->alu_perdoc = '5413971';
        $alumno->per_nombre = 'ISAAC RAFAEL';
        $alumno->per_apelli = 'BRITEZ PAREDES';

        $carrera = new stdClass;
        $carrera->hal_id = 58655;
        $carrera->hal_idrsc = 971;
        $carrera->hal_idple = 20261;
        $carrera->uac_descri = 'Facultad Politécnica';
        $carrera->pac_descri = 'INGENIERÍA DE SISTEMAS';
        $carrera->ciu_descri = 'CIUDAD DEL ESTE';
        $carrera->ple_codigo = '2026';
        $carrera->ple_descri = 'PERIODO LECTIVO 2026';
        $carrera->hal_vigent = true;

        $materia = new stdClass;
        $materia->mat_descri = 'COMUNICACIÓN ORAL Y ESCRITA';
        $materia->cur_descri = 'PRIMER SEMESTRE';
        $materia->tur_descri = 'INTEGRAL';
        $materia->sec_descri = 'ÚNICA';

        $evaluacion = new stdClass;
        $evaluacion->mat_descri = 'COMUNICACIÓN ORAL Y ESCRITA';
        $evaluacion->tev_descri = 'PRIMER PARCIAL';
        $evaluacion->evp_fecha = '15/04/2026';
        $evaluacion->epi_puntaj = 18;
        $evaluacion->evp_ptotal = 20;

        $asistencia = new stdClass;
        $asistencia->mat_descri = 'COMUNICACIÓN ORAL Y ESCRITA';
        $asistencia->cur_descri = 'PRIMER SEMESTRE';
        $asistencia->alu_clase = 20;
        $asistencia->alu_presen = 18;

        $deuda = new stdClass;
        $deuda->aca_descri = 'Cuota abril';
        $deuda->dit_vencim = '30/04/2026';
        $deuda->dit_saldo = 150000;

        $extracto = new stdClass;
        $extracto->mat_descri = 'BASE DE DATOS I';
        $extracto->tev_descri = 'SEGUNDO FINAL';
        $extracto->act_periodo = '2025';
        $extracto->act_fecha = '09/12/2025';
        $extracto->cal_notaci = '5';
        $extracto->cal_situac = 1;

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('resolverAlumno')->once()->with('5413971')->andReturn($alumno);
        $service->shouldReceive('carreras')->once()->with(42178)->andReturn(new Collection([$carrera]));
        $service->shouldReceive('materiasPorHabilitacion')->once()->with(42178, 58655, 971)->andReturn(new Collection([$materia]));
        $service->shouldReceive('extractoPorHabilitacion')->once()->with(42178, 58655)->andReturn(new Collection([$extracto]));
        $service->shouldReceive('deudasPorHabilitacion')->once()->with(42178, 971, 20261)->andReturn(new Collection([$deuda]));
        $service->shouldReceive('asistenciaPorHabilitacion')->once()->with(42178, 971, 20261)->andReturn(new Collection([$asistencia]));
        $service->shouldReceive('evaluaciones')->once()->with(58655)->andReturn(new Collection([$evaluacion]));

        $this->app->instance(AlumnoExternoService::class, $service);

        Volt::test('alumno.detalle-carrera', ['halId' => 58655])
            ->assertSee('Detalle de carrera')
            ->assertSee('INGENIERÍA DE SISTEMAS')
            ->call('loadData')
            ->assertSet('isLoaded', true)
            ->assertSee('COMUNICACIÓN ORAL Y ESCRITA')
            ->assertSee('PRIMER PARCIAL')
            ->assertSee('Cuota abril')
            ->assertSee('BASE DE DATOS I');
    }

    public function test_alumno_route_shows_error_when_documento_has_no_external_match(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'documento' => '5413971',
        ]);

        Role::findOrCreate('ALUMNO', 'web');
        $user->assignRole('ALUMNO');

        $this->actingAs($user);

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('resolverAlumno')->once()->with('5413971')->andReturnNull();

        $this->app->instance(AlumnoExternoService::class, $service);

        Volt::test('alumno.mis-carreras')
            ->assertSee('No se encontró un alumno con el documento registrado en tu cuenta.');
    }

    public function test_resolver_alumno_normalizes_legacy_cached_values(): void
    {
        $cacheKey = 'alumno_doc_5413971';

        Cache::put(
            $cacheKey,
            unserialize(
                'O:8:"stdClass":2:{s:6:"alu_id";i:42178;s:10:"alu_perdoc";s:7:"5413971";}',
                ['allowed_classes' => false],
            ),
            1800,
        );

        $alumno = app(AlumnoExternoService::class)->resolverAlumno('5413971');

        $this->assertInstanceOf(stdClass::class, $alumno);
        $this->assertSame(42178, $alumno->alu_id);
        $this->assertSame('5413971', $alumno->alu_perdoc);
        $this->assertIsArray(Cache::get($cacheKey));
        $this->assertSame(42178, Cache::get($cacheKey)['alu_id']);
    }

    public function test_carreras_normalizes_cached_rows_to_objects(): void
    {
        $cacheKey = 'alumno_42178_carreras';

        Cache::put($cacheKey, [
            [
                'uac_descri' => 'Facultad Politécnica',
                'pac_descri' => 'INGENIERÍA DE SISTEMAS',
                'ciu_descri' => 'CIUDAD DEL ESTE',
                'ple_codigo' => '2018',
                'ple_descri' => 'PERIODO LECTIVO 2018',
                'hal_vigent' => true,
            ],
        ], 1800);

        $carreras = app(AlumnoExternoService::class)->carreras(42178);

        $this->assertInstanceOf(Collection::class, $carreras);
        $this->assertInstanceOf(stdClass::class, $carreras->first());
        $this->assertSame('Facultad Politécnica', $carreras->first()->uac_descri);
        $this->assertTrue($carreras->first()->hal_vigent);
    }

    public function test_per_habilitacion_service_methods_filter_with_available_external_fields(): void
    {
        $service = new class(collect([(object) ['mat_descri' => 'COMUNICACIÓN ORAL Y ESCRITA', 'inm_idrsc' => 971], (object) ['mat_descri' => 'ÁLGEBRA', 'inm_idrsc' => 999]]), collect([(object) ['mat_descri' => 'BASE DE DATOS I', 'aci_idhal' => 58655], (object) ['mat_descri' => 'MATEMÁTICA', 'aci_idhal' => 99999]]), collect([(object) ['aca_descri' => 'Cuota abril', 'deu_idrsc' => 971, 'deu_idple' => 20261], (object) ['aca_descri' => 'Cuota mayo', 'deu_idrsc' => 971, 'deu_idple' => 20262]]), collect([(object) ['mat_descri' => 'COMUNICACIÓN ORAL Y ESCRITA', 'aal_idrsc' => 971, 'aal_idple' => 20261], (object) ['mat_descri' => 'ÁLGEBRA', 'aal_idrsc' => 999, 'aal_idple' => 20261]])) extends AlumnoExternoService
        {
            public function __construct(
                public Collection $materiasMock,
                public Collection $extractoMock,
                public Collection $deudasMock,
                public Collection $asistenciaMock,
            ) {}

            public function materiasInscriptas(int $aluId): Collection
            {
                return $this->materiasMock;
            }

            public function extractoAcademico(int $aluId): Collection
            {
                return $this->extractoMock;
            }

            public function deudas(int $aluId): Collection
            {
                return $this->deudasMock;
            }

            public function asistencia(int $aluId): Collection
            {
                return $this->asistenciaMock;
            }
        };

        $materias = $service->materiasPorHabilitacion(42178, 58655, 971);
        $extracto = $service->extractoPorHabilitacion(42178, 58655);
        $deudas = $service->deudasPorHabilitacion(42178, 971, 20261);
        $asistencias = $service->asistenciaPorHabilitacion(42178, 971, 20261);

        $this->assertCount(1, $materias);
        $this->assertSame('COMUNICACIÓN ORAL Y ESCRITA', $materias->first()->mat_descri);
        $this->assertCount(1, $extracto);
        $this->assertSame('BASE DE DATOS I', $extracto->first()->mat_descri);
        $this->assertCount(1, $deudas);
        $this->assertSame('Cuota abril', $deudas->first()->aca_descri);
        $this->assertCount(1, $asistencias);
        $this->assertSame('COMUNICACIÓN ORAL Y ESCRITA', $asistencias->first()->mat_descri);
    }
}
