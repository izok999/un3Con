<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicUnit;
use App\Models\User;
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
            ->set("selectedAcademicUnitsByUser.{$unitAdmin->id}", [(string) $agronomia->id, (string) $economicas->id])
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
}
