<?php

namespace Database\Seeders;

use App\Models\FormularioEvaluacion;
use Illuminate\Database\Seeder;

class FormularioEvaluacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedFormularioAlumno();
        $this->seedFormularioFuncionario();
    }

    protected function seedFormularioAlumno(): void
    {
        $formulario = FormularioEvaluacion::query()->updateOrCreate(
            [
                'nombre' => 'Evaluacion docente por alumno',
                'tipo_evaluador' => FormularioEvaluacion::TIPO_ALUMNO,
            ],
            [
                'descripcion' => 'Formulario de percepción estudiantil sobre el desempeño docente.',
                'activo' => true,
                'escala_min' => 1,
                'escala_max' => 5,
            ],
        );

        $this->upsertCriterios($formulario, [
            ['pregunta' => 'Explica los contenidos con claridad.', 'peso' => 20, 'orden' => 1, 'tipo_respuesta' => 'escala', 'obligatoria' => true],
            ['pregunta' => 'Demuestra dominio del contenido desarrollado.', 'peso' => 20, 'orden' => 2, 'tipo_respuesta' => 'escala', 'obligatoria' => true],
            ['pregunta' => 'Utiliza metodologías que favorecen el aprendizaje.', 'peso' => 20, 'orden' => 3, 'tipo_respuesta' => 'escala', 'obligatoria' => true],
            ['pregunta' => 'Cumple con la puntualidad y la carga horaria.', 'peso' => 15, 'orden' => 4, 'tipo_respuesta' => 'escala', 'obligatoria' => true],
            ['pregunta' => 'Evalúa de forma coherente con lo desarrollado en clase.', 'peso' => 15, 'orden' => 5, 'tipo_respuesta' => 'escala', 'obligatoria' => true],
            ['pregunta' => 'Mantiene una comunicación respetuosa y efectiva con el grupo.', 'peso' => 10, 'orden' => 6, 'tipo_respuesta' => 'escala', 'obligatoria' => true],
            ['pregunta' => 'Observaciones generales', 'peso' => 0, 'orden' => 7, 'tipo_respuesta' => 'texto', 'obligatoria' => false],
        ]);
    }

    protected function seedFormularioFuncionario(): void
    {
        $formulario = FormularioEvaluacion::query()->updateOrCreate(
            [
                'nombre' => 'Evaluacion docente por funcionario',
                'tipo_evaluador' => FormularioEvaluacion::TIPO_FUNCIONARIO,
            ],
            [
                'descripcion' => 'Formulario de supervisión académica para funcionarios/coordinadores.',
                'activo' => true,
                'escala_min' => 1,
                'escala_max' => 5,
            ],
        );

        $this->upsertCriterios($formulario, [
            ['pregunta' => 'Planifica sus actividades académicas con anticipación.', 'peso' => 20, 'orden' => 1, 'tipo_respuesta' => 'escala', 'obligatoria' => true],
            ['pregunta' => 'Cumple con los compromisos y cronogramas establecidos.', 'peso' => 20, 'orden' => 2, 'tipo_respuesta' => 'escala', 'obligatoria' => true],
            ['pregunta' => 'Demuestra dominio técnico y actualización disciplinar.', 'peso' => 20, 'orden' => 3, 'tipo_respuesta' => 'escala', 'obligatoria' => true],
            ['pregunta' => 'Aplica estrategias metodológicas adecuadas al contexto.', 'peso' => 15, 'orden' => 4, 'tipo_respuesta' => 'escala', 'obligatoria' => true],
            ['pregunta' => 'Gestiona el aula o grupo con orden y eficacia.', 'peso' => 15, 'orden' => 5, 'tipo_respuesta' => 'escala', 'obligatoria' => true],
            ['pregunta' => 'Asume responsabilidad institucional y trabajo colaborativo.', 'peso' => 10, 'orden' => 6, 'tipo_respuesta' => 'escala', 'obligatoria' => true],
            ['pregunta' => 'Observaciones generales', 'peso' => 0, 'orden' => 7, 'tipo_respuesta' => 'texto', 'obligatoria' => false],
        ]);
    }

    /**
     * @param  array<int, array{pregunta: string, peso: int, orden: int, tipo_respuesta: string, obligatoria: bool}>  $criterios
     */
    protected function upsertCriterios(FormularioEvaluacion $formulario, array $criterios): void
    {
        foreach ($criterios as $criterio) {
            $formulario->criterios()->updateOrCreate(
                ['orden' => $criterio['orden']],
                [
                    'pregunta' => $criterio['pregunta'],
                    'peso' => $criterio['peso'],
                    'tipo_respuesta' => $criterio['tipo_respuesta'],
                    'obligatoria' => $criterio['obligatoria'],
                    'activo' => true,
                ],
            );
        }
    }
}
