<?php

use App\Models\Docente;
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

    /** @var Collection<int, array{docente: Docente, contexto: \App\Models\DocenteContexto}> */
    public Collection $docentes;

    public Collection $evaluaciones;

    public ?PeriodoEvaluacion $periodoActivo = null;

    public string $selectedPeriodoId = '';

    /** @var array<int, int> docenteContextoIds evaluados en periodo activo */
    public array $evaluadosEnPeriodoActivo = [];

    /** @var array<int, string> */
    public array $catCarreras = [];

    /** @var array<int, string> */
    public array $catMaterias = [];

    /** @var array<int, string> */
    public array $catTurnos = [];

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

        // Load catalogs for display
        try {
            $service = app(AlumnoExternoService::class);
            $this->catCarreras = $service->catCarreras();
            $this->catTurnos = $service->catTurnos();
        } catch (\Throwable) {
            // Catalogs unavailable
        }
    }

    public function cargarDocentes(DocentesElegiblesResolver $resolver): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $this->docentes = $this->periodoActivo ? $resolver->paraAlumno($user) : collect();

        // Check already evaluated per context
        $this->evaluadosEnPeriodoActivo = $this->periodoActivo
            ? EvaluacionDocente::query()
                ->where('evaluador_user_id', $user->id)
                ->where('periodo_evaluacion_id', $this->periodoActivo->id)
                ->whereNotNull('docente_contexto_id')
                ->pluck('docente_contexto_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all()
            : [];

        // Resolve materia names for display
        $mi2Ids = $this->docentes
            ->pluck('contexto.mi2_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($mi2Ids)) {
            try {
                $this->catMaterias = app(AlumnoExternoService::class)->catMateriasPorIds($mi2Ids);
            } catch (\Throwable) {
                $this->catMaterias = [];
            }
        }

        $this->loadEvaluaciones();
        $this->ready = true;
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
                        <p class="text-sm text-base-content/65">Materias elegibles para evaluar</p>
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
                <h2 class="text-lg font-semibold text-base-content">Seleccioná un docente y materia para completar tu evaluación</h2>
            </div>

            @if (! $ready)
                <div class="flex items-center justify-center py-16">
                    <x-loading class="loading-dots" />
                </div>
            @elseif ($docentes->isEmpty())
                <x-mary-alert title="Todavía no hay docentes locales asociados a tu contexto académico para este periodo." icon="o-information-circle" class="alert-info" />
            @else
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    @foreach ($docentes as $item)
                        @php
                            $docente = $item['docente'];
                            $contexto = $item['contexto'];
                            $yaEvaluado = in_array((int) $contexto->id, $evaluadosEnPeriodoActivo, true);
                            $materiaNombre = $catMaterias[(int) $contexto->mi2_id] ?? "Materia #{$contexto->mi2_id}";
                            $turnoNombre = $catTurnos[(int) $contexto->tur_id] ?? '';
                            $carreraNombre = $catCarreras[(int) $contexto->car_id] ?? '';
                        @endphp

                        <article class="glass-card card">
                            <div class="card-body gap-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="space-y-1">
                                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Docente habilitado</p>
                                        <h3 class="card-title text-base text-base-content">{{ $docente->nombre }}</h3>
                                        <p class="text-sm text-base-content/65">Documento: {{ $docente->documento ?? 'Sin dato' }}</p>
                                        @if ($carreraNombre !== '')
                                            <span class="badge badge-soft badge-xs text-xs">{{ $carreraNombre }}</span>
                                        @endif
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            <span class="badge badge-outline badge-xs text-xs">
                                                {{ $materiaNombre }}
                                                @if ($turnoNombre !== '')
                                                    · {{ $turnoNombre }}
                                                @endif
                                            </span>
                                        </div>
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
                                    <a href="{{ route('alumno.evaluacion-docente.form', [$docente, $contexto]) }}" class="btn btn-primary" wire:navigate>
                                        Evaluar esta materia
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        @if (! $evaluaciones->isEmpty())
            <section class="space-y-3">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Historial</p>
                    <h2 class="text-lg font-semibold text-base-content">Evaluaciones enviadas</h2>
                </div>

                <div class="glass-card card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr class="border-b border-base-300">
                                    <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Docente</th>
                                    <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Tipo</th>
                                    <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50 text-center">Puntaje</th>
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
            </section>
        @endif
    @endif
</div>