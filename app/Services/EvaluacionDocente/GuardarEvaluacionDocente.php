<?php

namespace App\Services\EvaluacionDocente;

use App\Models\Docente;
use App\Models\DocenteContexto;
use App\Models\EvaluacionDocente;
use App\Models\FormularioCriterio;
use App\Models\FormularioEvaluacion;
use App\Models\PeriodoEvaluacion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuardarEvaluacionDocente
{
    /**
     * @param  array<int, array{formulario_criterio_id: int, valor_numerico?: int|float|string|null, valor_texto?: string|null, observacion?: string|null}>  $respuestas
     * @param  array<string, mixed>  $contextoSnapshot
     */
    public function __construct(public PuntajeCalculator $puntajeCalculator) {}

    public function guardar(
        PeriodoEvaluacion $periodo,
        FormularioEvaluacion $formulario,
        Docente $docente,
        User $evaluador,
        string $tipoEvaluador,
        array $respuestas,
        array $contextoSnapshot = [],
        ?DocenteContexto $docenteContexto = null,
    ): EvaluacionDocente {
        $this->ensurePeriodoActivo($periodo);
        $this->ensureFormularioDisponible($formulario, $tipoEvaluador);
        $this->ensureNoDuplicateEvaluation($periodo, $formulario, $docente, $evaluador, $docenteContexto);

        $criterios = $formulario->criterios()
            ->where('activo', true)
            ->get()
            ->keyBy('id');

        if ($criterios->isEmpty()) {
            throw ValidationException::withMessages([
                'formulario' => 'El formulario no tiene criterios activos para evaluar.',
            ]);
        }

        [$respuestasNormalizadas, $insumosCalculo] = $this->normalizarRespuestas($criterios, $respuestas, $formulario);
        $puntajeTotal = $this->puntajeCalculator->calcular($insumosCalculo);

        try {
            return DB::transaction(function () use (
                $periodo,
                $formulario,
                $docente,
                $evaluador,
                $tipoEvaluador,
                $puntajeTotal,
                $contextoSnapshot,
                $respuestasNormalizadas,
                $docenteContexto,
            ): EvaluacionDocente {
                /** @var EvaluacionDocente $evaluacion */
                $evaluacion = EvaluacionDocente::query()->create([
                    'periodo_evaluacion_id' => $periodo->id,
                    'formulario_evaluacion_id' => $formulario->id,
                    'docente_contexto_id' => $docenteContexto?->id,
                    'docente_id' => $docente->id,
                    'evaluador_user_id' => $evaluador->id,
                    'tipo_evaluador' => $tipoEvaluador,
                    'puntaje_total' => $puntajeTotal,
                    'estado' => EvaluacionDocente::ESTADO_ENVIADA,
                    'fecha_envio' => now(),
                    'docente_nombre_snapshot' => $docente->nombre,
                    'docente_documento_snapshot' => $docente->documento,
                    'contexto_snapshot' => $contextoSnapshot,
                ]);

                $evaluacion->respuestas()->createMany($respuestasNormalizadas);

                return $evaluacion->load(['docente', 'formulario', 'periodo', 'respuestas']);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw ValidationException::withMessages([
                    'evaluacion' => 'Ya existe una evaluación registrada para este docente en el periodo y formulario seleccionados.',
                ]);
            }

            throw $exception;
        }
    }

    protected function ensurePeriodoActivo(PeriodoEvaluacion $periodo): void
    {
        if (! $periodo->activo) {
            throw ValidationException::withMessages([
                'periodo' => 'El periodo seleccionado no está habilitado para recibir evaluaciones.',
            ]);
        }

        $now = now()->startOfDay();
        $inicio = $periodo->fecha_inicio?->startOfDay();
        $fin = $periodo->fecha_fin?->endOfDay();

        if ($inicio && $now->lt($inicio)) {
            throw ValidationException::withMessages([
                'periodo' => 'El periodo de evaluación aún no ha comenzado.',
            ]);
        }

        if ($fin && $now->gt($fin)) {
            throw ValidationException::withMessages([
                'periodo' => 'El periodo de evaluación ya ha finalizado.',
            ]);
        }
    }

    protected function ensureFormularioDisponible(FormularioEvaluacion $formulario, string $tipoEvaluador): void
    {
        if (! $formulario->activo) {
            throw ValidationException::withMessages([
                'formulario' => 'El formulario seleccionado no está activo.',
            ]);
        }

        if ($formulario->tipo_evaluador !== $tipoEvaluador) {
            throw ValidationException::withMessages([
                'tipo_evaluador' => 'El tipo de evaluador no coincide con el formulario seleccionado.',
            ]);
        }
    }

    protected function ensureNoDuplicateEvaluation(
        PeriodoEvaluacion $periodo,
        FormularioEvaluacion $formulario,
        Docente $docente,
        User $evaluador,
        ?DocenteContexto $docenteContexto = null,
    ): void {
        $query = EvaluacionDocente::query()
            ->where('periodo_evaluacion_id', $periodo->id)
            ->where('formulario_evaluacion_id', $formulario->id)
            ->where('docente_id', $docente->id)
            ->where('evaluador_user_id', $evaluador->id);

        if ($docenteContexto !== null) {
            $query->where('docente_contexto_id', $docenteContexto->id);
        }

        if ($query->exists()) {
            $message = $docenteContexto !== null
                ? 'Ya existe una evaluación registrada para este docente en esta materia.'
                : 'Ya existe una evaluación registrada para este docente en el periodo y formulario seleccionados.';

            throw ValidationException::withMessages([
                'evaluacion' => $message,
            ]);
        }
    }

    /**
     * @param  Collection<int, FormularioCriterio>  $criterios
     * @param  array<int, array{formulario_criterio_id: int, valor_numerico?: int|float|string|null, valor_texto?: string|null, observacion?: string|null}>  $respuestas
     * @return array{0: array<int, array{formulario_criterio_id: int, valor_numerico: float|null, valor_texto: string|null, observacion: string|null}>, 1: array<int, array{tipo_respuesta: string, peso: int|float|string, valor_numerico: float|null}>}
     */
    protected function normalizarRespuestas(Collection $criterios, array $respuestas, FormularioEvaluacion $formulario): array
    {
        $idsRepetidos = collect($respuestas)
            ->pluck('formulario_criterio_id')
            ->duplicates()
            ->filter()
            ->values();

        if ($idsRepetidos->isNotEmpty()) {
            throw ValidationException::withMessages([
                'respuestas' => 'No se puede responder el mismo criterio más de una vez.',
            ]);
        }

        $respuestasPorCriterio = collect($respuestas)->keyBy('formulario_criterio_id');
        $criteriosFueraDeFormulario = $respuestasPorCriterio->keys()->diff($criterios->keys());

        if ($criteriosFueraDeFormulario->isNotEmpty()) {
            throw ValidationException::withMessages([
                'respuestas' => 'Se enviaron criterios que no pertenecen al formulario seleccionado.',
            ]);
        }

        $respuestasNormalizadas = [];
        $insumosCalculo = [];

        foreach ($criterios as $criterio) {
            $respuesta = $respuestasPorCriterio->get($criterio->id);

            if ($respuesta === null) {
                if ($criterio->obligatoria) {
                    throw ValidationException::withMessages([
                        'respuestas.'.$criterio->id => 'Falta responder un criterio obligatorio del formulario.',
                    ]);
                }

                continue;
            }

            $valorTexto = $this->normalizeString($respuesta['valor_texto'] ?? null);
            $observacion = $this->normalizeString($respuesta['observacion'] ?? null);
            $valorNumerico = $this->normalizeNumericValue($respuesta['valor_numerico'] ?? null);

            if ($this->requiresNumericValue($criterio) && $valorNumerico === null) {
                throw ValidationException::withMessages([
                    'respuestas.'.$criterio->id => 'Este criterio requiere una respuesta numérica.',
                ]);
            }

            if ($criterio->tipo_respuesta === FormularioCriterio::TIPO_TEXTO && $criterio->obligatoria && $valorTexto === null) {
                throw ValidationException::withMessages([
                    'respuestas.'.$criterio->id => 'Este criterio requiere una respuesta textual.',
                ]);
            }

            if ($valorNumerico !== null && ($valorNumerico < $formulario->escala_min || $valorNumerico > $formulario->escala_max)) {
                throw ValidationException::withMessages([
                    'respuestas.'.$criterio->id => sprintf(
                        'La respuesta numérica debe estar entre %d y %d.',
                        $formulario->escala_min,
                        $formulario->escala_max,
                    ),
                ]);
            }

            if ($criterio->tipo_respuesta === FormularioCriterio::TIPO_TEXTO) {
                $valorNumerico = null;
            }

            if ($valorNumerico === null && $valorTexto === null && $observacion === null) {
                if ($criterio->obligatoria) {
                    throw ValidationException::withMessages([
                        'respuestas.'.$criterio->id => 'Este criterio obligatorio no puede enviarse vacío.',
                    ]);
                }

                continue;
            }

            $respuestasNormalizadas[] = [
                'formulario_criterio_id' => $criterio->id,
                'valor_numerico' => $valorNumerico,
                'valor_texto' => $valorTexto,
                'observacion' => $observacion,
            ];

            $insumosCalculo[] = [
                'tipo_respuesta' => $criterio->tipo_respuesta,
                'peso' => $criterio->peso,
                'valor_numerico' => $valorNumerico,
            ];
        }

        return [$respuestasNormalizadas, $insumosCalculo];
    }

    protected function requiresNumericValue(FormularioCriterio $criterio): bool
    {
        return in_array($criterio->tipo_respuesta, [FormularioCriterio::TIPO_ESCALA, FormularioCriterio::TIPO_MIXTO], true);
    }

    protected function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalizedValue = trim($value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }

    protected function normalizeNumericValue(int|float|string|null $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'respuestas' => 'Las respuestas numéricas deben ser valores válidos.',
            ]);
        }

        return (float) $value;
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array($exception->errorInfo[0] ?? null, ['23000', '23505'], true);
    }
}
