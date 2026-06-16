<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicUnit;
use App\Models\User;
use App\Models\UserAcademicUnitScope;
use Database\Seeders\AcademicUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAcademicUnitAdminsTest extends TestCase
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

    protected function unitAdminUser(): User
    {
        Role::findOrCreate('ADMIN_UNIDAD_ACADEMICA', 'web');

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('ADMIN_UNIDAD_ACADEMICA');

        return $user;
    }

    public function test_general_admin_can_view_the_academic_unit_admin_assignments_page(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $admin = $this->adminUser();
        $this->unitAdminUser();

        $this->actingAs($admin)
            ->get(route('admin.academic-unit-admins'))
            ->assertOk()
            ->assertSee('Administradores por Unidad Académica')
            ->assertSeeVolt('admin.administradores-unidades');
    }

    public function test_general_admin_can_assign_multiple_faculties_to_a_unit_admin(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $admin = $this->adminUser();
        $unitAdmin = $this->unitAdminUser();

        $agronomia = AcademicUnit::query()->firstWhere('slug', 'ingenieria-agronomica');
        $economicas = AcademicUnit::query()->firstWhere('slug', 'ciencias-economicas');

        $this->assertNotNull($agronomia);
        $this->assertNotNull($economicas);

        $this->actingAs($admin);

        Volt::test('admin.administradores-unidades')
            ->set("selectedAcademicUnitsByUser.{$unitAdmin->id}.{$agronomia->id}", true)
            ->set("selectedAcademicUnitsByUser.{$unitAdmin->id}.{$economicas->id}", true)
            ->call('saveScopes', $unitAdmin->id)
            ->assertHasNoErrors()
            ->assertSee($unitAdmin->email);

        $this->assertDatabaseHas('user_academic_unit_scopes', [
            'user_id' => $unitAdmin->id,
            'academic_unit_id' => $agronomia->id,
            'sed_id' => $agronomia->legacy_sede_ids[0],
        ]);

        $this->assertDatabaseHas('user_academic_unit_scopes', [
            'user_id' => $unitAdmin->id,
            'academic_unit_id' => $economicas->id,
            'sed_id' => $economicas->legacy_sede_ids[0],
        ]);
    }

    public function test_general_admin_can_assign_faculty_with_custom_sede(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $admin = $this->adminUser();
        $unitAdmin = $this->unitAdminUser();

        $agronomia = AcademicUnit::query()->firstWhere('slug', 'ingenieria-agronomica');
        $this->assertNotNull($agronomia);

        // Agronomía tiene 6 sedes: [1, 8, 21, 23, 27, 28] — elegir la segunda
        $customSedeId = $agronomia->legacy_sede_ids[1]; // sede 8
        $this->assertNotEquals($agronomia->legacy_sede_ids[0], $customSedeId);

        $this->actingAs($admin);

        Volt::test('admin.administradores-unidades')
            ->set("selectedAcademicUnitsByUser.{$unitAdmin->id}.{$agronomia->id}", true)
            ->set("selectedSedesByUser.{$unitAdmin->id}.{$agronomia->id}", $customSedeId)
            ->call('saveScopes', $unitAdmin->id)
            ->assertHasNoErrors()
            ->assertSee($unitAdmin->email);

        $this->assertDatabaseHas('user_academic_unit_scopes', [
            'user_id' => $unitAdmin->id,
            'academic_unit_id' => $agronomia->id,
            'sed_id' => $customSedeId,
        ]);

        $this->assertDatabaseMissing('user_academic_unit_scopes', [
            'user_id' => $unitAdmin->id,
            'academic_unit_id' => $agronomia->id,
            'sed_id' => $agronomia->legacy_sede_ids[0],
        ]);
    }

    public function test_badges_show_sede_information(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $admin = $this->adminUser();
        $unitAdmin = $this->unitAdminUser();

        $agronomia = AcademicUnit::query()->firstWhere('slug', 'ingenieria-agronomica');
        $this->assertNotNull($agronomia);

        $this->actingAs($admin);

        Volt::test('admin.administradores-unidades')
            ->set("selectedAcademicUnitsByUser.{$unitAdmin->id}.{$agronomia->id}", true)
            ->call('saveScopes', $unitAdmin->id)
            ->assertHasNoErrors()
            ->assertSee('(Sede: '.$agronomia->legacy_sede_ids[0].')');
    }

    public function test_admin_can_clear_all_scopes_from_unit_admin(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $admin = $this->adminUser();
        $unitAdmin = $this->unitAdminUser();

        $agronomia = AcademicUnit::query()->firstWhere('slug', 'ingenieria-agronomica');
        $this->assertNotNull($agronomia);

        $this->actingAs($admin);

        // First assign a faculty
        $component = Volt::test('admin.administradores-unidades')
            ->set("selectedAcademicUnitsByUser.{$unitAdmin->id}.{$agronomia->id}", true)
            ->call('saveScopes', $unitAdmin->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('user_academic_unit_scopes', [
            'user_id' => $unitAdmin->id,
        ]);

        // Now clear all scopes
        $component->call('clearScopes', $unitAdmin->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('user_academic_unit_scopes', [
            'user_id' => $unitAdmin->id,
        ]);
    }

    public function test_filter_by_academic_unit_shows_only_admins_with_that_faculty(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $admin = $this->adminUser();

        $unitAdmin1 = $this->unitAdminUser();
        $unitAdmin2 = $this->unitAdminUser();

        $agronomia = AcademicUnit::query()->firstWhere('slug', 'ingenieria-agronomica');
        $economicas = AcademicUnit::query()->firstWhere('slug', 'ciencias-economicas');
        $this->assertNotNull($agronomia);
        $this->assertNotNull($economicas);

        $this->actingAs($admin);

        $component = Volt::test('admin.administradores-unidades')
            ->assertSee($unitAdmin1->email)
            ->assertSee($unitAdmin2->email);

        // Assign agronomía only to admin 1
        $component
            ->set("selectedAcademicUnitsByUser.{$unitAdmin1->id}.{$agronomia->id}", true)
            ->call('saveScopes', $unitAdmin1->id);

        // Filter by agronomía — should only show admin 1
        $component
            ->set('filterAcademicUnit', (string) $agronomia->id)
            ->assertSee($unitAdmin1->email)
            ->assertDontSee($unitAdmin2->email);

        // Filter by económicas — should only show admin 2 once assigned
        $component
            ->set("selectedAcademicUnitsByUser.{$unitAdmin2->id}.{$economicas->id}", true)
            ->call('saveScopes', $unitAdmin2->id)
            ->set('filterAcademicUnit', (string) $economicas->id)
            ->assertSee($unitAdmin2->email)
            ->assertDontSee($unitAdmin1->email);
    }

    public function test_filter_by_no_scopes_shows_admins_without_faculties(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $admin = $this->adminUser();
        $unitAdmin = $this->unitAdminUser();

        $this->actingAs($admin);

        Volt::test('admin.administradores-unidades')
            ->set('filterAcademicUnit', '0')
            ->assertSee($unitAdmin->email)
            ->assertSee('Sin facultades asignadas');
    }

    public function test_unsaved_changes_indicator_appears_when_checkboxes_are_modified(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $admin = $this->adminUser();
        $unitAdmin = $this->unitAdminUser();

        $agronomia = AcademicUnit::query()->firstWhere('slug', 'ingenieria-agronomica');
        $this->assertNotNull($agronomia);

        $this->actingAs($admin);

        $component = Volt::test('admin.administradores-unidades');

        // Initially no unsaved changes badge
        $component->assertDontSee('Cambios sin guardar');

        // Check a faculty (change from original state)
        $component
            ->set("selectedAcademicUnitsByUser.{$unitAdmin->id}.{$agronomia->id}", true);

        // hasUnsavedChanges should return true (original is empty, current has 1)
        $this->assertTrue(
            $component->instance()->hasUnsavedChanges($unitAdmin->id)
        );
    }

    public function test_unit_admin_cannot_access_assignments_page(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $unitAdmin = $this->unitAdminUser();

        $this->actingAs($unitAdmin)
            ->get(route('admin.academic-unit-admins'))
            ->assertForbidden();
    }

    public function test_scope_records_who_assigned_and_when(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $admin = $this->adminUser();
        $unitAdmin = $this->unitAdminUser();

        $agronomia = AcademicUnit::query()->firstWhere('slug', 'ingenieria-agronomica');
        $this->assertNotNull($agronomia);

        $this->actingAs($admin);

        Volt::test('admin.administradores-unidades')
            ->set("selectedAcademicUnitsByUser.{$unitAdmin->id}.{$agronomia->id}", true)
            ->call('saveScopes', $unitAdmin->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('user_academic_unit_scopes', [
            'user_id' => $unitAdmin->id,
            'academic_unit_id' => $agronomia->id,
            'assigned_by' => $admin->id,
        ]);

        $scope = UserAcademicUnitScope::query()
            ->where('user_id', $unitAdmin->id)
            ->first();

        $this->assertNotNull($scope);
        $this->assertNotNull($scope->assigned_at);
        $this->assertEquals($admin->id, $scope->assigned_by);
    }

    public function test_trazability_is_displayed_in_ui(): void
    {
        $this->seed(AcademicUnitSeeder::class);

        $admin = $this->adminUser();
        $unitAdmin = $this->unitAdminUser();

        $agronomia = AcademicUnit::query()->firstWhere('slug', 'ingenieria-agronomica');
        $this->assertNotNull($agronomia);

        $this->actingAs($admin);

        Volt::test('admin.administradores-unidades')
            ->set("selectedAcademicUnitsByUser.{$unitAdmin->id}.{$agronomia->id}", true)
            ->call('saveScopes', $unitAdmin->id)
            ->assertHasNoErrors()
            ->assertSee('Asignado por '.$admin->name);
    }
}
