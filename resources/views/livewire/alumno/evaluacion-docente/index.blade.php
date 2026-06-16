<?php

use App\Models\EvaluacionDocente;
use App\Models\PeriodoEvaluacion;
use App\Models\User;
use App\Services\AlumnoExternoService;
use App\Services\EvaluacionDocente\DocentesElegiblesResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Collection $periodos;

    public Collection $docentes;

    public Collection $evaluaciones;

    public ?PeriodoEvaluacion $periodoActivo = null;

    public string $selectedPeriodoId = '';

    public array $evaluadosEnPeriodoActivo = [];

    /** @var array<int, array<int, array{mi2_id: int|string, materia: string, tur_id: int|string, turno: string}>> */
    public array $materiasPorDocente = [];

    /** @var array<int, string> */
    public array $carrerasPorDocente = [];

    public string $error = '';

    public bool $ready = false;

    public function boot(): void
    {
        $this->periodos = collect();
        $this->docentes = collect();
        $this->evaluaciones = collect();
    }

    protected function schemaIsReady(): bool
    {
        return Schema::hasTable('periodos_evaluacion')
            && Schema::hasTable('docentes')
            && Schema::hasTable('docente_contextos')
            && Schema::hasTable('evaluaciones_docentes');
    }

    public function mount(): void
    {
        if (! $this->schemaIsReady()) {
            $this->error = 'El módulo de evaluación docente todavía no está disponible en este entorno.';

            return;
        }

        $this->periodos = PeriodoEvaluacion::query()
            ->orderByDesc('fecha_inicio')
            ->get();

        $this->periodoActivo = PeriodoEvaluacion::query()
            ->where('activo', true)
            ->orderByDesc('fecha_inicio')
            ->first();

        $this->selectedPeriodoId = (string) ($this->periodoActivo?->id ?? $this->periodos->first()?->id ?? '');

        if (! $this->periodoActivo) {
            $this->error = 'No hay un periodo de evaluación activo en este momento.';
        }
    }

    public function cargarDocentes(DocentesElegiblesResolver $resolver): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $this->docentes = $this->periodoActivo ? $resolver->paraAlumno($user) : collect();

        $this->resolverContextosPorDocente($resolver, $user);

        $this->evaluadosEnPeriodoActivo = $this->periodoActivo
            ? EvaluacionDocente::query()
                ->where('evaluador_user_id', $user->id)
                ->where('periodo_evaluacion_id', $this->periodoActivo->id)
                ->pluck('docente_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all()
            : [];

        $this->loadEvaluaciones();
        $this->ready = true;
    }

    protected function resolverContextosPorDocente(DocentesElegiblesResolver $resolver, User $user): void
    {
        try {
            $externo = app(AlumnoExternoService::class);
            $catCarreras = $externo->catCarreras();
            $catTurnos = $externo->catTurnos();
        } catch (\Throwable) {
            $catCarreras = [];
            $catTurnos = [];
        }

        // Batch lookup: collect all unique mi2_ids across all docentes
        $allMi2Ids = $this->docentes
            ->flatMap(fn ($d) => $d->contextos->filter(fn ($ctx) => $ctx->mi2_id !== null && $ctx->activo)->pluck('mi2_id'))
            ->unique()
            ->values()
            ->toArray();

        try {
            $nombresMaterias = ! empty($allMi2Ids) ? $externo->catMateriasPorIds($allMi2Ids) : [];
        } catch (\Throwable) {
            $nombresMaterias = [];
        }

        foreach ($this->docentes as $docente) {
            $contexto = $resolver->contextoParaAlumno($user, $docente);

            if ($contexto === null) {
                continue;
            }

            $vistas = [];

            $materias = [];
            foreach ($docente->contextos as $ctx) {
                if ($ctx->mi2_id === null || ! $ctx->activo) {
                    continue;
                }

                $clave = $ctx->mi2_id.'|'.($ctx->tur_id ?? 'null');
                if (isset($vistas[$clave])) {
                    continue;
                }

                $nombreMateria = $nombresMaterias[(int) $ctx->mi2_id] ?? null;
                $nombreTurno = $ctx->tur_id !== null ? ($catTurnos[(int) $ctx->tur_id] ?? null) : null;

                $materias[] = [
                    'mi2_id' => $ctx->mi2_id,
                    'materia' => $nombreMateria ?? "ID {$ctx->mi2_id}",
                    'tur_id' => $ctx->tur_id ?? '',
                    'turno' => $nombreTurno ?? '',
                ];

                $vistas[$clave] = true;
            }

            $this->materiasPorDocente[$docente->id] = $materias;

            // Carreras
            $carIds = $docente->contextos
                ->filter(fn ($ctx) => $ctx->car_id !== null && $ctx->activo)
                ->pluck('car_id')
                ->unique()
                ->values()
                ->toArray();

            $carreras = array_map(
                fn (int $carId): string => $catCarreras[$carId] ?? "Carrera #{$carId}",
                $carIds,
            );

            $this->carrerasPorDocente[$docente->id] = $carreras;
        }
    }

    public function updatedSelectedPeriodoId(): void
    {
        $this->loadEvaluaciones();
    }

    protected function loadEvaluaciones(): void
    {
        $user = Auth::user();

        if (! $user || $this->selectedPeriodoId === '') {
            $this->evaluaciones = collect();

            return;
        }

        $this->evaluaciones = EvaluacionDocente::query()
            ->where('evaluador_user_id', $user->id)
            ->where('periodo_evaluacion_id', (int) $this->selectedPeriodoId)
            ->orderByDesc('fecha_envio')
            ->get();
    }
}; ?>

<div
    class="space-y-6"
    @if (! $error)
    wire:init="cargarDocentes"
    @endif
>
    <x-slot name="header">Evaluación Docente</x-slot>

    <x-mary-header title="Evaluación Docente" subtitle="Docentes habilitados y evaluaciones registradas" icon="o-clipboard-document-check" separator />

    @if (session('status'))
        <x-mary-alert title="{{ session('status') }}" icon="o-check-circle" class="alert-success" />
    @endif

    @if ($error !== '')
        <x-mary-alert title="{{ $error }}" icon="o-exclamation-triangle" class="alert-warning" />
    @else
        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            <article class="glass-card card">
                <div class="card-body gap-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Periodo activo</p>
                    <div class="space-y-1">
                        <h2 class="card-title text-xl text-primary">{{ $periodoActivo?->nombre }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ $periodoActivo?->fecha_inicio?->format('d/m/Y') }} al {{ $periodoActivo?->fecha_fin?->format('d/m/Y') }}
                        </p>
                    </div>
                </div>
            </article>

            <section class="grid grid-cols-2 gap-4">
                <article class="glass-card card">
                    <div class="card-body gap-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Disponibles</p>
                        <p class="text-3xl font-semibold text-primary">{{ $docentes->count() }}</p>
                        <p class="text-sm text-base-content/65">Docentes elegibles para evaluar</p>
                    </div>
                </article>

                <article class="glass-card card">
                    <div class="card-body gap-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Enviadas</p>
                        <p class="text-3xl font-semibold text-secondary">{{ count($evaluadosEnPeriodoActivo) }}</p>
                        <p class="text-sm text-base-content/65">Evaluaciones cargadas en el periodo activo</p>
                    </div>
                </article>
            </section>
        </section>

        <section class="space-y-3">
            <div class="space-y-1">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Carga disponible</p>
                <h2 class="text-lg font-semibold text-base-content">Seleccioná un docente para completar tu evaluación</h2>
            </div>

            @if (! $ready)
                <div class="flex items-center justify-center py-16">
                    <x-loading class="loading-dots" />
                </div>
            @elseif ($docentes->isEmpty())
                <x-mary-alert title="Todavía no hay docentes locales asociados a tu contexto académico para este periodo." icon="o-information-circle" class="alert-info" />
            @else
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    @foreach ($docentes as $docente)
                        @php
                            $yaEvaluado = in_array($docente->id, $evaluadosEnPeriodoActivo, true);
                        @endphp

                        <article class="glass-card card">
                            <div class="card-body gap-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="space-y-1">
                                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Docente habilitado</p>
                                        <h3 class="card-title text-base text-base-content">{{ $docente->nombre }}</h3>
                                        <p class="text-sm text-base-content/65">Documento: {{ $docente->documento ?? 'Sin dato' }}</p>
                                        @php
                                            $materias = $materiasPorDocente[$docente->id] ?? [];
                                            $carreras = $carrerasPorDocente[$docente->id] ?? [];
                                        @endphp
                                        @if (! empty($carreras))
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                @foreach ($carreras as $carrera)
                                                    <span class="badge badge-soft badge-xs text-xs">{{ $carrera }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if (! empty($materias))
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                @foreach ($materias as $m)
                                                    <span class="badge badge-outline badge-xs text-xs">
                                                        {{ $m['materia'] }}
                                                        @if (! empty($m['turno']))
                                                            · {{ $m['turno'] }}
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    @if ($yaEvaluado)
                                        <span class="badge badge-success badge-sm">Ya evaluado</span>
                                    @else
                                        <span class="badge badge-outline badge-sm">Pendiente</span>
                                    @endif
                                </div>

                                @if ($yaEvaluado)
                                    <button type="button" class="btn btn-disabled btn-outline">
                                        Evaluación ya enviada
                                    </button>
                                @else
                                    <a href="{{ route('alumno.evaluacion-docente.form', $docente) }}" class="btn btn-primary" wire:navigate>
                                        Evaluar docente
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="space-y-3">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Historial</p>
                    <h2 class="text-lg font-semibold text-base-content">Tus evaluaciones por periodo</h2>
                </div>

                <label class="form-control w-full md:max-w-xs">
                    <span class="label-text text-sm font-medium">Periodo</span>
                    <select wire:model.live="selectedPeriodoId" class="select select-bordered w-full">
                        @foreach ($periodos as $periodo)
                            <option value="{{ $periodo->id }}">{{ $periodo->nombre }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            @if ($evaluaciones->isEmpty())
                <x-mary-alert title="No registrás evaluaciones para el periodo seleccionado." icon="o-information-circle" class="alert-info" />
            @else
                <div class="glass-card card">
                    <div class="card-body overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Docente</th>
                                    <th>Formulario</th>
                                    <th class="text-center">Puntaje</th>
                                    <th class="text-center">Estado</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($evaluaciones as $evaluacion)
                                    <tr class="hover">
                                        <td class="font-medium">{{ $evaluacion->docente_nombre_snapshot }}</td>
                                        <td>{{ $evaluacion->tipo_evaluador === 'alumno' ? 'Alumno' : 'Funcionario' }}</td>
                                        <td class="text-center">{{ $evaluacion->puntaje_total }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-success badge-sm">{{ ucfirst($evaluacion->estado) }}</span>
                                        </td>
                                        <td>{{ $evaluacion->fecha_envio?->format('d/m/Y H:i') ?? 'Sin envío' }}</td>
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