<?php

namespace Tests\Feature\Alumno;

use App\Models\User;
use App\Services\AlumnoExternoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Role;
use stdClass;
use Tests\TestCase;

class AlumnoViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_alumno_layout_renders_mobile_bottom_navigation(): void
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
        $carrera->ple_codigo = '2026';
        $carrera->ple_descri = 'PERIODO LECTIVO 2026';
        $carrera->hal_vigent = true;

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('resolverAlumno')->once()->with('5413971')->andReturn($alumno);
        $service->shouldReceive('carreras')->once()->with(42178)->andReturn(new Collection([$carrera]));

        $this->app->instance(AlumnoExternoService::class, $service);

        $this->get(route('alumno.carreras'))
            ->assertOk()
            ->assertSee('data-route-shell', false)
            ->assertSee('route-transition-shell', false)
            ->assertSee('data-testid="alumno-mobile-bottom-nav"', false)
            ->assertSee('aria-label="Navegacion principal del alumno"', false)
            ->assertSee('data-route-link="mobile-home"', false)
            ->assertSee('data-route-link="mobile-carreras"', false)
            ->assertSee('wire:navigate', false)
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('href="'.route('alumno.carreras').'"', false)
            ->assertSee('href="'.route('alumno.extracto').'"', false)
            ->assertSee('href="'.route('alumno.materias').'"', false)
            ->assertSee('href="'.route('alumno.deudas').'"', false)
            ->assertSeeText('Inicio')
            ->assertSeeText('Carreras')
            ->assertSeeText('Extracto')
            ->assertSeeText('Materias')
            ->assertSeeText('Pagos');
    }

    public function test_admin_layout_does_not_render_alumno_mobile_bottom_navigation(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Role::findOrCreate('ADMIN', 'web');
        $user->assignRole('ADMIN');

        $this->actingAs($user);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('data-testid="alumno-mobile-bottom-nav"', false);
    }

    public function test_alumno_dashboard_renders_stagger_markup_for_glass_cards(): void
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
        $carrera->ple_codigo = '2026';
        $carrera->ple_descri = 'PERIODO LECTIVO 2026';
        $carrera->hal_vigent = true;

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('resolverAlumno')->andReturn($alumno);
        $service->shouldReceive('carreras')->andReturn(new Collection([$carrera]));

        $this->app->instance(AlumnoExternoService::class, $service);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-dashboard-stagger', false)
            ->assertSee('data-dashboard-stagger-item', false)
            ->assertSee('--dashboard-stagger-index: 0;', false)
            ->assertSee('--dashboard-stagger-index: 1;', false)
            ->assertSee('--dashboard-stagger-index: 5;', false)
            ->assertSeeText('Bienvenido, '.$user->name)
            ->assertSeeText('Mis Carreras')
            ->assertSeeText('Estado de Cuenta');
    }

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
        $service->shouldReceive('pagosAlumno')->once()->with(42178)->andReturn(collect());
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
            ->assertSee('Timeline (0)')
            ->assertSee('Deudas (0)')
            ->assertSee('Pagos legacy (0)')
            ->assertSee('No hay movimientos financieros registrados todavía.');

        Volt::test('alumno.extracto-academico')
            ->assertSee('Extracto Académico')
            ->assertSee('BASE DE DATOS I');

        $this->get(route('alumno.carreras'))
            ->assertOk()
            ->assertSee('Mis Carreras')
            ->assertSee('INGENIERÍA DE SISTEMAS');
    }

    public function test_alumno_global_financial_view_renders_tabs_and_timeline_with_legacy_payments(): void
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

        $deuda = new stdClass;
        $deuda->aca_descri = 'Cuota abril';
        $deuda->uac_descri = 'Facultad Politécnica';
        $deuda->ple_codigo = '2026';
        $deuda->ple_descri = 'PERIODO LECTIVO 2026';
        $deuda->dit_vencim = '30/04/2026';
        $deuda->dit_saldo = 150000;
        $deuda->deu_situac = 'P';

        $pago = new stdClass;
        $pago->cob_arancel = 'Cuota marzo';
        $pago->uac_descri = 'Facultad Politécnica';
        $pago->cob_fecha = '2026-05-05';
        $pago->cob_monto = 120000;
        $pago->cob_numero = '001-000123';
        $pago->cob_perceptor = 'Caja central';

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('resolverAlumno')->once()->with('5413971')->andReturn($alumno);
        $service->shouldReceive('deudas')->times(3)->with(42178)->andReturn(new Collection([$deuda]));
        $service->shouldReceive('pagosAlumno')->times(3)->with(42178)->andReturn(new Collection([$pago]));

        $this->app->instance(AlumnoExternoService::class, $service);

        Volt::test('alumno.mis-deudas')
            ->assertSee('Timeline (2)')
            ->assertSee('Deudas (1)')
            ->assertSee('Pagos legacy (1)')
            ->assertSee('Cuota abril')
            ->assertSee('Recibo #001-000123 · Caja central')
            ->assertSet('timeline.0.concept', 'Cuota abril')
            ->assertSet('timeline.1.concept', 'Cuota marzo')
            ->set('tab', 'pagos')
            ->assertSet('tab', 'pagos')
            ->assertSee('Perceptor')
            ->set('tab', 'deudas')
            ->assertSet('tab', 'deudas')
            ->assertSee('Pendiente');
    }

    public function test_detalle_carrera_loads_lazy_sections_with_per_habilitacion_service_methods(): void
    {
        Livewire::withoutLazyLoading();

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

        $pago = new stdClass;
        $pago->cob_arancel = 'Cuota marzo';
        $pago->cob_fecha = '05/04/2026';
        $pago->cob_monto = 120000;
        $pago->cob_numero = '001-000123';
        $pago->cob_perceptor = 'Caja central';
        $pago->uac_descri = 'Facultad Politécnica';

        $extracto = new stdClass;
        $extracto->mat_descri = 'BASE DE DATOS I';
        $extracto->tev_descri = 'SEGUNDO FINAL';
        $extracto->act_periodo = '2025';
        $extracto->act_fecha = '09/12/2025';
        $extracto->cal_notaci = '5';
        $extracto->cal_situac = 1;

        $mallaPendiente = new stdClass;
        $mallaPendiente->car_id = 77;
        $mallaPendiente->mat_descri = 'INTELIGENCIA ARTIFICIAL';
        $mallaPendiente->cur_descri = 'NOVENO SEMESTRE';
        $mallaPendiente->estado = 'Pendiente';

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('resolverAlumno')->once()->with('5413971')->andReturn($alumno);
        $service->shouldReceive('carreras')->once()->with(42178)->andReturn(new Collection([$carrera]));
        $service->shouldReceive('materiasPorHabilitacion')->with(42178, 58655, 971)->andReturn(new Collection([$materia]));
        $service->shouldReceive('extractoImpresionPorHabilitacion')->with(42178, 58655)->andReturn(new Collection([$extracto]));
        $service->shouldReceive('deudasPorHabilitacion')->with(42178, 971, 20261)->andReturn(new Collection([$deuda]));
        $service->shouldReceive('pagosAlumno')->with(42178)->andReturn(new Collection([$pago]));
        $service->shouldReceive('asistenciaPorHabilitacion')->with(42178, 971, 20261)->andReturn(new Collection([$asistencia]));
        $service->shouldReceive('evaluaciones')->with(58655)->andReturn(new Collection([$evaluacion]));
        $service->shouldReceive('mallaCurricular')->with(42178)->andReturn(new Collection([$mallaPendiente]));

        $this->app->instance(AlumnoExternoService::class, $service);

        Volt::test('alumno.detalle-carrera', ['halId' => 58655])
            ->assertSee('Detalle de carrera')
            ->assertSee('INGENIERÍA DE SISTEMAS')
            ->assertSee('Resumen académico')
            ->assertSee('data-testid="detalle-carrera-periodo-seguimiento-headings"', false)
            ->assertSee('Materias actuales y su asistencia respectiva')
            ->assertSee('Ultimas evaluaciones y sus calificaciones')
            ->assertSee('Pagos y deudas en orden cronologico')
            ->assertSeeInOrder([
                'Materias actuales y su asistencia respectiva',
                'Seguimiento reciente',
                'Ultimas evaluaciones y sus calificaciones',
                'Estado financiero',
            ])
            ->assertSee('Materias pendientes en la carrera');

        Livewire::withoutLazyLoading()->test('alumno.detalle-carrera.extracto', ['aluId' => 42178, 'halId' => 58655])
            ->assertSee('Resumen académico')
            ->assertSee('BASE DE DATOS I');

        Livewire::withoutLazyLoading()->test('alumno.detalle-carrera.materias', ['aluId' => 42178, 'halId' => 58655, 'rscId' => 971])
            ->assertSee('Materias actuales')
            ->assertSee('COMUNICACIÓN ORAL Y ESCRITA');

        Livewire::withoutLazyLoading()->test('alumno.detalle-carrera.asistencias', ['aluId' => 42178, 'rscId' => 971, 'periodoId' => 20261])
            ->assertSee('Asistencia')
            ->assertSee('COMUNICACIÓN ORAL Y ESCRITA');

        Livewire::withoutLazyLoading()->test('alumno.detalle-carrera.evaluaciones', ['halId' => 58655])
            ->assertSee('Ultimas evaluaciones')
            ->assertSee('PRIMER PARCIAL');

        Livewire::withoutLazyLoading()->test('alumno.detalle-carrera.deudas', ['aluId' => 42178, 'rscId' => 971, 'periodoId' => 20261])
            ->assertSee('Estado de cuenta')
            ->assertSee('Cuota abril')
            ->assertSee('Cuota marzo')
            ->assertSee('Recibo #001-000123');

        Livewire::withoutLazyLoading()->test('alumno.detalle-carrera.pendientes', ['aluId' => 42178, 'halId' => 58655, 'carId' => 77, 'rscId' => 971])
            ->assertSee('Materias pendientes')
            ->assertSee('INTELIGENCIA ARTIFICIAL');
    }

    public function test_detalle_carrera_child_components_sort_recent_activity_and_financial_rows(): void
    {
        Livewire::withoutLazyLoading();

        $evaluacionAntigua = new stdClass;
        $evaluacionAntigua->mat_descri = 'ALGORITMOS';
        $evaluacionAntigua->tev_descri = 'PRIMER PARCIAL';
        $evaluacionAntigua->evp_fecha = '10/03/2026';
        $evaluacionAntigua->epi_puntaj = 15;
        $evaluacionAntigua->evp_ptotal = 20;

        $evaluacionReciente = new stdClass;
        $evaluacionReciente->mat_descri = 'BASE DE DATOS';
        $evaluacionReciente->tev_descri = 'SEGUNDO PARCIAL';
        $evaluacionReciente->evp_fecha = '22/04/2026';
        $evaluacionReciente->epi_puntaj = 19;
        $evaluacionReciente->evp_ptotal = 20;

        $deudaAntigua = new stdClass;
        $deudaAntigua->aca_descri = 'Cuota marzo';
        $deudaAntigua->dit_vencim = '31/03/2026';
        $deudaAntigua->dit_saldo = 100000;

        $deudaReciente = new stdClass;
        $deudaReciente->aca_descri = 'Cuota abril';
        $deudaReciente->dit_vencim = '30/04/2026';
        $deudaReciente->dit_saldo = 150000;

        $pagoAntiguo = new stdClass;
        $pagoAntiguo->cob_arancel = 'Pago matricula';
        $pagoAntiguo->cob_fecha = '15/03/2026';
        $pagoAntiguo->cob_monto = 80000;
        $pagoAntiguo->cob_numero = '001-000111';
        $pagoAntiguo->cob_perceptor = 'Caja central';

        $pagoReciente = new stdClass;
        $pagoReciente->cob_arancel = 'Pago mayo';
        $pagoReciente->cob_fecha = '15/05/2026';
        $pagoReciente->cob_monto = 180000;
        $pagoReciente->cob_numero = '001-000222';
        $pagoReciente->cob_perceptor = 'Caja central';

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('evaluaciones')->once()->with(58655)->andReturn(new Collection([$evaluacionAntigua, $evaluacionReciente]));
        $service->shouldReceive('deudasPorHabilitacion')->once()->with(42178, 971, 20261)->andReturn(new Collection([$deudaReciente, $deudaAntigua]));
        $service->shouldReceive('pagosAlumno')->once()->with(42178)->andReturn(new Collection([$pagoReciente, $pagoAntiguo]));

        $this->app->instance(AlumnoExternoService::class, $service);

        Livewire::withoutLazyLoading()->test('alumno.detalle-carrera.evaluaciones', ['halId' => 58655])
            ->assertSet('evaluaciones.0.mat_descri', 'BASE DE DATOS')
            ->assertSet('evaluaciones.1.mat_descri', 'ALGORITMOS');

        Livewire::withoutLazyLoading()->test('alumno.detalle-carrera.deudas', ['aluId' => 42178, 'rscId' => 971, 'periodoId' => 20261])
            ->assertSet('deudas.0.aca_descri', 'Cuota marzo')
            ->assertSet('deudas.1.aca_descri', 'Cuota abril')
            ->assertSet('timeline.0.concept', 'Pago matricula')
            ->assertSet('timeline.1.concept', 'Cuota marzo')
            ->assertSet('timeline.2.concept', 'Cuota abril')
            ->assertSet('timeline.3.concept', 'Pago mayo');
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

    public function test_pagos_alumno_normalizes_rows_from_legacy_function(): void
    {
        $service = new class([(object) ['cob_arancel' => 'Cuota marzo', 'cob_fecha' => '2026-04-05', 'cob_monto' => 120000, 'cob_numero' => '001-000123', 'cob_perceptor' => 'Caja central']]) extends AlumnoExternoService
        {
            public array $captured = [];

            public function __construct(public array $pagosMock) {}

            protected function queryPagosAlumno(int $aluId): array
            {
                $this->captured = [$aluId];

                return $this->pagosMock;
            }
        };

        $pagos = $service->pagosAlumno(42178);

        $this->assertCount(1, $pagos);
        $this->assertInstanceOf(stdClass::class, $pagos->first());
        $this->assertSame('Cuota marzo', $pagos->first()->cob_arancel);
        $this->assertSame([42178], $service->captured);
    }

    public function test_carreras_normalizes_cached_view_22_rows_to_existing_contract(): void
    {
        $cacheKey = 'alumno_42178_carreras';

        Cache::put($cacheKey, [
            [
                'hal_id' => 58655,
                'rsc_id' => 971,
                'ple_id' => 20261,
                'uac_descri' => 'Facultad Politécnica',
                'pac_descri' => 'INGENIERÍA DE SISTEMAS',
                'ciu_descri' => 'CIUDAD DEL ESTE',
                'ple_codigo' => '2026',
            ],
            [
                'hal_id' => 58001,
                'rsc_id' => 971,
                'ple_id' => 20251,
                'uac_descri' => 'Facultad Politécnica',
                'pac_descri' => 'INGENIERÍA DE SISTEMAS',
                'ciu_descri' => 'CIUDAD DEL ESTE',
                'ple_codigo' => '2025',
            ],
        ], 1800);

        $carreras = app(AlumnoExternoService::class)->carreras(42178);

        $this->assertInstanceOf(Collection::class, $carreras);
        $this->assertInstanceOf(stdClass::class, $carreras->first());
        $this->assertSame('Facultad Politécnica', $carreras->first()->uac_descri);
        $this->assertSame(971, $carreras->first()->hal_idrsc);
        $this->assertSame(20261, $carreras->first()->hal_idple);
        $this->assertSame('PERIODO LECTIVO 2026', $carreras->first()->ple_descri);
        $this->assertTrue($carreras->first()->hal_vigent);
        $this->assertFalse($carreras->last()->hal_vigent);
    }

    public function test_per_habilitacion_service_methods_use_targeted_queries_when_available(): void
    {
        $service = new class(collect([(object) ['mat_descri' => 'COMUNICACIÓN ORAL Y ESCRITA', 'inm_idrsc' => 971]]), collect([(object) ['mat_descri' => 'BASE DE DATOS I', 'aci_idhal' => 58655]]), collect([(object) ['aca_descri' => 'Cuota abril', 'deu_idrsc' => 971, 'deu_idple' => 20261]]), collect([(object) ['mat_descri' => 'COMUNICACIÓN ORAL Y ESCRITA', 'aal_idrsc' => 971, 'aal_idple' => 20261], (object) ['mat_descri' => 'ÁLGEBRA', 'aal_idrsc' => 971, 'aal_idple' => 20262]])) extends AlumnoExternoService
        {
            public array $captured = [];

            public function __construct(
                public Collection $materiasMock,
                public Collection $extractoMock,
                public Collection $deudasMock,
                public Collection $asistenciaMock,
            ) {}

            public function materiasInscriptas(int $aluId): Collection
            {
                throw new RuntimeException('Fallback de materias no deberia ejecutarse.');
            }

            public function extractoAcademico(int $aluId): Collection
            {
                throw new RuntimeException('Fallback de extracto no deberia ejecutarse.');
            }

            public function deudas(int $aluId): Collection
            {
                throw new RuntimeException('Fallback de deudas no deberia ejecutarse.');
            }

            public function asistencia(int $aluId): Collection
            {
                throw new RuntimeException('Fallback de asistencia no deberia ejecutarse.');
            }

            protected function queryMateriasPorRecurso(int $aluId, int $rscId): Collection
            {
                $this->captured['materias'] = [$aluId, $rscId];

                return $this->materiasMock;
            }

            protected function queryExtractoPorHabilitacion(int $aluId, int $halId): Collection
            {
                $this->captured['extracto'] = [$aluId, $halId];

                return $this->extractoMock;
            }

            protected function queryDeudasPorHabilitacion(int $aluId, int $rscId, ?int $periodoId = null): Collection
            {
                $this->captured['deudas'] = [$aluId, $rscId, $periodoId];

                return $this->deudasMock;
            }

            protected function queryAsistenciaPorRecurso(int $aluId, int $rscId): Collection
            {
                $this->captured['asistencia'] = [$aluId, $rscId];

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
        $this->assertSame([42178, 971], $service->captured['materias']);
        $this->assertSame([42178, 58655], $service->captured['extracto']);
        $this->assertSame([42178, 971, 20261], $service->captured['deudas']);
        $this->assertSame([42178, 971], $service->captured['asistencia']);
    }

    public function test_per_habilitacion_service_methods_fall_back_to_collection_filtering_on_query_errors(): void
    {
        $service = new class(collect([(object) ['mat_descri' => 'COMUNICACIÓN ORAL Y ESCRITA', 'inm_idrsc' => 971, 'inm_idhal' => 58655], (object) ['mat_descri' => 'ÁLGEBRA', 'inm_idrsc' => 999, 'inm_idhal' => 99999]]), collect([(object) ['mat_descri' => 'BASE DE DATOS I', 'aci_idhal' => 58655], (object) ['mat_descri' => 'MATEMÁTICA', 'aci_idhal' => 99999]]), collect([(object) ['aca_descri' => 'Cuota abril', 'deu_idrsc' => 971, 'deu_idple' => 20261], (object) ['aca_descri' => 'Cuota mayo', 'deu_idrsc' => 971, 'deu_idple' => 20262]]), collect([(object) ['mat_descri' => 'COMUNICACIÓN ORAL Y ESCRITA', 'aal_idrsc' => 971, 'aal_idple' => 20261], (object) ['mat_descri' => 'ÁLGEBRA', 'aal_idrsc' => 999, 'aal_idple' => 20261]])) extends AlumnoExternoService
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

            protected function queryMateriasPorRecurso(int $aluId, int $rscId): Collection
            {
                throw new QueryException('pgsql_externa', 'select * from materias', [], new RuntimeException('missing inm_idrsc'));
            }

            protected function queryExtractoPorHabilitacion(int $aluId, int $halId): Collection
            {
                throw new QueryException('pgsql_externa', 'select * from extracto', [], new RuntimeException('missing aci_idhal'));
            }

            protected function queryDeudasPorHabilitacion(int $aluId, int $rscId, ?int $periodoId = null): Collection
            {
                throw new QueryException('pgsql_externa', 'select * from deudas', [], new RuntimeException('missing deu_idrsc'));
            }

            protected function queryAsistenciaPorRecurso(int $aluId, int $rscId): Collection
            {
                throw new QueryException('pgsql_externa', 'select * from asistencia', [], new RuntimeException('missing aal_idrsc'));
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
