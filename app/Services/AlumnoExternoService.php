<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use stdClass;

class AlumnoExternoService
{
    protected function query()
    {
        return DB::connection('pgsql_externa');
    }

    /**
     * Resolver el registro de alumno a partir del documento (cédula).
     * Se cachea 30 minutos porque no cambia frecuentemente.
     */
    public function resolverAlumno(string $documento): ?stdClass
    {
        $cacheKey = "alumno_doc_{$documento}";
        $cachedAlumno = Cache::get($cacheKey);

        if ($cachedAlumno !== null) {
            $normalizedAlumno = $this->normalizeAlumnoPayload($cachedAlumno);

            if ($normalizedAlumno !== null) {
                if (! is_array($cachedAlumno)) {
                    Cache::put($cacheKey, (array) $normalizedAlumno, 1800);
                }

                return $normalizedAlumno;
            }

            Cache::forget($cacheKey);
        }

        $result = $this->query()
            ->table('sh_maestros.vw_alumnos_00')
            ->where('alu_perdoc', $documento)
            ->first();

        if (! $result) {
            return null;
        }

        Cache::put($cacheKey, (array) $result, 1800);

        return $result;
    }

    protected function normalizeAlumnoPayload(mixed $payload): ?stdClass
    {
        if (is_array($payload)) {
            return (object) $payload;
        }

        if (! is_object($payload)) {
            return null;
        }

        $attributes = get_object_vars($payload);

        unset($attributes['__PHP_Incomplete_Class_Name']);

        return (object) $attributes;
    }

    /**
     * Valida las credenciales históricas del consultor académico.
     */
    public function autenticarConsultor(string $documento, string $pin, string $ip): ?array
    {
        $result = $this->query()->selectOne(
            'select * from sh_movimientos.fn_consultor_verificacion_pin_web2(?, ?, ?)',
            [$documento, $pin, $ip],
        );

        if (! $result) {
            return null;
        }

        $payload = (array) $result;

        foreach ($payload as $key => $value) {
            if (Str::lower((string) $key) === 'error' && filled($value)) {
                return null;
            }
        }

        return $payload;
    }

    /**
     * Lista de alumnos apta para sincronización masiva de usuarios locales.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function alumnosParaSincronizar(?string $documento = null): LazyCollection
    {
        return $this->query()
            ->table('sh_maestros.vw_alumnos_00')
            ->select(['alu_id', 'alu_perdoc', 'per_nombre', 'per_apelli'])
            ->selectRaw('count(*) over (partition by alu_perdoc) as duplicate_count')
            ->whereNotNull('alu_perdoc')
            ->when($documento, function ($query, string $documento) {
                $query->where('alu_perdoc', $documento);
            })
            ->orderBy('alu_id')
            ->cursor()
            ->map(fn ($row) => (array) $row);
    }

    /**
     * Habilitaciones del alumno usando la vista legacy optimizada.
     */
    public function carreras(int $aluId): Collection
    {
        $data = Cache::remember("alumno_{$aluId}_carreras", 1800, function () use ($aluId) {
            return $this->query()
                ->table('sh_movimientos.vw_alumnos_habilitacion_22')
                ->select([
                    'alu_id',
                    'hal_id',
                    'rsc_id',
                    'car_id',
                    'riu_id',
                    'uac_descri',
                    'ple_id',
                    'ple_codigo',
                    'pac_descri',
                    'ciu_descri',
                    'sed_id',
                    'sed_descri',
                    'pla_caneta',
                ])
                ->selectRaw('rsc_id as hal_idrsc')
                ->selectRaw('ple_id as hal_idple')
                ->where('alu_id', $aluId)
                ->orderByRaw('ple_codigo::numeric desc')
                ->orderByDesc('hal_id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->toArray();
        });

        $carreras = collect($data)
            ->map(fn (mixed $row): ?stdClass => $this->normalizeCarreraPayload($row))
            ->filter()
            ->values();

        $ultimoPeriodo = $carreras
            ->map(fn (stdClass $carrera): ?int => $this->normalizePeriodoCodigo($carrera->ple_codigo ?? null))
            ->filter(fn (?int $periodo): bool => $periodo !== null)
            ->max();

        return $carreras
            ->map(function (stdClass $carrera) use ($ultimoPeriodo): stdClass {
                if (! isset($carrera->hal_vigent)) {
                    $periodoActual = $this->normalizePeriodoCodigo($carrera->ple_codigo ?? null);
                    $carrera->hal_vigent = $ultimoPeriodo === null
                        ? true
                        : $periodoActual !== null && $periodoActual === $ultimoPeriodo;
                }

                return $carrera;
            })
            ->values();
    }

    protected function normalizeCarreraPayload(mixed $payload): ?stdClass
    {
        if (is_array($payload)) {
            $carrera = (object) $payload;
        } elseif ($payload instanceof stdClass) {
            $carrera = $payload;
        } elseif (is_object($payload)) {
            $attributes = get_object_vars($payload);

            unset($attributes['__PHP_Incomplete_Class_Name']);

            $carrera = (object) $attributes;
        } else {
            return null;
        }

        $carrera->hal_idrsc ??= $carrera->rsc_id ?? null;
        $carrera->hal_idple ??= $carrera->ple_id ?? null;

        if (blank($carrera->ple_descri ?? null) && filled($carrera->ple_codigo ?? null)) {
            $carrera->ple_descri = "PERIODO LECTIVO {$carrera->ple_codigo}";
        }

        if (property_exists($carrera, 'hal_vigent')) {
            $halVigent = $this->normalizeBoolean($carrera->hal_vigent);

            if ($halVigent === null) {
                unset($carrera->hal_vigent);
            } else {
                $carrera->hal_vigent = $halVigent;
            }
        }

        return $carrera;
    }

    protected function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return match ($value) {
                1 => true,
                0 => false,
                default => null,
            };
        }

        if (! is_string($value)) {
            return null;
        }

        return match (Str::lower(trim($value))) {
            '1', 'true', 't', 'yes', 'y', 'on' => true,
            '0', 'false', 'f', 'no', 'n', 'off' => false,
            default => null,
        };
    }

    protected function normalizePeriodoCodigo(mixed $periodo): ?int
    {
        if ($periodo === null) {
            return null;
        }

        $normalizedPeriodo = trim((string) $periodo);

        if ($normalizedPeriodo === '' || ! is_numeric($normalizedPeriodo)) {
            return null;
        }

        return (int) $normalizedPeriodo;
    }

    /**
     * Extracto académico completo (calificaciones históricas).
     */
    public function extractoAcademico(int $aluId): Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_extracto_academico_01')/* vw_extracto_academico_11 finalmente se usa la función sh_movimientos.fn_busca_alumnos_habilitacion_extracto */
            ->where('aci_idalu', $aluId)
            ->orderBy('act_fecha', 'desc')
            ->get();
    }

    /**
     * Extracto académico filtrado por la habilitación actual cuando la vista expone ese campo.
     */
    public function extractoPorHabilitacion(int $aluId, int $halId): Collection
    {
        try {
            return $this->queryExtractoPorHabilitacion($aluId, $halId)->values();
        } catch (QueryException) {
            return $this->filterByFirstAvailableField(
                $this->extractoAcademico($aluId),
                $halId,
                ['aci_idhal', 'act_idhal', 'hal_id'],
            )->values();
        }
    }

    protected function queryExtractoPorHabilitacion(int $aluId, int $halId): Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_extracto_academico_01')
            ->where('aci_idalu', $aluId)
            ->where('aci_idhal', $halId)
            ->orderBy('act_fecha', 'desc')
            ->get();
    }

    /**
     * Extracto acqadémico agrupado por curso, usando la función legacy del sistema.
     *
     * Parámetros fijos: 0 (segunda posición) y 1 (tercera posición).
     * - cur_print:    indica que se puede imprimir el nombre del semestre/curso (cur_descri).
     * - cur_completo: indica que el alumno completó ese curso o año.
     *
     * Ejemplo SQL: SELECT * FROM sh_academico.fn_busca_alumnos_habilitacion_extracto(alu_id, 0, 1);
     */
    public function extractoImpresion(int $aluId): Collection
    {
        try {
            return $this->queryExtractoPorFuncion($aluId);
        } catch (QueryException) {
            return $this->extractoAcademico($aluId);
        }
    }

    /**
     * Extracto académico agrupado por curso filtrado por habilitación, usando la función legacy.
     *
     * Pasa el hal_id como segundo parámetro para restringir el resultado a una sola habilitación.
     *
     * Ejemplo SQL: SELECT * FROM sh_academico.fn_busca_alumnos_habilitacion_extracto(alu_id, hal_id, 1);
     */
    public function extractoImpresionPorHabilitacion(int $aluId, int $halId): Collection
    {
        $data = Cache::remember("extracto_impresion_{$aluId}_{$halId}", 900, function () use ($aluId, $halId): array {
            try {
                return $this->queryExtractoPorFuncionYHabilitacion($aluId, $halId)
                    ->map(fn ($row) => (array) $row)
                    ->all();
            } catch (QueryException) {
                return $this->extractoPorHabilitacion($aluId, $halId)
                    ->map(fn ($row) => (array) $row)
                    ->all();
            }
        });

        return collect($data)->map(fn (array $row) => (object) $row)->values();
    }

    protected function queryExtractoPorFuncion(int $aluId): Collection
    {
        return collect($this->query()->select(
            'SELECT * FROM sh_academico.fn_busca_alumnos_habilitacion_extracto(?, ?, ?)',
            [$aluId, 0, 1],
        ));
    }

    protected function queryExtractoPorFuncionYHabilitacion(int $aluId, int $halId): Collection
    {
        return collect($this->query()->select(
            'SELECT * FROM sh_academico.fn_busca_alumnos_habilitacion_extracto(?, ?, ?)',
            [$aluId, $halId, 1],
        ));
    }

    /**
     * Materias inscriptas vigentes en el periodo actual.
     * sh_movimientos.vw_alumnos_inscriptos_materias_14 - es super lenta
     */
    public function materiasInscriptas(int $aluId): Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_alumnos_inscriptos_materias_14')
            ->where('alu_id', $aluId)
            ->where('imi_vigent', true)
            ->get();
    }

    /**
     * Materias inscriptas filtradas por la habilitación o recurso vigente.
     */
    public function materiasPorHabilitacion(int $aluId, int $halId, ?int $rscId = null): Collection
    {
        if ($rscId !== null) {
            try {
                return $this->queryMateriasPorRecurso($aluId, $rscId)->values();
            } catch (QueryException) {
                // Fallback for external schemas that still expose a different resource field name.
            }
        }

        $materias = $this->materiasInscriptas($aluId);

        if ($rscId !== null) {
            $materias = $this->filterByFirstAvailableField(
                $materias,
                $rscId,
                ['inm_idrsc', 'imi_idrsc', 'hal_idrsc'],
            );
        }

        return $this->filterByFirstAvailableField(
            $materias,
            $halId,
            ['imi_idhal', 'inm_idhal', 'hal_id'],
        )->values();
    }

    protected function queryMateriasPorRecurso(int $aluId, int $rscId): Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_alumnos_inscriptos_materias_14')
            ->where('alu_id', $aluId)
            ->where('inm_idrsc', $rscId)
            ->where('imi_vigent', true)
            ->orderBy('cur_descri')
            ->get();
    }

    /**
     * Deudas y saldos pendientes de aranceles.
     */
    public function deudas(int $aluId): Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_alumnos_deudas_saldos_12')
            ->where('deu_idalu', $aluId)
            ->get();
    }

    /**
     * Historial de pagos registrado en el consultor legacy del alumno.
     */
    public function pagosAlumno(int $aluId): Collection
    {
        return collect($this->queryPagosAlumno($aluId))
            ->map(fn (mixed $row): ?stdClass => $this->normalizeAlumnoPayload($row))
            ->filter()
            ->values();
    }

    /**
     * @return array<int, mixed>
     */
    protected function queryPagosAlumno(int $aluId): array
    {
        return $this->query()->select(
            'select * from sh_movimientos.fn_consultor_alumnos_pagos(?, ?, ?, ?)',
            [$aluId, 'A', null, null],
        );
    }

    /**
     * Deudas asociadas a una habilitación, cuando la vista expone recurso o periodo.
     */
    public function deudasPorHabilitacion(int $aluId, int $rscId, ?int $periodoId = null): Collection
    {
        try {
            return $this->queryDeudasPorHabilitacion($aluId, $rscId, $periodoId)->values();
        } catch (QueryException) {
            $deudas = $this->filterByFirstAvailableField(
                $this->deudas($aluId),
                $rscId,
                ['dit_idrsc', 'deu_idrsc', 'rsc_id', 'hal_idrsc'],
            );

            if ($periodoId !== null) {
                $deudas = $this->filterByFirstAvailableField(
                    $deudas,
                    $periodoId,
                    ['dit_idple', 'deu_idple', 'ple_id', 'hal_idple'],
                );
            }

            return $deudas->values();
        }
    }

    protected function queryDeudasPorHabilitacion(int $aluId, int $rscId, ?int $periodoId = null): Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_alumnos_deudas_saldos_12')
            ->where('deu_idalu', $aluId)
            ->where('deu_idrsc', $rscId)
            ->when($periodoId !== null, function ($query) use ($periodoId) {
                $query->where('deu_idple', $periodoId);
            })
            ->orderBy('dit_vencim', 'desc')
            ->get();
    }

    /**
     * Asistencia por materia.
     */
    public function asistencia(int $aluId): Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_asistencia_alumnos_14')
            ->where('aai_idalu', $aluId)
            ->get();
    }

    /**
     * Asistencia asociada a una habilitación, cuando la vista expone recurso o periodo.
     */
    public function asistenciaPorHabilitacion(int $aluId, int $rscId, ?int $periodoId = null): Collection
    {
        try {
            $asistencias = $this->queryAsistenciaPorRecurso($aluId, $rscId);
        } catch (QueryException) {
            $asistencias = $this->filterByFirstAvailableField(
                $this->asistencia($aluId),
                $rscId,
                ['aal_idrsc', 'aai_idrsc', 'rsc_id', 'hal_idrsc'],
            );
        }

        if ($periodoId !== null) {
            $asistencias = $this->filterByFirstAvailableField(
                $asistencias,
                $periodoId,
                ['aal_idple', 'aai_idple', 'ple_id', 'hal_idple'],
            );
        }

        return $asistencias->values();
    }

    protected function queryAsistenciaPorRecurso(int $aluId, int $rscId): Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_asistencia_alumnos_14')
            ->where('aai_idalu', $aluId)
            ->where('aal_idrsc', $rscId)
            ->get();
    }

    /**
     * Evaluaciones y puntajes de parciales (requiere hal_id de la habilitación).
     */
    public function evaluaciones(int $halId): Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_evaluaciones_puntajes_item_14')
            ->where('epi_idhal', $halId)
            ->get();
    }

    /**
     * Malla curricular del alumno.
     */
    public function mallaCurricular(int $aluId): Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_malla_alumnos_00')
            ->where('hal_idalu', $aluId)
            ->get();
    }

    /**
     * Certificados de estudios emitidos.
     */
    public function certificados(int $aluId): Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_certificado_de_estudios_01')
            ->where('ces_idalu', $aluId)
            ->get();
    }

    /**
     * Avisos activos (generales o por sede).
     */
    public function avisos(?int $sedId = null): Collection
    {
        $query = $this->query()
            ->table('sh_movimientos.vw_avisos_00')
            ->where('avi_activo', true)
            ->orderBy('avi_fecha', 'desc');

        if ($sedId) {
            $query->where(function ($q) use ($sedId) {
                $q->where('avi_idsed', $sedId)->orWhereNull('avi_idsed');
            });
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @param  array<int, string>  $candidateFields
     */
    protected function filterByFirstAvailableField(Collection $rows, int|string $expectedValue, array $candidateFields): Collection
    {
        $field = collect($candidateFields)->first(function (string $candidate) use ($rows): bool {
            return $rows->contains(function (mixed $row) use ($candidate): bool {
                return $this->rowHasField($row, $candidate);
            });
        });

        if ($field === null) {
            return $rows;
        }

        return $rows->filter(function (mixed $row) use ($field, $expectedValue): bool {
            $value = $this->rowGet($row, $field);

            return $value !== null && (string) $value === (string) $expectedValue;
        });
    }

    protected function rowHasField(mixed $row, string $field): bool
    {
        if (is_array($row)) {
            return array_key_exists($field, $row);
        }

        if (! is_object($row)) {
            return false;
        }

        return property_exists($row, $field) || array_key_exists($field, get_object_vars($row));
    }

    protected function rowGet(mixed $row, string $field): mixed
    {
        if (is_array($row)) {
            return $row[$field] ?? null;
        }

        if (! is_object($row)) {
            return null;
        }

        return $row->{$field} ?? null;
    }

    /**
     * Catálogo de carreras para selects administrativos: [car_id => 'pac_descri']
     * Usa distinct car_id + pac_descri de la vista de habilitaciones.
     *
     * @return array<int, string>
     */
    public function catCarreras(): array
    {
        return Cache::remember('ext_cat_carreras', 3600, function (): array {
            return $this->query()
                ->table('sh_movimientos.vw_alumnos_habilitacion_22')
                ->select(['car_id', 'pac_descri'])
                ->distinct()
                ->orderBy('pac_descri')
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->car_id => trim($row->pac_descri)])
                ->all();
        });
    }

    /**
     * Catálogo de sedes para selects administrativos: [sed_id => 'uac_descri — sed_descri']
     *
     * @return array<int, string>
     */
    public function catSedes(): array
    {
        return Cache::remember('ext_cat_sedes', 3600, function (): array {
            return $this->query()
                ->table('sh_maestros.vw_unidadesacademicas_sedes_00')
                ->select(['sed_id', 'uac_descri', 'sed_descri'])
                ->orderBy('uac_descri')
                ->orderBy('sed_descri')
                ->get()
                ->mapWithKeys(fn ($row) => [
                    (int) $row->sed_id => trim($row->uac_descri).' — '.trim($row->sed_descri),
                ])
                ->all();
        });
    }

    /**
     * Catálogo de periodos lectivos: [ple_id => 'ple_codigo — ple_descri'], más recientes primero.
     *
     * @return array<int, string>
     */
    public function catPeriodos(): array
    {
        return Cache::remember('ext_cat_periodos', 3600, function (): array {
            return $this->query()
                ->table('sh_maestros.vw_periodo_lectivo_11')
                ->select(['ple_id', 'ple_codigo', 'ple_descri'])
                ->orderByDesc('ple_codigo')
                ->get()
                ->mapWithKeys(fn ($row) => [
                    (int) $row->ple_id => trim($row->ple_codigo).' — '.trim($row->ple_descri),
                ])
                ->all();
        });
    }

    /**
     * Catálogo de turnos: [tur_id => 'tur_descri']
     *
     * @return array<int, string>
     */
    public function catTurnos(): array
    {
        return Cache::remember('ext_cat_turnos', 3600, function (): array {
            return $this->query()
                ->table('sh_maestros.vw_turnos_00')
                ->select(['tur_id', 'tur_descri'])
                ->orderBy('tur_codigo')
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->tur_id => trim($row->tur_descri)])
                ->all();
        });
    }

    /**
     * Catálogo de secciones: [sec_id => 'sec_descri']
     *
     * @return array<int, string>
     */
    public function catSecciones(): array
    {
        return Cache::remember('ext_cat_secciones', 3600, function (): array {
            return $this->query()
                ->table('sh_maestros.vw_secciones_00')
                ->select(['sec_id', 'sec_descri'])
                ->orderBy('sec_codigo')
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->sec_id => trim($row->sec_descri)])
                ->all();
        });
    }

    /**
     * Contextos de enseñanza completos de un docente, derivados del cruce entre sus
     * asignaciones (vw_anexo_items_profesores_questions) y las inscripciones de alumnos
     * (vw_alumnos_inscriptos_materias_14).
     *
     * El JOIN resuelve los campos que la vista de profesores no expone directamente:
     * car_id, ple_id (entero) y tur_id.
     *
     * @return Collection<int, array{car_id: int|null, sed_id: int|null, ple_id: int|null, mi2_id: int|null, tur_id: int|null, sec_id: int|null}>
     */
    public function contextosDocentePorDocumento(string $documento, ?string $pleCodigo = null): Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_anexo_items_profesores_questions as prof')
            ->selectRaw('prof.mi2_id, prof.ane_idsed as sed_id, prof.sec_id, insc.rsc_idcar as car_id, insc.inm_idple as ple_id, insc.imi_idtur as tur_id')
            ->join(
                'sh_movimientos.vw_alumnos_inscriptos_materias_14 as insc',
                function ($join) {
                    $join->on('insc.imi_idmi2', '=', 'prof.mi2_id')
                        ->on('insc.rsc_idsed', '=', 'prof.ane_idsed')
                        ->whereColumn('insc.ple_codigo', 'prof.ple_codigo');
                },
            )
            ->where('prof.rol_docume', $documento)
            ->when($pleCodigo !== null, fn ($q) => $q->where('prof.ple_codigo', $pleCodigo))
            ->distinct()
            ->get()
            ->map(fn ($row): array => [
                'car_id' => isset($row->car_id) ? (int) $row->car_id : null,
                'sed_id' => isset($row->sed_id) ? (int) $row->sed_id : null,
                'ple_id' => isset($row->ple_id) ? (int) $row->ple_id : null,
                'mi2_id' => isset($row->mi2_id) ? (int) $row->mi2_id : null,
                'tur_id' => isset($row->tur_id) ? (int) $row->tur_id : null,
                'sec_id' => isset($row->sec_id) ? (int) $row->sec_id : null,
            ]);
    }

    /**
     * Carreras available for a specific sede.
     *
     * @return array<int, string>
     */
    public function catCarrerasPorSede(int $sedId): array
    {
        return Cache::remember("ext_cat_carreras_sed_{$sedId}", 3600, function () use ($sedId): array {
            return $this->query()
                ->table('sh_movimientos.vw_alumnos_habilitacion_22')
                ->select(['car_id', 'pac_descri'])
                ->where('sed_id', $sedId)
                ->distinct()
                ->orderBy('pac_descri')
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->car_id => trim($row->pac_descri)])
                ->all();
        });
    }

    /**
     * Materias for a specific carrera + sede combination.
     *
     * @return array<int, string>
     */
    public function catMateriasPorCarreraYSede(int $carId, int $sedId): array
    {
        return Cache::remember("ext_cat_materias_car_{$carId}_sed_{$sedId}", 3600, function () use ($carId, $sedId): array {
            return $this->query()
                ->table('sh_movimientos.vw_alumnos_inscriptos_materias_14')
                ->select(['imi_idmi2', 'mat_descri'])
                ->where('rsc_idcar', $carId)
                ->where('rsc_idsed', $sedId)
                ->distinct()
                ->orderBy('mat_descri')
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->imi_idmi2 => trim($row->mat_descri)])
                ->all();
        });
    }

    /**
     * Periodos for carrera + sede (optionally filtered by materia).
     *
     * @return array<int, string>
     */
    public function catPeriodosPorCarreraYSede(int $carId, int $sedId, ?int $mi2Id = null): array
    {
        $cacheKey = "ext_cat_periodos_car_{$carId}_sed_{$sedId}_mi2_{$mi2Id}";

        return Cache::remember($cacheKey, 3600, function () use ($carId, $sedId, $mi2Id): array {
            return $this->query()
                ->table('sh_movimientos.vw_alumnos_inscriptos_materias_14')
                ->select(['inm_idple', 'ple_descri'])
                ->where('rsc_idcar', $carId)
                ->where('rsc_idsed', $sedId)
                ->when($mi2Id !== null, fn ($q) => $q->where('imi_idmi2', $mi2Id))
                ->distinct()
                ->orderByDesc('inm_idple')
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->inm_idple => trim($row->ple_descri)])
                ->all();
        });
    }

    /**
     * Turnos for carrera + sede (optionally filtered by materia and periodo).
     *
     * @return array<int, string>
     */
    public function catTurnosPorCarreraYSede(int $carId, int $sedId, ?int $mi2Id = null, ?int $pleId = null): array
    {
        $cacheKey = "ext_cat_turnos_car_{$carId}_sed_{$sedId}_mi2_{$mi2Id}_ple_{$pleId}";

        return Cache::remember($cacheKey, 3600, function () use ($carId, $sedId, $mi2Id, $pleId): array {
            return $this->query()
                ->table('sh_movimientos.vw_alumnos_inscriptos_materias_14')
                ->select(['imi_idtur', 'tur_descri'])
                ->where('rsc_idcar', $carId)
                ->where('rsc_idsed', $sedId)
                ->when($mi2Id !== null, fn ($q) => $q->where('imi_idmi2', $mi2Id))
                ->when($pleId !== null, fn ($q) => $q->where('inm_idple', $pleId))
                ->distinct()
                ->orderBy('tur_descri')
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->imi_idtur => trim($row->tur_descri)])
                ->all();
        });
    }

    /**
     * Secciones for carrera + sede (optionally filtered by materia, periodo, turno).
     *
     * @return array<int, string>
     */
    public function catSeccionesPorCarreraYSede(int $carId, int $sedId, ?int $mi2Id = null, ?int $pleId = null, ?int $turId = null): array
    {
        $cacheKey = "ext_cat_secciones_car_{$carId}_sed_{$sedId}_mi2_{$mi2Id}_ple_{$pleId}_tur_{$turId}";

        return Cache::remember($cacheKey, 3600, function () use ($carId, $sedId, $mi2Id, $pleId, $turId): array {
            return $this->query()
                ->table('sh_movimientos.vw_alumnos_inscriptos_materias_14')
                ->select(['imi_idsec', 'sec_descri'])
                ->where('rsc_idcar', $carId)
                ->where('rsc_idsed', $sedId)
                ->when($mi2Id !== null, fn ($q) => $q->where('imi_idmi2', $mi2Id))
                ->when($pleId !== null, fn ($q) => $q->where('inm_idple', $pleId))
                ->when($turId !== null, fn ($q) => $q->where('imi_idtur', $turId))
                ->distinct()
                ->orderBy('sec_descri')
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->imi_idsec => trim($row->sec_descri)])
                ->all();
        });
    }

    /**
     * Nombres de materias para un conjunto de mi2_ids: [mi2_id => 'mat_descri']
     *
     * @param  array<int>  $mi2Ids
     * @return array<int, string>
     */
    public function catMateriasPorIds(array $mi2Ids): array
    {
        if (empty($mi2Ids)) {
            return [];
        }

        return $this->query()
            ->table('sh_maestros.vw_materias_en_mallas_vigentes_01')
            ->select(['mi2_id', 'mat_descri'])
            ->whereIn('mi2_id', $mi2Ids)
            ->get()
            ->unique('mi2_id')
            ->mapWithKeys(fn ($row) => [(int) $row->mi2_id => trim($row->mat_descri)])
            ->all();
    }
}
