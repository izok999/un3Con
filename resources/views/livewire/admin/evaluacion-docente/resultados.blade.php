<?php

use App\Enums\RoleName;
use App\Models\Docente;
use App\Models\EvaluacionDocente;
use App\Models\EvaluacionRespuesta;
use App\Models\FormularioCriterio;
use App\Models\FormularioEvaluacion;
use App\Models\PeriodoEvaluacion;
use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $schemaReady = true;

    public string $schemaMessage = '';

    public ?int $selectedPeriodoId = null;

    public array $resultados = [];

    /** @var array<int, string> */
    public array $catCarreras = [];

    public bool $isGeneralAdmin = false;

    /** @var array<int, int> */
    public array $allowedSedeIds = [];

    public bool $ready = false;

    /** @var array<int, int> distribución 1-5 => count */
    public array $chartDistribucion = [];

    /** @var array<string, int> carreraName => evaluación count */
    public array $chartParticipacion = [];

    /** @var array<int, array{nombre: string, puntaje: float}> */
    public array $chartTopDocentes = [];

    public function mount(): void
    {
        $this->schemaReady = $this->schemaIsReady();

        if (! $this->schemaReady) {
            $this->schemaMessage = 'Las tablas locales de evaluación docente todavía no están disponibles. Ejecutá las migraciones del módulo para consultar resultados.';

            return;
        }

        /** @var ?\App\Models\User $user */
        $user = Auth::user();
        $this->isGeneralAdmin = $user?->isGeneralAdmin() ?? false;
        $this->allowedSedeIds = $this->resolveAllowedSedeIds();

        try {
            $this->catCarreras = app(AlumnoExternoService::class)->catCarreras();
        } catch (\Throwable) {
            $this->catCarreras = [];
        }
    }

    public function inicializarComponente(): void
    {
        if (! $this->schemaReady) {
            return;
        }

        $this->selectedPeriodoId = PeriodoEvaluacion::query()->latest('id')->value('id');
        $this->loadResultados();
        $this->computarChartData();
        $this->dispatchChartData();
        $this->ready = true;
    }

    public function updatedSelectedPeriodoId(): void
    {
        if (! $this->ready) {
            return;
        }

        $this->loadResultados();
        $this->computarChartData();
        $this->dispatchChartData();
    }

    protected function dispatchChartData(): void
    {
        $this->dispatch('charts-data-updated', [
            'distribucion' => $this->chartDistribucion,
            'participacion' => $this->chartParticipacion,
            'topDocentes' => $this->chartTopDocentes,
        ]);
    }

    protected function schemaIsReady(): bool
    {
        return Schema::hasTable('evaluaciones_docentes')
            && Schema::hasTable('evaluacion_respuestas');
    }

    protected function resolveAllowedSedeIds(): array
    {
        /** @var ?\App\Models\User $user */
        $user = Auth::user();

        if (! $user || ! $user->hasRole(RoleName::AdminUnidadAcademica->value)) {
            return [];
        }

        return $user->managedSedeIds();
    }

    public function isScopedAcademicAdmin(): bool
    {
        return $this->allowedSedeIds !== [];
    }

    protected function loadResultados(): void
    {
        if (! $this->selectedPeriodoId) {
            $this->resultados = [];

            return;
        }

        $evaluaciones = EvaluacionDocente::query()
            ->with([
                'docente:id,nombre,documento',
                'formulario:id,nombre,tipo_evaluador',
                'respuestas.criterio:id,pregunta,peso,tipo_respuesta,orden',
            ])
            ->where('periodo_evaluacion_id', $this->selectedPeriodoId)
            ->where('estado', EvaluacionDocente::ESTADO_ENVIADA)
            ->whereHas('docente')
            ->when(
                $this->isScopedAcademicAdmin(),
                fn ($query) => $query->where(function ($builder): void {
                    $builder
                        ->whereNull('contexto_snapshot')
                        ->orWhereHas('docente', fn ($docBuilder) => $docBuilder
                            ->whereHas('contextos', fn ($ctxQuery) => $ctxQuery->whereIn('sed_id', $this->allowedSedeIds)))
                        ->orWhere(function ($q): void {
                            foreach ($this->allowedSedeIds as $sedeId) {
                                $q->orWhereRaw("contexto_snapshot->>'sed_id' = ?", [(string) $sedeId]);
                            }
                        });
                }),
            )
            ->get();

        $agrupado = $evaluaciones->groupBy('docente_id');

        $resultados = [];

        foreach ($agrupado as $docenteId => $evaluacionesDocente) {
            $docente = $evaluacionesDocente->first()->docente;

            $carIds = $evaluacionesDocente
                ->pluck('contexto_snapshot')
                ->filter()
                ->pluck('car_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $carrerasUnicas = array_map(
                fn (int $carId): string => $this->catCarreras[$carId] ?? "Carrera #{$carId}",
                $carIds,
            );

            $materiasUnicas = $evaluacionesDocente
                ->pluck('contexto_snapshot')
                ->filter()
                ->flatMap(fn (?array $snapshot): array => $snapshot['materias'] ?? [])
                ->unique(fn (array $m): string => ($m['mi2_id'] ?? '').'|'.($m['tur_id'] ?? ''))
                ->values()
                ->map(fn (array $m): string => $m['materia'] ?? "ID {$m['mi2_id']}")
                ->toArray();

            $porFormulario = $evaluacionesDocente->groupBy('formulario_evaluacion_id')->map(function (Collection $grupo) {
                $formulario = $grupo->first()->formulario;
                $evaluadorCount = $grupo->unique('evaluador_user_id')->count();

                $materiasForm = $grupo
                    ->pluck('contexto_snapshot')
                    ->filter()
                    ->flatMap(fn (?array $snapshot): array => $snapshot['materias'] ?? [])
                    ->unique(fn (array $m): string => ($m['mi2_id'] ?? '').'|'.($m['tur_id'] ?? ''))
                    ->values()
                    ->toArray();

                $allRespuestas = collect();
                foreach ($grupo as $eval) {
                    $allRespuestas = $allRespuestas->merge($eval->respuestas);
                }

                $criterioAvgs = $allRespuestas
                    ->filter(fn (EvaluacionRespuesta $r): bool => $r->criterio !== null && $r->valor_numerico !== null)
                    ->groupBy('formulario_criterio_id')
                    ->map(function (Collection $grupoRespuestas): array {
                        $criterio = $grupoRespuestas->first()->criterio;

                        return [
                            'criterio_id' => $criterio->id,
                            'pregunta' => $criterio->pregunta,
                            'peso' => (float) $criterio->peso,
                            'promedio' => round((float) $grupoRespuestas->avg('valor_numerico'), 2),
                        ];
                    })
                    ->values()
                    ->toArray();

                $sumaPonderada = 0.0;
                $sumaPesos = 0.0;
                foreach ($criterioAvgs as $item) {
                    $sumaPonderada += $item['promedio'] * $item['peso'];
                    $sumaPesos += $item['peso'];
                }
                $puntaje = $sumaPesos > 0 ? round($sumaPonderada / $sumaPesos, 2) : null;

                return [
                    'formulario_id' => $formulario->id,
                    'formulario_titulo' => $formulario->nombre,
                    'tipo_evaluador' => $formulario->tipo_evaluador,
                    'puntaje' => $puntaje,
                    'evaluadores' => $evaluadorCount,
                    'criterios' => $criterioAvgs,
                    'materias' => $materiasForm,
                ];
            })->values()->toArray();

            $bestScore = collect($porFormulario)
                ->pluck('puntaje')
                ->filter()
                ->max();

            $resultados[] = [
                'docente_id' => $docente->id,
                'docente_nombre' => $docente->nombre,
                'docente_documento' => $docente->documento,
                'best_score' => $bestScore,
                'formularios' => $porFormulario,
                'materias' => $materiasUnicas,
                'carreras' => $carrerasUnicas,
            ];
        }

        usort($resultados, fn (array $a, array $b): int => strcasecmp($a['docente_nombre'], $b['docente_nombre']));

        $this->resultados = $resultados;
    }

    protected function computarChartData(): void
    {
        if (! $this->selectedPeriodoId || empty($this->resultados)) {
            $this->chartDistribucion = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
            $this->chartParticipacion = [];
            $this->chartTopDocentes = [];

            return;
        }

        // 1. Distribución de calificaciones (1-5)
        $baseQuery = EvaluacionDocente::query()
            ->where('periodo_evaluacion_id', $this->selectedPeriodoId)
            ->where('estado', EvaluacionDocente::ESTADO_ENVIADA);

        if ($this->isScopedAcademicAdmin()) {
            $baseQuery->where(function ($builder): void {
                $builder
                    ->whereNull('contexto_snapshot')
                    ->orWhereHas('docente', fn ($docBuilder) => $docBuilder
                        ->whereHas('contextos', fn ($ctxQuery) => $ctxQuery->whereIn('sed_id', $this->allowedSedeIds)))
                    ->orWhere(function ($q): void {
                        foreach ($this->allowedSedeIds as $sedeId) {
                            $q->orWhereRaw("contexto_snapshot->>'sed_id' = ?", [(string) $sedeId]);
                        }
                    });
            });
        }

        $evalIds = $baseQuery->pluck('id');

        $distribucion = EvaluacionRespuesta::query()
            ->whereIn('evaluacion_docente_id', $evalIds)
            ->whereNotNull('valor_numerico')
            ->selectRaw('valor_numerico, COUNT(*) as count')
            ->groupBy('valor_numerico')
            ->pluck('count', 'valor_numerico')
            ->toArray();

        $this->chartDistribucion = [
            1 => (int) ($distribucion[1] ?? 0),
            2 => (int) ($distribucion[2] ?? 0),
            3 => (int) ($distribucion[3] ?? 0),
            4 => (int) ($distribucion[4] ?? 0),
            5 => (int) ($distribucion[5] ?? 0),
        ];

        // 2. Participación por carrera
        $participacionRaw = [];
        foreach ($this->resultados as $r) {
            $evalCount = 0;
            foreach ($r['formularios'] as $f) {
                $evalCount += (int) ($f['evaluadores'] ?? 0);
            }
            foreach ($r['carreras'] as $carrera) {
                $participacionRaw[$carrera] = ($participacionRaw[$carrera] ?? 0) + $evalCount;
            }
        }
        arsort($participacionRaw);
        $this->chartParticipacion = $participacionRaw;

        // 3. Top docentes
        $this->chartTopDocentes = collect($this->resultados)
            ->filter(fn (array $r): bool => $r['best_score'] !== null)
            ->map(fn (array $r): array => [
                'nombre' => $r['docente_nombre'],
                'puntaje' => round((float) $r['best_score'], 2),
            ])
            ->values()
            ->toArray();
    }

    public function with(): array
    {
        return [
            'periodos' => $this->schemaReady
                ? PeriodoEvaluacion::query()->orderByDesc('fecha_inicio')->get()
                : collect(),
            'totalDocentes' => $this->schemaReady && $this->selectedPeriodoId
                ? count($this->resultados)
                : 0,
            'totalEvaluaciones' => $this->schemaReady && $this->selectedPeriodoId
                ? collect($this->resultados)->sum(fn (array $r): int => collect($r['formularios'])->sum(fn (array $f): int => (int) ($f['evaluadores'] ?? 0)))
                : 0,
        ];
    }
}; ?>

<div
    class="space-y-6"
    x-data="{
        expandedDocenteId: null,
        toggleDocente(docenteId) {
            this.expandedDocenteId = (this.expandedDocenteId === docenteId) ? null : docenteId;
        },
        isExpanded(docenteId) {
            return this.expandedDocenteId === docenteId;
        }
    }"
    wire:init="inicializarComponente"
>
    <x-slot name="header">Resultados de Evaluación Docente</x-slot>

    <x-mary-header title="Resultados de Evaluación Docente" subtitle="Puntajes ponderados por docente, formulario y criterio" separator />

    @if (! $schemaReady)
        <x-mary-alert title="{{ $schemaMessage }}" icon="o-exclamation-triangle" class="alert-warning" />
    @elseif (! $ready)
        {{-- ===== SKELETAL LOADING ===== --}}
        <section class="grid gap-4 xl:grid-cols-[minmax(0,0.4fr)_minmax(0,0.6fr)]">
            <article class="glass-card card">
                <div class="card-body gap-3">
                    <div class="skeleton h-3 w-28 rounded-lg"></div>
                    <div class="skeleton h-12 w-full rounded-2xl"></div>
                </div>
            </article>
            <article class="glass-card card">
                <div class="card-body gap-3">
                    <div class="skeleton h-3 w-16 rounded-lg"></div>
                    <div class="flex items-end gap-6">
                        <div>
                            <div class="skeleton h-9 w-12 rounded-xl"></div>
                            <div class="skeleton h-3 w-28 rounded-lg mt-1"></div>
                        </div>
                        <div>
                            <div class="skeleton h-9 w-12 rounded-xl"></div>
                            <div class="skeleton h-3 w-32 rounded-lg mt-1"></div>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="glass-card card">
                <div class="card-body gap-3">
                    <div class="skeleton h-4 w-48 rounded-lg"></div>
                    <div class="skeleton h-52 w-full rounded-2xl"></div>
                </div>
            </article>
            <article class="glass-card card">
                <div class="card-body gap-3">
                    <div class="skeleton h-4 w-40 rounded-lg"></div>
                    <div class="skeleton h-52 w-full rounded-2xl"></div>
                </div>
            </article>
        </section>

        <section class="space-y-4">
            <article class="glass-card card">
                <div class="card-body gap-3">
                    <div class="skeleton h-4 w-44 rounded-lg"></div>
                    <div class="skeleton h-36 w-full rounded-2xl"></div>
                </div>
            </article>
            <div class="glass-card card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr class="border-b border-base-300">
                                <th class="w-8"></th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Docente</th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Documento</th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Carrera</th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Materia</th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Formularios</th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50 text-center">Mejor punt.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (range(1, 5) as $i)
                                <tr class="border-b border-base-300/60">
                                    <td class="w-8"><div class="skeleton size-4 rounded"></div></td>
                                    <td><div class="skeleton h-4 w-36 rounded-lg"></div></td>
                                    <td><div class="skeleton h-4 w-24 rounded-lg"></div></td>
                                    <td><div class="flex gap-1"><div class="skeleton h-4 w-16 rounded-full"></div></div></td>
                                    <td><div class="flex gap-1"><div class="skeleton h-4 w-20 rounded-full"></div></div></td>
                                    <td><div class="skeleton h-5 w-8 rounded-full"></div></td>
                                    <td class="text-center"><div class="skeleton h-5 w-10 rounded-full mx-auto"></div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @else
        {{-- ===== REAL CONTENT ===== --}}
        {{-- Period selector & stats --}}
        <section class="grid gap-4 xl:grid-cols-[minmax(0,0.4fr)_minmax(0,0.6fr)]">
            <article class="glass-card card">
                <div class="card-body gap-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Período de evaluación</p>
                    <select wire:model.live="selectedPeriodoId" class="select select-bordered w-full">
                        <option value="">— Seleccionar período —</option>
                        @foreach ($periodos as $periodo)
                            <option value="{{ $periodo->id }}">
                                {{ $periodo->nombre }} ({{ $periodo->fecha_inicio->format('d/m/Y') }} — {{ $periodo->fecha_fin->format('d/m/Y') }})
                                {{ $periodo->activo ? '· Activo' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </article>

            <article class="glass-card card">
                <div class="card-body gap-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Resumen</p>
                    <div class="flex items-end gap-6">
                        <div>
                            <p class="text-3xl font-semibold text-primary">{{ $totalDocentes }}</p>
                            <p class="text-sm text-base-content/65">Docentes evaluados</p>
                        </div>
                        <div>
                            <p class="text-3xl font-semibold text-secondary">{{ $totalEvaluaciones }}</p>
                            <p class="text-sm text-base-content/65">Evaluaciones recibidas</p>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        {{-- CHARTS ROW --}}
        <section class="grid gap-4 xl:grid-cols-2">
            {{-- Distribución de calificaciones --}}
            <article class="glass-card card">
                <div class="card-body gap-3">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Distribución</p>
                        <h3 class="font-semibold text-base-content">Calificaciones (1–5)</h3>
                        <p class="text-xs text-base-content/50">Detecta polarización, inflación de notas o falta de discriminación.</p>
                    </div>
                    <div class="relative h-52">
                        <canvas
                            id="chart-distribucion"
                            x-data="{}"
                            x-init="window.uneCharts?.renderDistribucionChart('chart-distribucion', {{ Js::from($chartDistribucion) }})"
                        ></canvas>
                    </div>
                </div>
            </article>

            {{-- Participación por carrera --}}
            <article class="glass-card card">
                <div class="card-body gap-3">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Participación</p>
                        <h3 class="font-semibold text-base-content">
                            Por carrera{{ !empty($allowedSedeIds) ? ' (tu unidad)' : '' }}
                        </h3>
                        <p class="text-xs text-base-content/50">Evaluaciones recibidas por carrera en este período.</p>
                    </div>
                    <div class="relative h-52">
                        <canvas
                            id="chart-participacion"
                            x-data="{}"
                            x-init="window.uneCharts?.renderParticipacionChart('chart-participacion', {{ Js::from($chartParticipacion) }})"
                        ></canvas>
                    </div>
                </div>
            </article>
        </section>

        {{-- Top docentes chart --}}
        @if (!empty($chartTopDocentes))
            <section class="space-y-4">
                <article class="glass-card card">
                    <div class="card-body gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Ranking</p>
                            <h3 class="font-semibold text-base-content">Top 5 docentes</h3>
                            <p class="text-xs text-base-content/50">Mejor puntaje ponderado por docente. Verde ≥4, naranja ≥3, rojo <3.</p>
                        </div>
                        <div class="relative h-44">
                            <canvas
                                id="chart-top-docentes"
                                x-data="{}"
                                x-init="window.uneCharts?.renderTopDocentesChart('chart-top-docentes', {{ Js::from($chartTopDocentes) }})"
                            ></canvas>
                        </div>
                    </div>
                </article>
            </section>
        @endif

        {{-- Results table --}}
        <section class="space-y-4">
            <div class="space-y-1">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Puntajes</p>
                <h2 class="text-lg font-semibold text-base-content">
                    {{ $selectedPeriodoId ? 'Resultados del período seleccionado' : 'Seleccioná un período para ver los resultados' }}
                </h2>
            </div>

            @if (! $selectedPeriodoId)
                <x-mary-alert title="Seleccioná un período de evaluación para consultar los resultados." icon="o-information-circle" class="alert-info" />
            @elseif (empty($resultados))
                <x-mary-alert title="Todavía no hay evaluaciones enviadas en este período." icon="o-information-circle" class="alert-info" />
            @else
                <div class="glass-card card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr class="border-b border-base-300">
                                    <th></th>
                                    <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Docente</th>
                                    <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Documento</th>
                                    <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Carrera</th>
                                    <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Materia</th>
                                    <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Formularios</th>
                                    <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50 text-center">Mejor punt.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($resultados as $item)
                                    @php
                                        $formCount = count($item['formularios']);
                                        $bestScore = $item['best_score'] ?? null;
                                    @endphp
                                    <tr
                                        wire:key="resultado-docente-{{ $item['docente_id'] }}"
                                        @class([
                                            'border-b border-base-300/60 transition cursor-pointer',
                                            'hover:bg-base-200/40',
                                        ])
                                        x-on:click="toggleDocente({{ $item['docente_id'] }})"
                                    >
                                        <td class="w-8">
                                            <button
                                                type="button"
                                                x-on:click="toggleDocente({{ $item['docente_id'] }})"
                                                class="btn btn-ghost btn-xs btn-square"
                                                title="Ver detalle por formulario y criterio"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    :class="'size-4 transition-transform duration-200 ' + (isExpanded({{ $item['docente_id'] }}) ? 'rotate-90' : '')"
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </button>
                                        </td>
                                        <td>
                                            <span class="font-semibold text-sm text-base-content">{{ $item['docente_nombre'] }}</span>
                                        </td>
                                        <td class="text-sm text-base-content/70">
                                            {{ $item['docente_documento'] ?? '—' }}
                                        </td>
                                        <td>
                                            <div class="flex flex-wrap gap-1">
                                                @forelse ($item['carreras'] as $carrera)
                                                    <span class="badge badge-soft badge-xs text-xs">{{ $carrera }}</span>
                                                @empty
                                                    <span class="text-sm text-base-content/40 italic">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-wrap gap-1">
                                                @forelse ($item['materias'] as $materia)
                                                    <span class="badge badge-soft badge-xs text-xs">{{ $materia }}</span>
                                                @empty
                                                    <span class="text-sm text-base-content/40 italic">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td>
                                            <span @class([
                                                'badge badge-sm',
                                                'badge-primary' => $formCount > 0,
                                                'badge-ghost' => $formCount === 0,
                                            ])>
                                                {{ $formCount }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span @class([
                                                'badge badge-sm font-mono',
                                                'badge-success' => $bestScore !== null && $bestScore >= 4,
                                                'badge-warning' => $bestScore !== null && $bestScore >= 3 && $bestScore < 4,
                                                'badge-error' => $bestScore !== null && $bestScore < 3,
                                                'badge-ghost' => $bestScore === null,
                                            ])>
                                                {{ $bestScore ?? '—' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr
                                        x-show="isExpanded({{ $item['docente_id'] }})"
                                        x-cloak
                                        wire:key="resultado-detalle-{{ $item['docente_id'] }}"
                                    >
                                        <td colspan="7" class="bg-base-200/30 p-4">
                                            <div class="space-y-4">
                                                <p class="text-sm font-semibold text-base-content/70">
                                                    Detalle por formulario — {{ $item['docente_nombre'] }}
                                                </p>
                                                @foreach ($item['formularios'] as $form)
                                                    <div class="rounded-2xl border border-base-300 bg-base-100 p-4 space-y-3">
                                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                                            <div>
                                                                <p class="font-semibold text-base-content">{{ $form['formulario_titulo'] }}</p>
                                                                <p class="text-xs text-base-content/50">
                                                                    Tipo: {{ $form['tipo_evaluador'] }} ·
                                                                    {{ $form['evaluadores'] }} evaluador{{ $form['evaluadores'] !== 1 ? 'es' : '' }}
                                                                </p>
                                                                @if (! empty($form['materias']))
                                                                    <div class="flex flex-wrap gap-1 mt-1">
                                                                        @foreach ($form['materias'] as $m)
                                                                            <span class="badge badge-soft badge-xs text-xs">{{ $m['materia'] }}</span>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            </div>
                                                            <span @class([
                                                                'badge badge-lg font-mono font-semibold',
                                                                'badge-success' => $form['puntaje'] !== null && $form['puntaje'] >= 4,
                                                                'badge-warning' => $form['puntaje'] !== null && $form['puntaje'] >= 3 && $form['puntaje'] < 4,
                                                                'badge-error' => $form['puntaje'] !== null && $form['puntaje'] < 3,
                                                                'badge-ghost' => $form['puntaje'] === null,
                                                            ])>
                                                                {{ $form['puntaje'] ?? '—' }}
                                                            </span>
                                                        </div>
                                                        @if (! empty($form['criterios']))
                                                            <div class="overflow-x-auto">
                                                                <table class="table table-xs">
                                                                    <thead>
                                                                        <tr class="border-b border-base-300/60">
                                                                            <th class="text-xs font-semibold text-base-content/50">Criterio</th>
                                                                            <th class="text-xs font-semibold text-base-content/50 text-center">Peso</th>
                                                                            <th class="text-xs font-semibold text-base-content/50 text-center">Promedio</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach ($form['criterios'] as $criterio)
                                                                            <tr class="border-b border-base-300/40">
                                                                                <td class="text-sm text-base-content">{{ $criterio['pregunta'] }}</td>
                                                                                <td class="text-sm text-base-content/70 text-center">{{ $criterio['peso'] }}%</td>
                                                                                <td class="text-sm text-base-content/70 text-center font-mono">{{ $criterio['promedio'] }}</td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        @else
                                                            <p class="text-sm text-base-content/40 italic">Sin criterios con valores numéricos.</p>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    @endif
</div>