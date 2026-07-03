<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicUnit;
use App\Models\Docente;
use App\Models\DocenteContexto;
use App\Models\PeriodoEvaluacion;
use App\Models\User;
use App\Models\UserAcademicUnitScope;
use App\Services\AlumnoExternoService;
use Database\Seeders\AcademicUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminEvaluacionDocenteManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function adminUser(): User
    {
        Role::findOrCreate('ADMIN', 'web');

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('ADMIN');

        return $user;
    }

    protected function unitAdminUser(array $sedeIds = [8]): User
    {
        Role::findOrCreate('ADMIN_UNIDAD_ACADEMICA', 'web');

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('ADMIN_UNIDAD_ACADEMICA');

        foreach ($sedeIds as $sedeId) {
            UserAcademicUnitScope::query()->create([
                'user_id' => $user->id,
                'sed_id' => $sedeId,
            ]);
        }

        return $user;
    }

    protected function unitAdminForAcademicUnit(string $slug): User
    {
        Role::findOrCreate('ADMIN_UNIDAD_ACADEMICA', 'web');

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('ADMIN_UNIDAD_ACADEMICA');

        $academicUnit = AcademicUnit::query()->where('slug', $slug)->firstOrFail();

        UserAcademicUnitScope::query()->create([
            'user_id' => $user->id,
            'academic_unit_id' => $academicUnit->id,
            'sed_id' => $academicUnit->legacy_sede_ids[0],
        ]);

        return $user;
    }

    public function test_admin_page_is_displayed(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.evaluacion-docente.docentes'))
            ->assertOk()
            ->assertSee('Docentes para Evaluación')
            ->assertSeeVolt('admin.evaluacion-docente.docentes');
    }

    public function test_admin_can_create_a_docente_from_the_management_screen(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin);

        Volt::test('admin.evaluacion-docente.docentes')
            ->call('inicializarComponente')
            ->set('docenteForm.nombre', 'Grace Hopper')
            ->set('docenteForm.documento', '2222222')
            ->set('docenteForm.docente_externo_id', '9101')
            ->call('saveDocente')
            ->assertHasNoErrors()
            ->assertSee('Grace Hopper');

        $this->assertDatabaseHas('docentes', [
            'nombre' => 'Grace Hopper',
            'documento' => '2222222',
            'docente_externo_id' => 9101,
            'activo' => true,
        ]);
    }

    public function test_admin_can_add_a_context_for_an_existing_docente(): void
    {
        $admin = $this->adminUser();

        $docente = Docente::query()->create([
            'nombre' => 'Katherine Johnson',
            'documento' => '4444444',
            'docente_externo_id' => 9103,
            'activo' => true,
        ]);

        $this->actingAs($admin);

        // Context management happens in the child component
        $testable = Volt::test('admin.evaluacion-docente.docente-contextos', [
            'selectedDocenteId' => $docente->id,
            'allowedSedeIds' => [],
        ])
            ->set('contextoForm.sed_id', '8')
            ->set('contextoForm.car_id', '14')
            ->set('contextoForm.mi2_id', '301')
            ->set('contextoForm.ple_id', '2026')
            ->set('contextoForm.tur_id', '2')
            ->set('contextoForm.sec_id', '4')
            ->call('saveContexto')
            ->assertHasNoErrors()
            ->assertSee('Katherine Johnson');

        $this->assertDatabaseHas('docente_contextos', [
            'docente_id' => $docente->id,
            'car_id' => 14,
            'sed_id' => 8,
            'ple_id' => 2026,
            'mi2_id' => 301,
            'tur_id' => 2,
            'sec_id' => 4,
            'activo' => true,
        ]);
    }

    public function test_admin_can_add_a_context_with_periodo_evaluacion(): void
    {
        $admin = $this->adminUser();

        $periodo = PeriodoEvaluacion::query()->create([
            'nombre' => 'Evaluación 2026-1',
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-06-30',
            'activo' => true,
        ]);

        $docente = Docente::query()->create([
            'nombre' => 'Alan Turing',
            'documento' => '7777777',
            'docente_externo_id' => 9106,
            'activo' => true,
        ]);

        $this->actingAs($admin);

        Volt::test('admin.evaluacion-docente.docente-contextos', [
            'selectedDocenteId' => $docente->id,
            'allowedSedeIds' => [],
        ])
            ->set('contextoForm.sed_id', '8')
            ->set('contextoForm.car_id', '14')
            ->set('contextoForm.mi2_id', '301')
            ->set('contextoForm.ple_id', '2026')
            ->set('contextoForm.periodo_evaluacion_id', (string) $periodo->id)
            ->set('contextoForm.tur_id', '2')
            ->call('saveContexto')
            ->assertHasNoErrors()
            ->assertSee('Alan Turing');

        $this->assertDatabaseHas('docente_contextos', [
            'docente_id' => $docente->id,
            'car_id' => 14,
            'sed_id' => 8,
            'periodo_evaluacion_id' => $periodo->id,
        ]);
    }

    public function test_unit_admin_can_access_the_management_screen_when_scoped(): void
    {
        $unitAdmin = $this->unitAdminUser([8]);

        $this->actingAs($unitAdmin)
            ->get(route('admin.evaluacion-docente.docentes'))
            ->assertOk()
            ->assertSee('Docentes para Evaluación')
            ->assertSee(route('admin.consulta-alumno'), false)
            ->assertDontSee(route('admin.academic-unit-admins'), false)
            ->assertDontSee(route('admin.evaluacion-docente.configuracion'), false);
    }

    public function test_unit_admin_can_only_add_contexts_for_assigned_sedes(): void
    {
        $unitAdmin = $this->unitAdminUser([8]);

        $docente = Docente::query()->create([
            'nombre' => 'Margaret Hamilton',
            'documento' => '5555555',
            'docente_externo_id' => 9104,
            'activo' => true,
        ]);

        $this->actingAs($unitAdmin);

        Volt::test('admin.evaluacion-docente.docente-contextos', [
            'selectedDocenteId' => $docente->id,
            'allowedSedeIds' => [8],
        ])
            ->set('contextoForm.sed_id', '8')
            ->set('contextoForm.ple_id', '2026')
            ->call('saveContexto')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('docente_contextos', [
            'docente_id' => $docente->id,
            'sed_id' => 8,
        ]);

        Volt::test('admin.evaluacion-docente.docente-contextos', [
            'selectedDocenteId' => $docente->id,
            'allowedSedeIds' => [8],
        ])
            ->set('contextoForm.sed_id', '9')
            ->set('contextoForm.ple_id', '2026')
            ->call('saveContexto')
            ->assertHasErrors(['contextoForm.sed_id']);

        $this->assertDatabaseMissing('docente_contextos', [
            'docente_id' => $docente->id,
            'sed_id' => 9,
        ]);
    }

    public function test_unit_admin_scope_by_faculty_expands_to_all_legacy_sedes_of_that_faculty(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $unitAdmin = $this->unitAdminForAcademicUnit('ingenieria-agronomica');

        $docente = Docente::query()->create([
            'nombre' => 'Norman Borlaug',
            'documento' => '6666666',
            'docente_externo_id' => 9105,
            'activo' => true,
        ]);

        $this->actingAs($unitAdmin);

        // Sede 1 is allowed (from academic unit scope)
        Volt::test('admin.evaluacion-docente.docente-contextos', [
            'selectedDocenteId' => $docente->id,
            'allowedSedeIds' => $unitAdmin->managedSedeIds(),
        ])
            ->set('contextoForm.sed_id', '1')
            ->set('contextoForm.ple_id', '2026')
            ->call('saveContexto')
            ->assertHasNoErrors();

        Volt::test('admin.evaluacion-docente.docente-contextos', [
            'selectedDocenteId' => $docente->id,
            'allowedSedeIds' => $unitAdmin->managedSedeIds(),
        ])
            ->set('contextoForm.sed_id', '8')
            ->set('contextoForm.ple_id', '2026')
            ->call('saveContexto')
            ->assertHasNoErrors();

        Volt::test('admin.evaluacion-docente.docente-contextos', [
            'selectedDocenteId' => $docente->id,
            'allowedSedeIds' => $unitAdmin->managedSedeIds(),
        ])
            ->set('contextoForm.sed_id', '3')
            ->set('contextoForm.ple_id', '2026')
            ->call('saveContexto')
            ->assertHasErrors(['contextoForm.sed_id']);

        $this->assertDatabaseHas('docente_contextos', [
            'docente_id' => $docente->id,
            'sed_id' => 1,
        ]);

        $this->assertDatabaseHas('docente_contextos', [
            'docente_id' => $docente->id,
            'sed_id' => 8,
        ]);

        $this->assertDatabaseMissing('docente_contextos', [
            'docente_id' => $docente->id,
            'sed_id' => 3,
        ]);
    }

    public function test_sync_imports_contextos_from_external_service(): void
    {
        $admin = $this->adminUser();

        $docente = Docente::query()->create([
            'nombre' => 'Dorothy Vaughan',
            'documento' => '3333333',
            'activo' => true,
        ]);

        $this->mock(AlumnoExternoService::class, function (MockInterface $mock) use ($docente): void {
            $mock->shouldReceive('contextosDocentePorDocumento')
                ->once()
                ->with($docente->documento)
                ->andReturn(collect([
                    ['car_id' => 14, 'sed_id' => 3, 'ple_id' => 68, 'mi2_id' => 6630, 'tur_id' => 2, 'sec_id' => 8],
                ]));
            $mock->shouldReceive('catCarreras')->andReturn([]);
            $mock->shouldReceive('catSedes')->andReturn([]);
            $mock->shouldReceive('catPeriodos')->andReturn([]);
            $mock->shouldReceive('catTurnos')->andReturn([]);
            $mock->shouldReceive('catSecciones')->andReturn([]);
            $mock->shouldReceive('catMateriasPorIds')->andReturn([]);
        });

        $this->actingAs($admin);

        Volt::test('admin.evaluacion-docente.docente-contextos', [
            'selectedDocenteId' => $docente->id,
        ])
            ->call('sincronizarContextosDocente')
            ->assertHasNoErrors();

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
    }

    public function test_sync_is_idempotent_for_existing_contextos(): void
    {
        $admin = $this->adminUser();

        $docente = Docente::query()->create([
            'nombre' => 'Katherine Johnson',
            'documento' => '4444444',
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

        $this->mock(AlumnoExternoService::class, function (MockInterface $mock) use ($docente): void {
            $mock->shouldReceive('contextosDocentePorDocumento')
                ->once()
                ->with($docente->documento)
                ->andReturn(collect([
                    ['car_id' => 14, 'sed_id' => 3, 'ple_id' => 68, 'mi2_id' => 6630, 'tur_id' => 2, 'sec_id' => 8],
                ]));
            $mock->shouldReceive('catCarreras')->andReturn([]);
            $mock->shouldReceive('catSedes')->andReturn([]);
            $mock->shouldReceive('catPeriodos')->andReturn([]);
            $mock->shouldReceive('catTurnos')->andReturn([]);
            $mock->shouldReceive('catSecciones')->andReturn([]);
            $mock->shouldReceive('catMateriasPorIds')->andReturn([]);
        });

        $this->actingAs($admin);

        Volt::test('admin.evaluacion-docente.docente-contextos', [
            'selectedDocenteId' => $docente->id,
        ])
            ->call('sincronizarContextosDocente')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('docente_contextos', 1);
    }

    public function test_form_warns_and_does_not_leak_unfiltered_catalog_when_external_query_fails(): void
    {
        $admin = $this->adminUser();

        $docente = Docente::query()->create([
            'nombre' => 'Ada Lovelace',
            'documento' => '5555555',
            'activo' => true,
        ]);

        $this->mock(AlumnoExternoService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('catCarreras')->andReturn(['999' => 'CARRERA NO RELACIONADA']);
            $mock->shouldReceive('catSedes')->andReturn(['8' => 'Sede Central']);
            $mock->shouldReceive('catPeriodos')->andReturn([]);
            $mock->shouldReceive('catTurnos')->andReturn([]);
            $mock->shouldReceive('catSecciones')->andReturn([]);
            $mock->shouldReceive('catCarrerasPorSede')
                ->with(8)
                ->andThrow(new \RuntimeException('external db unreachable'));
        });

        $this->actingAs($admin);

        $testable = Volt::test('admin.evaluacion-docente.docente-contextos', [
            'selectedDocenteId' => $docente->id,
            'allowedSedeIds' => [],
        ])
            ->set('contextoForm.sed_id', '8');

        // The unfiltered global catalog ("CARRERA NO RELACIONADA") must never be
        // presented as if it were the sede-filtered result.
        $testable->assertDontSee('CARRERA NO RELACIONADA');
        $this->assertSame([], $testable->instance()->formCarreras);
        $testable->assertSee('No se pudo consultar el catálogo externo de carreras');

        // Switching sede clears the stale warning instead of leaving it stuck.
        $testable->set('contextoForm.sed_id', '');
        $testable->assertDontSee('No se pudo consultar el catálogo externo de carreras');
    }

    public function test_docente_list_is_paginated_ten_per_page(): void
    {
        $admin = $this->adminUser();

        foreach (range(1, 12) as $i) {
            Docente::query()->create([
                'nombre' => sprintf('Docente %02d', $i),
                'documento' => (string) (1000000 + $i),
                'activo' => true,
            ]);
        }

        $this->actingAs($admin);

        $testable = Volt::test('admin.evaluacion-docente.docentes')
            ->call('inicializarComponente');

        $testable->assertSee('Docente 01')->assertDontSee('Docente 11');

        $this->assertSame(10, $testable->instance()->docentes->count());
        $this->assertSame(12, $testable->instance()->docentes->total());

        $testable->call('gotoPage', 2);
        $testable->assertSee('Docente 11')->assertDontSee('Docente 01');
    }

    public function test_has_unsaved_changes_detects_dirty_form_and_resets_after_save(): void
    {
        $admin = $this->adminUser();

        $docente = Docente::query()->create([
            'nombre' => 'Ada Lovelace',
            'documento' => '2222222',
            'activo' => true,
        ]);

        $this->actingAs($admin);

        $testable = Volt::test('admin.evaluacion-docente.docentes')
            ->call('inicializarComponente')
            ->call('editDocente', $docente->id);

        $this->assertFalse($testable->instance()->hasUnsavedChanges());

        $testable->set('docenteForm.nombre', 'Ada Lovelace Editado');
        $this->assertTrue($testable->instance()->hasUnsavedChanges());

        $testable->call('saveDocente');
        $this->assertFalse($testable->instance()->hasUnsavedChanges());
    }

    public function test_sync_adds_error_when_docente_has_no_documento(): void
    {
        $admin = $this->adminUser();

        $docente = Docente::query()->create([
            'nombre' => 'Sin Documento',
            'documento' => null,
            'activo' => true,
        ]);

        $this->actingAs($admin);

        Volt::test('admin.evaluacion-docente.docente-contextos', [
            'selectedDocenteId' => $docente->id,
        ])
            ->call('sincronizarContextosDocente')
            ->assertHasErrors(['sync']);

        $this->assertDatabaseCount('docente_contextos', 0);
    }
}
