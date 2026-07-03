<?php

namespace App\Services\EvaluacionDocente;

use App\Models\Docente;
use App\Models\DocenteContexto;
use App\Models\PeriodoEvaluacion;
use App\Models\User;
use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DocentesElegiblesResolver
{
    public function __construct(public AlumnoExternoService $alumnoExternoService) {}

    /**
     * Retorna pares (docente, contexto) para cada materia elegible.
     * Un docente puede aparecer múltiples veces si tiene varias materias.
     *
     * El match se hace fila por fila contra las inscripciones del alumno
     * (tupla completa materia + turno + sección + carrera + sede + periodo),
     * nunca contra conjuntos aplanados, para no mezclar atributos de
     * inscripciones distintas.
     *
     * @return Collection<int, array{docente: Docente, contexto: DocenteContexto}>
     */
    public function paraAlumno(User $user, ?PeriodoEvaluacion $periodo = null): Collection
    {
        $inscripciones = $this->inscripcionesAlumno($user, $periodo?->ple_codigo);

        if ($inscripciones === null || $inscripciones->isEmpty()) {
            return collect();
        }

        return Docente::query()
            ->where('activo', true)
            ->with(['contextos' => function ($query) use ($periodo) {
                $query->where('activo', true)
                    ->when($periodo, fn ($q) => $q->where(function ($sub) use ($periodo) {
                        $sub->whereNull('periodo_evaluacion_id')
                            ->orWhere('periodo_evaluacion_id', $periodo->id);
                    }));
            }])
            ->get()
            ->flatMap(function (Docente $docente) use ($inscripciones): array {
                return $docente->contextos
                    ->filter(fn (DocenteContexto $ctx) => $this->matchesAlgunaInscripcion($ctx, $inscripciones))
                    ->map(fn (DocenteContexto $ctx) => ['docente' => $docente, 'contexto' => $ctx])
                    ->values()
                    ->all();
            })
            ->sortBy('docente.nombre')
            ->values();
    }

    /**
     * Verifica que un contexto concreto sea elegible para el alumno, sin
     * cargar el resto de los docentes. Usado como gate al abrir el formulario.
     */
    public function esElegibleParaAlumno(User $user, DocenteContexto $contexto, ?PeriodoEvaluacion $periodo = null): bool
    {
        if (! $contexto->activo) {
            return false;
        }

        if ($periodo && $contexto->periodo_evaluacion_id !== null && (int) $contexto->periodo_evaluacion_id !== (int) $periodo->id) {
            return false;
        }

        $inscripciones = $this->inscripcionesAlumno($user, $periodo?->ple_codigo);

        return $inscripciones !== null
            && $this->matchesAlgunaInscripcion($contexto, $inscripciones);
    }

    /**
     * Inscripciones del alumno como tuplas completas. Si la campaña declara
     * un ple_codigo, se consultan las inscripciones de ESE periodo lectivo;
     * si no, se usan las vigentes (comportamiento histórico). Se cachea
     * brevemente porque la vista externa de inscripciones es muy lenta.
     *
     * @return Collection<int, array{car_id: int|null, sed_id: int|null, ple_id: int|null, mi2_id: int|null, tur_id: int|null, sec_id: int|null}>|null
     */
    protected function inscripcionesAlumno(User $user, ?string $pleCodigo = null): ?Collection
    {
        if (blank($user->documento)) {
            return null;
        }

        $alumno = $this->alumnoExternoService->resolverAlumno($user->documento);

        if (! $alumno || ! isset($alumno->alu_id)) {
            return null;
        }

        $alumnoId = (int) $alumno->alu_id;

        $tuplas = Cache::remember(
            'evaluacion_docente_inscripciones_'.$alumnoId.'_'.($pleCodigo ?? 'vigentes'),
            600,
            fn (): array => $this->alumnoExternoService
                ->materiasInscriptas($alumnoId, $pleCodigo)
                ->map(fn (object $row): array => [
                    'car_id' => $this->toNullableInt($row->rsc_idcar ?? null),
                    'sed_id' => $this->toNullableInt($row->rsc_idsed ?? null),
                    'ple_id' => $this->toNullableInt($row->inm_idple ?? null),
                    'mi2_id' => $this->toNullableInt($row->imi_idmi2 ?? null),
                    'tur_id' => $this->toNullableInt($row->imi_idtur ?? null),
                    'sec_id' => $this->toNullableInt($row->imi_idsec ?? null),
                ])
                ->filter(fn (array $inscripcion): bool => $inscripcion['mi2_id'] !== null)
                ->values()
                ->all(),
        );

        return collect($tuplas);
    }

    /**
     * @param  Collection<int, array{car_id: int|null, sed_id: int|null, ple_id: int|null, mi2_id: int|null, tur_id: int|null, sec_id: int|null}>  $inscripciones
     */
    protected function matchesAlgunaInscripcion(DocenteContexto $contexto, Collection $inscripciones): bool
    {
        return $inscripciones->contains(
            fn (array $inscripcion): bool => $this->matchesInscripcion($contexto, $inscripcion),
        );
    }

    /**
     * Compara el contexto del docente contra UNA inscripción concreta.
     * La materia (mi2_id) es estricta: el contexto debe declararla y coincidir.
     * El resto de los campos admite NULL como comodín.
     *
     * @param  array{car_id: int|null, sed_id: int|null, ple_id: int|null, mi2_id: int|null, tur_id: int|null, sec_id: int|null}  $inscripcion
     */
    protected function matchesInscripcion(DocenteContexto $contexto, array $inscripcion): bool
    {
        if ($contexto->mi2_id === null || (int) $contexto->mi2_id !== $inscripcion['mi2_id']) {
            return false;
        }

        return $this->campoCoincide($contexto->car_id, $inscripcion['car_id'])
            && $this->campoCoincide($contexto->sed_id, $inscripcion['sed_id'])
            && $this->campoCoincide($contexto->ple_id, $inscripcion['ple_id'])
            && $this->campoCoincide($contexto->tur_id, $inscripcion['tur_id'])
            && $this->campoCoincide($contexto->sec_id, $inscripcion['sec_id']);
    }

    /**
     * NULL en el contexto del docente actúa como comodín. NULL en la
     * inscripción (campo que la vista externa no expone) tampoco descarta
     * el match, para no ocultar materias por datos incompletos del legacy.
     */
    protected function campoCoincide(int|string|null $contextValue, ?int $inscripcionValue): bool
    {
        if ($contextValue === null || $inscripcionValue === null) {
            return true;
        }

        return (int) $contextValue === $inscripcionValue;
    }

    protected function toNullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
