<?php

namespace App\Services;

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
            'select * from fn_consultor_verificacion_pin_web2(?, ?, ?)',
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
     * Carreras activas (habilitaciones vigentes) del alumno.
     */
    public function carreras(int $aluId): Collection
    {
        $data = Cache::remember("alumno_{$aluId}_carreras", 1800, function () use ($aluId) {
            return $this->query()
                ->table('sh_movimientos.vw_alumnos_habilitacion_21')
                ->where('alu_id', $aluId)
                /* ->where('hal_vigent', true)/* tiene que mostrar igual si no es vigente */
                ->get()
                ->map(fn ($row) => (array) $row)
                ->toArray();
        });

        return collect($data)
            ->map(function (mixed $row): stdClass {
                if (is_array($row)) {
                    return (object) $row;
                }

                if ($row instanceof stdClass) {
                    return $row;
                }

                $attributes = get_object_vars($row);

                unset($attributes['__PHP_Incomplete_Class_Name']);

                return (object) $attributes;
            });
    }

    /**
     * Extracto académico completo (calificaciones históricas).
     */
    public function extractoAcademico(int $aluId): Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_extracto_academico_01')/* vw_extracto_academico_11 */
            ->where('aci_idalu', $aluId)
            ->orderBy('act_fecha', 'desc')
            ->get();
    }

    /**
     * Extracto académico filtrado por la habilitación actual cuando la vista expone ese campo.
     */
    public function extractoPorHabilitacion(int $aluId, int $halId): Collection
    {
        return $this->filterByFirstAvailableField(
            $this->extractoAcademico($aluId),
            $halId,
            ['aci_idhal', 'act_idhal', 'hal_id'],
        )->values();
    }

    /**
     * Materias inscriptas vigentes en el periodo actual.
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
     * Deudas asociadas a una habilitación, cuando la vista expone recurso o periodo.
     */
    public function deudasPorHabilitacion(int $aluId, int $rscId, ?int $periodoId = null): Collection
    {
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
        $asistencias = $this->filterByFirstAvailableField(
            $this->asistencia($aluId),
            $rscId,
            ['aal_idrsc', 'aai_idrsc', 'rsc_id', 'hal_idrsc'],
        );

        if ($periodoId !== null) {
            $asistencias = $this->filterByFirstAvailableField(
                $asistencias,
                $periodoId,
                ['aal_idple', 'aai_idple', 'ple_id', 'hal_idple'],
            );
        }

        return $asistencias->values();
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
}
