<?php

namespace Tests\Feature\Admin;

use App\Models\Docente;
use App\Models\EvaluacionDocente;
use App\Models\FormularioCriterio;
use App\Models\FormularioEvaluacion;
use App\Models\PeriodoEvaluacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminEvaluacionDocenteResultadosTest extends TestCase
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

    public function test_results_page_is_displayed_for_admin(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.evaluacion-docente.resultados'))
            ->assertOk()
            ->assertSeeVolt('admin.evaluacion-docente.resultados');
    }

    public function test_non_admin_user_cannot_access_results_page(): void
    {
        Role::findOrCreate('ALUMNO', 'web');

        /** @var User $alumno */
        $alumno = User::factory()->create();
        $alumno->assignRole('ALUMNO');

        $this->actingAs($alumno)
            ->get(route('admin.evaluacion-docente.resultados'))
            ->assertStatus(403);
    }

    public function test_results_page_shows_period_selector(): void
    {
        $admin = $this->adminUser();

        PeriodoEvaluacion::query()->create([
            'nombre' => 'Evaluación 2026-1',
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-06-30',
            'activo' => true,
        ]);

        PeriodoEvaluacion::query()->create([
            'nombre' => 'Evaluación 2025-2',
            'fecha_inicio' => '2025-08-01',
            'fecha_fin' => '2025-11-30',
            'activo' => false,
        ]);

        $this->actingAs($admin);

        Volt::test('admin.evaluacion-docente.resultados')
            ->assertSee('Evaluación 2026-1')
            ->assertSee('Evaluación 2025-2');
    }

    public function test_results_page_shows_empty_state_when_no_period_selected(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin);

        Volt::test('admin.evaluacion-docente.resultados')
            ->set('selectedPeriodoId', null)
            ->assertSee('Seleccioná un período');
    }

    public function test_results_page_shows_empty_state_for_period_without_evaluations(): void
    {
        $admin = $this->adminUser();

        PeriodoEvaluacion::query()->create([
            'nombre' => 'Evaluación Vacía',
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-06-30',
            'activo' => true,
        ]);

        $this->actingAs($admin);

        Volt::test('admin.evaluacion-docente.resultados')
            ->assertSee('Todavía no hay evaluaciones enviadas');
    }

    public function test_results_page_displays_correct_scores(): void
    {
        $admin = $this->adminUser();

        $periodo = PeriodoEvaluacion::query()->create([
            'nombre' => 'Evaluación 2026-1',
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-06-30',
            'activo' => true,
        ]);

        $formulario = FormularioEvaluacion::query()->create([
            'nombre' => 'Formulario Alumno 2026',
            'tipo_evaluador' => FormularioEvaluacion::TIPO_ALUMNO,
            'descripcion' => 'Formulario de prueba.',
            'activo' => true,
            'escala_min' => 1,
            'escala_max' => 5,
        ]);

        $criterio1 = FormularioCriterio::query()->create([
            'formulario_evaluacion_id' => $formulario->id,
            'pregunta' => 'Claridad de explicación',
            'peso' => 50,
            'orden' => 1,
            'tipo_respuesta' => FormularioCriterio::TIPO_ESCALA,
            'obligatoria' => true,
            'activo' => true,
        ]);

        $criterio2 = FormularioCriterio::query()->create([
            'formulario_evaluacion_id' => $formulario->id,
            'pregunta' => 'Dominio del contenido',
            'peso' => 50,
            'orden' => 2,
            'tipo_respuesta' => FormularioCriterio::TIPO_ESCALA,
            'obligatoria' => true,
            'activo' => true,
        ]);

        $docente = Docente::query()->create([
            'nombre' => 'Prof. Juan Pérez',
            'documento' => '1234567',
            'activo' => true,
        ]);

        $evaluador1 = User::factory()->create();
        $evaluador2 = User::factory()->create();

        // Evaluador 1 gives scores: 4 and 5 → weighted = (4*50 + 5*50)/100 = 4.50
        $eval1 = EvaluacionDocente::query()->create([
            'periodo_evaluacion_id' => $periodo->id,
            'formulario_evaluacion_id' => $formulario->id,
            'docente_id' => $docente->id,
            'evaluador_user_id' => $evaluador1->id,
            'tipo_evaluador' => FormularioEvaluacion::TIPO_ALUMNO,
            'puntaje_total' => 4.50,
            'estado' => EvaluacionDocente::ESTADO_ENVIADA,
            'fecha_envio' => now(),
            'docente_nombre_snapshot' => 'Prof. Juan Pérez',
            'docente_documento_snapshot' => '1234567',
            'contexto_snapshot' => [
                'car_id' => 10,
                'materias' => [
                    ['mi2_id' => 101, 'materia' => 'Álgebra Lineal', 'tur_id' => 1, 'turno' => 'Mañana'],
                    ['mi2_id' => 102, 'materia' => 'Cálculo I', 'tur_id' => 2, 'turno' => 'Tarde'],
                ],
            ],
        ]);

        $eval1->respuestas()->create([
            'formulario_criterio_id' => $criterio1->id,
            'valor_numerico' => 4,
        ]);

        $eval1->respuestas()->create([
            'formulario_criterio_id' => $criterio2->id,
            'valor_numerico' => 5,
        ]);

        // Evaluador 2 gives scores: 5 and 5 → weighted = 5.00
        $eval2 = EvaluacionDocente::query()->create([
            'periodo_evaluacion_id' => $periodo->id,
            'formulario_evaluacion_id' => $formulario->id,
            'docente_id' => $docente->id,
            'evaluador_user_id' => $evaluador2->id,
            'tipo_evaluador' => FormularioEvaluacion::TIPO_ALUMNO,
            'puntaje_total' => 5.00,
            'estado' => EvaluacionDocente::ESTADO_ENVIADA,
            'fecha_envio' => now(),
            'docente_nombre_snapshot' => 'Prof. Juan Pérez',
            'docente_documento_snapshot' => '1234567',
            'contexto_snapshot' => [
                'car_id' => 10,
                'materias' => [
                    ['mi2_id' => 101, 'materia' => 'Álgebra Lineal', 'tur_id' => 1, 'turno' => 'Mañana'],
                ],
            ],
        ]);

        $eval2->respuestas()->create([
            'formulario_criterio_id' => $criterio1->id,
            'valor_numerico' => 5,
        ]);

        $eval2->respuestas()->create([
            'formulario_criterio_id' => $criterio2->id,
            'valor_numerico' => 5,
        ]);

        $this->actingAs($admin);

        Volt::test('admin.evaluacion-docente.resultados')
            ->assertSee('Prof. Juan Pérez')
            ->assertSee('1234567')
            ->assertSee('2 evaluadores')
            ->assertSee('Álgebra Lineal')
            ->assertSee('Cálculo I')
            ->assertSee('Claridad de explicación')
            ->assertSee('Dominio del contenido');
    }

    public function test_results_page_excludes_draft_evaluations(): void
    {
        $admin = $this->adminUser();

        $periodo = PeriodoEvaluacion::query()->create([
            'nombre' => 'Evaluación 2026-1',
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-06-30',
            'activo' => true,
        ]);

        $formulario = FormularioEvaluacion::query()->create([
            'nombre' => 'Formulario Alumno 2026',
            'tipo_evaluador' => FormularioEvaluacion::TIPO_ALUMNO,
            'descripcion' => 'Formulario de prueba.',
            'activo' => true,
            'escala_min' => 1,
            'escala_max' => 5,
        ]);

        $docente = Docente::query()->create([
            'nombre' => 'Dra. María Gómez',
            'documento' => '7654321',
            'activo' => true,
        ]);

        $evaluador = User::factory()->create();

        // Borrador — should not appear
        EvaluacionDocente::query()->create([
            'periodo_evaluacion_id' => $periodo->id,
            'formulario_evaluacion_id' => $formulario->id,
            'docente_id' => $docente->id,
            'evaluador_user_id' => $evaluador->id,
            'tipo_evaluador' => FormularioEvaluacion::TIPO_ALUMNO,
            'puntaje_total' => 3.00,
            'estado' => EvaluacionDocente::ESTADO_BORRADOR,
            'fecha_envio' => null,
            'docente_nombre_snapshot' => 'Dra. María Gómez',
            'docente_documento_snapshot' => '7654321',
        ]);

        $this->actingAs($admin);

        $test = Volt::test('admin.evaluacion-docente.resultados');

        // Should show empty state since the only evaluation is a draft
        $this->assertEmpty($test->get('resultados'));
    }
}
