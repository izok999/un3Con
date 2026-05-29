<?php

namespace Tests\Feature\Admin;

use App\Models\FormularioEvaluacion;
use App\Models\PeriodoEvaluacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminEvaluacionDocenteConfigurationTest extends TestCase
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

    public function test_configuration_page_is_displayed(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.evaluacion-docente.configuracion'))
            ->assertOk()
            ->assertSee('Configuracion de Evaluacion Docente')
            ->assertSeeVolt('admin.evaluacion-docente.configuracion');
    }

    public function test_admin_can_create_an_active_period_and_deactivate_the_previous_one(): void
    {
        $admin = $this->adminUser();

        $periodoAnterior = PeriodoEvaluacion::query()->create([
            'nombre' => 'Periodo Lectivo 2025',
            'fecha_inicio' => '2025-02-01',
            'fecha_fin' => '2025-11-30',
            'activo' => true,
        ]);

        $this->actingAs($admin);

        Volt::test('admin.evaluacion-docente.configuracion')
            ->set('periodoForm.nombre', 'Periodo Lectivo 2026')
            ->set('periodoForm.fecha_inicio', '2026-02-01')
            ->set('periodoForm.fecha_fin', '2026-11-30')
            ->set('periodoForm.activo', true)
            ->call('savePeriodo')
            ->assertHasNoErrors()
            ->assertSee('Periodo Lectivo 2026');

        $this->assertDatabaseHas('periodos_evaluacion', [
            'nombre' => 'Periodo Lectivo 2026',
            'activo' => true,
        ]);

        $this->assertFalse($periodoAnterior->refresh()->activo);
    }

    public function test_admin_can_create_a_formulario_and_add_a_criterio(): void
    {
        $admin = $this->adminUser();

        $formularioAnterior = FormularioEvaluacion::query()->create([
            'nombre' => 'Formulario Alumno 2025',
            'tipo_evaluador' => FormularioEvaluacion::TIPO_ALUMNO,
            'descripcion' => 'Formulario previo.',
            'activo' => true,
            'escala_min' => 1,
            'escala_max' => 5,
        ]);

        $this->actingAs($admin);

        Volt::test('admin.evaluacion-docente.configuracion')
            ->set('formularioForm.nombre', 'Formulario Alumno 2026')
            ->set('formularioForm.tipo_evaluador', FormularioEvaluacion::TIPO_ALUMNO)
            ->set('formularioForm.descripcion', 'Formulario operativo para el nuevo periodo.')
            ->set('formularioForm.escala_min', '1')
            ->set('formularioForm.escala_max', '5')
            ->set('formularioForm.activo', true)
            ->call('saveFormulario')
            ->assertHasNoErrors()
            ->assertSee('Formulario Alumno 2026')
            ->set('criterioForm.pregunta', 'Explica los contenidos con claridad.')
            ->set('criterioForm.descripcion', 'Evalua la claridad expositiva del docente.')
            ->set('criterioForm.peso', '25')
            ->set('criterioForm.orden', '1')
            ->set('criterioForm.tipo_respuesta', 'escala')
            ->set('criterioForm.obligatoria', true)
            ->call('saveCriterio')
            ->assertHasNoErrors()
            ->assertSee('Explica los contenidos con claridad.');

        $formularioNuevo = FormularioEvaluacion::query()
            ->where('nombre', 'Formulario Alumno 2026')
            ->firstOrFail();

        $this->assertTrue($formularioNuevo->activo);
        $this->assertFalse($formularioAnterior->refresh()->activo);

        $this->assertDatabaseHas('formulario_criterios', [
            'formulario_evaluacion_id' => $formularioNuevo->id,
            'pregunta' => 'Explica los contenidos con claridad.',
            'orden' => 1,
            'tipo_respuesta' => 'escala',
            'activo' => true,
        ]);
    }
}
