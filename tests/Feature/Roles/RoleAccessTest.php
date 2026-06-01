<?php

namespace Tests\Feature\Roles;

use App\Enums\RoleName;
use App\Models\AcademicUnit;
use App\Models\User;
use App\Models\UserAcademicUnitScope;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_creates_all_expected_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertEqualsCanonicalizing(
            RoleName::values(),
            Role::query()->pluck('name')->all(),
        );
    }

    public function test_general_admin_and_unit_admin_can_access_admin_dashboard(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $admin = $this->userWithRole(RoleName::Admin);
        $unitAdmin = $this->userWithRole(RoleName::AdminUnidadAcademica);
        $agronomia = AcademicUnit::query()->firstWhere('slug', 'ingenieria-agronomica');

        $this->assertNotNull($agronomia);

        UserAcademicUnitScope::query()->create([
            'user_id' => $unitAdmin->id,
            'academic_unit_id' => $agronomia->id,
            'sed_id' => $agronomia->legacy_sede_ids[0],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Admin');

        $this->actingAs($unitAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Admin');
    }

    public function test_unit_admin_without_scope_is_forbidden_from_shared_admin_routes(): void
    {
        $unitAdmin = $this->userWithRole(RoleName::AdminUnidadAcademica);

        $this->actingAs($unitAdmin)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($unitAdmin)
            ->get(route('admin.evaluacion-docente.docentes'))
            ->assertForbidden();
    }

    public function test_unit_admin_can_not_access_global_admin_routes_even_with_scope(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $unitAdmin = $this->userWithRole(RoleName::AdminUnidadAcademica);
        $agronomia = AcademicUnit::query()->firstWhere('slug', 'ingenieria-agronomica');

        $this->assertNotNull($agronomia);

        UserAcademicUnitScope::query()->create([
            'user_id' => $unitAdmin->id,
            'academic_unit_id' => $agronomia->id,
            'sed_id' => $agronomia->legacy_sede_ids[0],
        ]);

        $this->actingAs($unitAdmin)
            ->get(route('admin.academic-unit-admins'))
            ->assertForbidden();

        $this->actingAs($unitAdmin)
            ->get(route('admin.consulta-alumno'))
            ->assertOk();

        $this->actingAs($unitAdmin)
            ->get(route('admin.evaluacion-docente.configuracion'))
            ->assertForbidden();
    }

    public function test_funcionario_can_access_welcome_but_not_admin_dashboard(): void
    {
        $funcionario = $this->userWithRole(RoleName::Funcionario);

        $this->actingAs($funcionario)
            ->get(route('welcome'))
            ->assertOk();

        $this->actingAs($funcionario)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_academic_unit_seeder_creates_the_provided_faculty_catalog(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $this->assertSame(6, AcademicUnit::query()->count());
        $this->assertDatabaseHas('academic_units', [
            'slug' => 'ingenieria-agronomica',
            'name' => 'Facultad de Ingeniería Agronómica',
        ]);
        $this->assertDatabaseHas('academic_units', [
            'slug' => 'politecnica',
            'name' => 'Facultad Politécnica',
        ]);
    }

    public function test_academic_unit_scope_expands_to_all_legacy_sedes_of_that_faculty(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $unitAdmin = $this->userWithRole(RoleName::AdminUnidadAcademica);
        $academicUnit = AcademicUnit::query()->firstWhere('slug', 'ingenieria-agronomica');

        $this->assertNotNull($academicUnit);

        UserAcademicUnitScope::query()->create([
            'user_id' => $unitAdmin->id,
            'academic_unit_id' => $academicUnit->id,
            'sed_id' => $academicUnit->legacy_sede_ids[0],
        ]);

        $this->assertSame([1, 8, 21, 23, 27, 28], $unitAdmin->fresh()->managedSedeIds());
        $this->assertTrue($unitAdmin->fresh()->canManageSede(1));
        $this->assertTrue($unitAdmin->fresh()->canManageSede(8));
        $this->assertFalse($unitAdmin->fresh()->canManageSede(3));
    }

    protected function userWithRole(RoleName $roleName): User
    {
        Role::findOrCreate($roleName->value, 'web');

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole($roleName->value);

        return $user;
    }
}
