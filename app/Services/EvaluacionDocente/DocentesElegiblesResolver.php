<?php

namespace App\Services\EvaluacionDocente;

use App\Models\Docente;
use App\Models\DocenteContexto;
use App\Models\User;
use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;

class DocentesElegiblesResolver
{
    public function __construct(public AlumnoExternoService $alumnoExternoService) {}

    /**
     * @return Collection<int, Docente>
     */
    public function paraAlumno(User $user): Collection
    {
        $contextoAlumno = $this->buildStudentContext($user);

        if ($contextoAlumno === null) {
            return collect();
        }

        return Docente::query()
            ->where('activo', true)
            ->with(['contextos' => fn ($query) => $query->where('activo', true)])
            ->get()
            ->filter(fn (Docente $docente): bool => $this->findMatchingContext($docente, $contextoAlumno) !== null)
            ->sortBy('nombre')
            ->values();
    }

    /**
     * @return array<string, int>|null
     */
    public function contextoParaAlumno(User $user, Docente $docente): ?array
    {
        $contextoAlumno = $this->buildStudentContext($user);

        if ($contextoAlumno === null) {
            return null;
        }

        $matchingContext = $this->findMatchingContext(
            $docente->loadMissing(['contextos' => fn ($query) => $query->where('activo', true)]),
            $contextoAlumno,
        );

        if (! $matchingContext) {
            return null;
        }

        return array_filter([
            'car_id' => $matchingContext->car_id,
            'sed_id' => $matchingContext->sed_id,
            'ple_id' => $matchingContext->ple_id,
            'mi2_id' => $matchingContext->mi2_id,
            'tur_id' => $matchingContext->tur_id,
            'sec_id' => $matchingContext->sec_id,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array{car_ids: array<int, int>, sed_ids: array<int, int>, ple_ids: array<int, int>, mi2_ids: array<int, int>, tur_ids: array<int, int>, sec_ids: array<int, int>}|null
     */
    protected function buildStudentContext(User $user): ?array
    {
        if (blank($user->documento)) {
            return null;
        }

        $alumno = $this->alumnoExternoService->resolverAlumno($user->documento);

        if (! $alumno || ! isset($alumno->alu_id)) {
            return null;
        }

        $alumnoId = (int) $alumno->alu_id;
        $carreras = $this->alumnoExternoService->carreras($alumnoId);
        $materias = $this->alumnoExternoService->materiasInscriptas($alumnoId);

        return [
            'car_ids' => $this->collectIntegerValues($carreras, ['car_id'])->merge($this->collectIntegerValues($materias, ['rsc_idcar']))->unique()->values()->all(),
            'sed_ids' => $this->collectIntegerValues($carreras, ['sed_id'])->merge($this->collectIntegerValues($materias, ['rsc_idsed']))->unique()->values()->all(),
            'ple_ids' => $this->collectIntegerValues($carreras, ['ple_id'])->merge($this->collectIntegerValues($materias, ['inm_idple']))->unique()->values()->all(),
            'mi2_ids' => $this->collectIntegerValues($materias, ['imi_idmi2'])->unique()->values()->all(),
            'tur_ids' => $this->collectIntegerValues($materias, ['imi_idtur'])->unique()->values()->all(),
            'sec_ids' => $this->collectIntegerValues($materias, ['imi_idsec'])->unique()->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int, string>  $fields
     * @return Collection<int, int>
     */
    protected function collectIntegerValues(Collection $rows, array $fields): Collection
    {
        return $rows
            ->flatMap(function (object $row) use ($fields): array {
                $values = [];

                foreach ($fields as $field) {
                    if (isset($row->{$field}) && is_numeric($row->{$field})) {
                        $values[] = (int) $row->{$field};
                    }
                }

                return $values;
            })
            ->filter(fn (mixed $value): bool => is_int($value));
    }

    /**
     * @param  array{car_ids: array<int, int>, sed_ids: array<int, int>, ple_ids: array<int, int>, mi2_ids: array<int, int>, tur_ids: array<int, int>, sec_ids: array<int, int>}  $contextoAlumno
     */
    protected function findMatchingContext(Docente $docente, array $contextoAlumno): ?DocenteContexto
    {
        /** @var Collection<int, DocenteContexto> $contextos */
        $contextos = $docente->contextos;

        return $contextos->first(function (DocenteContexto $contexto) use ($contextoAlumno): bool {
            return $this->contextValueMatches($contexto->car_id, $contextoAlumno['car_ids'])
                && $this->contextValueMatches($contexto->sed_id, $contextoAlumno['sed_ids'])
                && $this->contextValueMatches($contexto->ple_id, $contextoAlumno['ple_ids'])
                && $this->contextValueMatches($contexto->mi2_id, $contextoAlumno['mi2_ids'])
                && $this->contextValueMatches($contexto->tur_id, $contextoAlumno['tur_ids'])
                && $this->contextValueMatches($contexto->sec_id, $contextoAlumno['sec_ids']);
        });
    }

    /**
     * @param  array<int, int>  $studentValues
     */
    protected function contextValueMatches(?int $contextValue, array $studentValues): bool
    {
        return $contextValue === null || in_array($contextValue, $studentValues, true);
    }
}
