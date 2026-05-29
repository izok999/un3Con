<?php

namespace Tests\Feature\Admin;

use App\Models\Docente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
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

        Volt::test('admin.evaluacion-docente.docentes')
            ->call('selectDocente', $docente->id)
            ->set('contextoForm.car_id', '14')
            ->set('contextoForm.sed_id', '8')
            ->set('contextoForm.ple_id', '2026')
            ->set('contextoForm.mi2_id', '301')
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
}
