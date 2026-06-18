<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicUnit;
use App\Models\User;
use App\Models\UserAcademicUnitScope;
use App\Services\AlumnoExternoService;
use Database\Seeders\AcademicUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Volt\Volt;
use Mockery;
use Spatie\Permission\Models\Role;
use stdClass;
use Tests\TestCase;

class AdminConsultaAlumnoScopeTest extends TestCase
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
            'sed_id' => $academicUnit->primarySedeId(),
        ]);

        return $user;
    }

    public function test_general_admin_and_scoped_unit_admin_can_open_student_consulta(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $admin = $this->adminUser();
        $unitAdmin = $this->unitAdminForAcademicUnit('ingenieria-agronomica');

        $this->actingAs($admin)
            ->get(route('admin.consulta-alumno'))
            ->assertOk();

        $this->actingAs($unitAdmin)
            ->get(route('admin.consulta-alumno'))
            ->assertOk()
            ->assertSee('Consulta restringida por facultad');
    }

    public function test_unit_admin_can_only_consult_students_from_managed_academic_units(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $unitAdmin = $this->unitAdminForAcademicUnit('ingenieria-agronomica');

        $allowedStudent = new stdClass;
        $allowedStudent->alu_id = 42;
        $allowedStudent->alu_nombre = 'Juan';
        $allowedStudent->alu_apellido = 'Perez';
        $allowedStudent->alu_perdoc = '1234567';

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('resolverAlumno')
            ->once()
            ->with('1234567')
            ->andReturn($allowedStudent);
        $service->shouldReceive('carreras')
            ->once()
            ->with(42)
            ->andReturn(new Collection([(object) ['sed_id' => 1, 'uac_descri' => 'Facultad de Ingeniería Agronómica']]));
        $service->shouldReceive('materiasInscriptas')
            ->once()
            ->with(42)
            ->andReturn(new Collection([(object) ['rsc_idsed' => 1]]));
        $service->shouldReceive('extractoAcademico')->once()->with(42)->andReturn(new Collection);
        $service->shouldReceive('deudas')->once()->with(42)->andReturn(new Collection);
        $service->shouldReceive('asistencia')->once()->with(42)->andReturn(new Collection);
        $service->shouldReceive('mallaCurricular')->once()->with(42)->andReturn(new Collection);
        $service->shouldReceive('certificados')->once()->with(42)->andReturn(new Collection);

        $this->app->instance(AlumnoExternoService::class, $service);
        $this->actingAs($unitAdmin);

        Volt::test('admin.consulta-alumno')
            ->set('documento', '1234567')
            ->call('buscar')
            ->assertHasNoErrors()
            ->assertSee('Juan Perez')
            ->assertDontSee('Solo podés consultar alumnos vinculados a las facultades que tenés asignadas.');
    }

    public function test_unit_admin_is_blocked_when_student_is_outside_managed_academic_units(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $unitAdmin = $this->unitAdminForAcademicUnit('ingenieria-agronomica');

        $externalStudent = new stdClass;
        $externalStudent->alu_id = 99;
        $externalStudent->alu_nombre = 'Ana';
        $externalStudent->alu_apellido = 'Lopez';
        $externalStudent->alu_perdoc = '7654321';

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('resolverAlumno')
            ->once()
            ->with('7654321')
            ->andReturn($externalStudent);
        $service->shouldReceive('carreras')
            ->once()
            ->with(99)
            ->andReturn(new Collection([(object) ['sed_id' => 3, 'uac_descri' => 'Facultad de Ciencias Económicas']]));
        $service->shouldReceive('materiasInscriptas')
            ->once()
            ->with(99)
            ->andReturn(new Collection([(object) ['rsc_idsed' => 3]]));
        $service->shouldNotReceive('extractoAcademico');
        $service->shouldNotReceive('deudas');
        $service->shouldNotReceive('asistencia');
        $service->shouldNotReceive('mallaCurricular');
        $service->shouldNotReceive('certificados');

        $this->app->instance(AlumnoExternoService::class, $service);
        $this->actingAs($unitAdmin);

        Volt::test('admin.consulta-alumno')
            ->set('documento', '7654321')
            ->call('buscar')
            ->assertHasNoErrors()
            ->assertSee('Solo podés consultar alumnos vinculados a las facultades que tenés asignadas.');
    }
}
