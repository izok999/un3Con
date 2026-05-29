<?php

use App\Models\FormularioCriterio;
use App\Models\FormularioEvaluacion;
use App\Models\PeriodoEvaluacion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Collection $periodos;

    public Collection $formularios;

    public ?int $editingPeriodoId = null;

    public ?int $editingFormularioId = null;

    public ?int $editingCriterioId = null;

    public ?int $selectedFormularioId = null;

    public array $periodoForm = [];

    public array $formularioForm = [];

    public array $criterioForm = [];

    public bool $schemaReady = true;

    public string $schemaMessage = '';

    public function boot(): void
    {
        $this->periodos = collect();
        $this->formularios = collect();
    }

    public function mount(): void
    {
        $this->resetPeriodoForm();
        $this->resetFormularioForm();
        $this->resetCriterioForm();

        $this->schemaReady = $this->schemaIsReady();

        if (! $this->schemaReady) {
            $this->schemaMessage = 'Las tablas de periodos y formularios de evaluación todavía no están disponibles. Ejecutá las migraciones del módulo para administrar esta configuración.';

            return;
        }

        $this->loadPeriodos();
        $this->loadFormularios();
    }

    public function getSelectedFormularioProperty(): ?FormularioEvaluacion
    {
        /** @var ?FormularioEvaluacion $formulario */
        $formulario = $this->formularios->firstWhere('id', $this->selectedFormularioId);

        return $formulario;
    }

    public function createNewPeriodo(): void
    {
        $this->resetPeriodoForm();
        $this->resetValidation();
    }

    public function editPeriodo(int $periodoId): void
    {
        $periodo = PeriodoEvaluacion::query()->findOrFail($periodoId);

        $this->editingPeriodoId = $periodo->id;
        $this->periodoForm = [
            'nombre' => $periodo->nombre,
            'fecha_inicio' => $periodo->fecha_inicio?->format('Y-m-d') ?? '',
            'fecha_fin' => $periodo->fecha_fin?->format('Y-m-d') ?? '',
            'activo' => $periodo->activo,
        ];

        $this->resetValidation();
    }

    public function savePeriodo(): void
    {
        if (! $this->schemaReady) {
            return;
        }

        $validated = $this->validate($this->periodoRules());

        $payload = [
            'nombre' => trim($validated['periodoForm']['nombre']),
            'fecha_inicio' => $validated['periodoForm']['fecha_inicio'],
            'fecha_fin' => $validated['periodoForm']['fecha_fin'],
            'activo' => (bool) ($validated['periodoForm']['activo'] ?? false),
        ];

        $periodo = DB::transaction(function () use ($payload): PeriodoEvaluacion {
            if ($payload['activo']) {
                PeriodoEvaluacion::query()
                    ->when($this->editingPeriodoId, fn ($query) => $query->whereKeyNot($this->editingPeriodoId))
                    ->where('activo', true)
                    ->update(['activo' => false]);
            }

            if ($this->editingPeriodoId) {
                $periodo = PeriodoEvaluacion::query()->findOrFail($this->editingPeriodoId);
                $periodo->fill($payload)->save();

                return $periodo;
            }

            return PeriodoEvaluacion::query()->create($payload);
        });

        $periodo->refresh();
        $this->editingPeriodoId = $periodo->id;
        $this->periodoForm = [
            'nombre' => $periodo->nombre,
            'fecha_inicio' => $periodo->fecha_inicio?->format('Y-m-d') ?? '',
            'fecha_fin' => $periodo->fecha_fin?->format('Y-m-d') ?? '',
            'activo' => $periodo->activo,
        ];
        $this->loadPeriodos();
        $this->resetValidation();

        session()->flash('status', 'Periodo guardado correctamente.');
    }

    public function createNewFormulario(): void
    {
        $this->resetFormularioForm();
        $this->resetCriterioForm();
        $this->resetValidation();
    }

    public function editFormulario(int $formularioId): void
    {
        $formulario = FormularioEvaluacion::query()->findOrFail($formularioId);

        $this->editingFormularioId = $formulario->id;
        $this->selectedFormularioId = $formulario->id;
        $this->formularioForm = [
            'nombre' => $formulario->nombre,
            'tipo_evaluador' => $formulario->tipo_evaluador,
            'descripcion' => $formulario->descripcion ?? '',
            'escala_min' => (string) $formulario->escala_min,
            'escala_max' => (string) $formulario->escala_max,
            'activo' => $formulario->activo,
        ];

        $this->resetCriterioForm();
        $this->resetValidation();
        $this->loadFormularios();
    }

    public function selectFormulario(int $formularioId): void
    {
        $this->selectedFormularioId = $formularioId;
        $this->resetCriterioForm();
        $this->resetValidation();
    }

    public function saveFormulario(): void
    {
        if (! $this->schemaReady) {
            return;
        }

        $validated = $this->validate($this->formularioRules());
        $payload = [
            'nombre' => trim($validated['formularioForm']['nombre']),
            'tipo_evaluador' => $validated['formularioForm']['tipo_evaluador'],
            'descripcion' => $this->normalizeNullableString($validated['formularioForm']['descripcion'] ?? null),
            'escala_min' => (int) $validated['formularioForm']['escala_min'],
            'escala_max' => (int) $validated['formularioForm']['escala_max'],
            'activo' => (bool) ($validated['formularioForm']['activo'] ?? false),
        ];

        $formulario = DB::transaction(function () use ($payload): FormularioEvaluacion {
            if ($payload['activo']) {
                FormularioEvaluacion::query()
                    ->where('tipo_evaluador', $payload['tipo_evaluador'])
                    ->when($this->editingFormularioId, fn ($query) => $query->whereKeyNot($this->editingFormularioId))
                    ->where('activo', true)
                    ->update(['activo' => false]);
            }

            if ($this->editingFormularioId) {
                $formulario = FormularioEvaluacion::query()->findOrFail($this->editingFormularioId);
                $formulario->fill($payload)->save();

                return $formulario;
            }

            return FormularioEvaluacion::query()->create($payload);
        });

        $formulario->refresh();
        $this->editingFormularioId = $formulario->id;
        $this->selectedFormularioId = $formulario->id;
        $this->formularioForm = [
            'nombre' => $formulario->nombre,
            'tipo_evaluador' => $formulario->tipo_evaluador,
            'descripcion' => $formulario->descripcion ?? '',
            'escala_min' => (string) $formulario->escala_min,
            'escala_max' => (string) $formulario->escala_max,
            'activo' => $formulario->activo,
        ];
        $this->loadFormularios();
        $this->resetValidation();

        session()->flash('status', 'Formulario guardado correctamente.');
    }

    public function createNewCriterio(): void
    {
        $this->resetCriterioForm();
        $this->resetValidation();
    }

    public function editCriterio(int $criterioId): void
    {
        $criterio = FormularioCriterio::query()->findOrFail($criterioId);

        $this->editingCriterioId = $criterio->id;
        $this->selectedFormularioId = $criterio->formulario_evaluacion_id;
        $this->criterioForm = [
            'pregunta' => $criterio->pregunta,
            'descripcion' => $criterio->descripcion ?? '',
            'peso' => (string) $criterio->peso,
            'orden' => (string) $criterio->orden,
            'tipo_respuesta' => $criterio->tipo_respuesta,
            'obligatoria' => $criterio->obligatoria,
            'activo' => $criterio->activo,
        ];

        $this->resetValidation();
        $this->loadFormularios();
    }

    public function saveCriterio(): void
    {
        if (! $this->schemaReady) {
            return;
        }

        if (! $this->selectedFormularioId) {
            $this->addError('criterio', 'Seleccioná primero un formulario para administrar sus criterios.');

            return;
        }

        $validated = $this->validate($this->criterioRules());
        $payload = [
            'pregunta' => trim($validated['criterioForm']['pregunta']),
            'descripcion' => $this->normalizeNullableString($validated['criterioForm']['descripcion'] ?? null),
            'peso' => $validated['criterioForm']['tipo_respuesta'] === FormularioCriterio::TIPO_TEXTO
                ? 0
                : (float) $validated['criterioForm']['peso'],
            'orden' => (int) $validated['criterioForm']['orden'],
            'tipo_respuesta' => $validated['criterioForm']['tipo_respuesta'],
            'obligatoria' => (bool) ($validated['criterioForm']['obligatoria'] ?? false),
            'activo' => (bool) ($validated['criterioForm']['activo'] ?? false),
        ];

        try {
            if ($this->editingCriterioId) {
                $criterio = FormularioCriterio::query()->findOrFail($this->editingCriterioId);
                $criterio->fill($payload)->save();
            } else {
                FormularioCriterio::query()->create([
                    'formulario_evaluacion_id' => $this->selectedFormularioId,
                    ...$payload,
                ]);
            }
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                $this->addError('criterioForm.orden', 'Ya existe un criterio con ese orden dentro del formulario seleccionado.');

                return;
            }

            throw $exception;
        }

        $this->resetCriterioForm();
        $this->loadFormularios();
        $this->resetValidation();

        session()->flash('status', 'Criterio guardado correctamente.');
    }

    protected function schemaIsReady(): bool
    {
        return Schema::hasTable('periodos_evaluacion')
            && Schema::hasTable('formularios_evaluacion')
            && Schema::hasTable('formulario_criterios');
    }

    protected function loadPeriodos(): void
    {
        $this->periodos = PeriodoEvaluacion::query()
            ->orderByDesc('activo')
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    protected function loadFormularios(): void
    {
        $this->formularios = FormularioEvaluacion::query()
            ->with(['criterios' => fn ($query) => $query->orderBy('orden')])
            ->orderBy('tipo_evaluador')
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        if ($this->selectedFormularioId !== null && $this->formularios->doesntContain('id', $this->selectedFormularioId)) {
            $this->selectedFormularioId = null;
        }

        if ($this->selectedFormularioId === null && $this->formularios->isNotEmpty()) {
            $this->selectedFormularioId = $this->formularios->first()->id;
        }
    }

    protected function periodoRules(): array
    {
        return [
            'periodoForm.nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('periodos_evaluacion', 'nombre')->ignore($this->editingPeriodoId),
            ],
            'periodoForm.fecha_inicio' => ['required', 'date'],
            'periodoForm.fecha_fin' => ['required', 'date', 'after_or_equal:periodoForm.fecha_inicio'],
            'periodoForm.activo' => ['boolean'],
        ];
    }

    protected function formularioRules(): array
    {
        return [
            'formularioForm.nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('formularios_evaluacion', 'nombre')
                    ->ignore($this->editingFormularioId)
                    ->where(fn ($query) => $query->where('tipo_evaluador', $this->formularioForm['tipo_evaluador'] ?? '')),
            ],
            'formularioForm.tipo_evaluador' => ['required', Rule::in([
                FormularioEvaluacion::TIPO_ALUMNO,
                FormularioEvaluacion::TIPO_FUNCIONARIO,
            ])],
            'formularioForm.descripcion' => ['nullable', 'string'],
            'formularioForm.escala_min' => ['required', 'integer', 'min:0'],
            'formularioForm.escala_max' => ['required', 'integer', 'gte:formularioForm.escala_min'],
            'formularioForm.activo' => ['boolean'],
        ];
    }

    protected function criterioRules(): array
    {
        return [
            'criterioForm.pregunta' => ['required', 'string'],
            'criterioForm.descripcion' => ['nullable', 'string'],
            'criterioForm.peso' => ['required', 'numeric', 'min:0'],
            'criterioForm.orden' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('formulario_criterios', 'orden')
                    ->ignore($this->editingCriterioId)
                    ->where(fn ($query) => $query->where('formulario_evaluacion_id', $this->selectedFormularioId)),
            ],
            'criterioForm.tipo_respuesta' => ['required', Rule::in([
                FormularioCriterio::TIPO_ESCALA,
                FormularioCriterio::TIPO_TEXTO,
                FormularioCriterio::TIPO_MIXTO,
            ])],
            'criterioForm.obligatoria' => ['boolean'],
            'criterioForm.activo' => ['boolean'],
        ];
    }

    protected function resetPeriodoForm(): void
    {
        $this->editingPeriodoId = null;
        $this->periodoForm = [
            'nombre' => '',
            'fecha_inicio' => '',
            'fecha_fin' => '',
            'activo' => false,
        ];
    }

    protected function resetFormularioForm(): void
    {
        $this->editingFormularioId = null;
        $this->formularioForm = [
            'nombre' => '',
            'tipo_evaluador' => FormularioEvaluacion::TIPO_ALUMNO,
            'descripcion' => '',
            'escala_min' => '1',
            'escala_max' => '5',
            'activo' => false,
        ];
    }

    protected function resetCriterioForm(): void
    {
        $this->editingCriterioId = null;
        $this->criterioForm = [
            'pregunta' => '',
            'descripcion' => '',
            'peso' => '0',
            'orden' => '',
            'tipo_respuesta' => FormularioCriterio::TIPO_ESCALA,
            'obligatoria' => true,
            'activo' => true,
        ];
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = trim($value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array($exception->errorInfo[0] ?? null, ['23000', '23505'], true);
    }
}; ?>

<div class="space-y-6">
    <x-slot name="header">Configuracion de Evaluacion Docente</x-slot>

    <x-mary-header title="Configuracion de Evaluacion Docente" subtitle="Mantenimiento de periodos activos, formularios y criterios" separator />

    @if (session('status'))
        <x-mary-alert title="{{ session('status') }}" icon="o-check-circle" class="alert-success" />
    @endif

    @if (! $schemaReady)
        <x-mary-alert title="{{ $schemaMessage }}" icon="o-exclamation-triangle" class="alert-warning" />
    @else
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="glass-card card">
                <div class="card-body gap-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Periodos</p>
                    <p class="text-3xl font-semibold text-primary">{{ $periodos->count() }}</p>
                    <p class="text-sm text-base-content/65">Ventanas configuradas</p>
                </div>
            </article>

            <article class="glass-card card">
                <div class="card-body gap-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Activo</p>
                    <p class="text-lg font-semibold text-secondary">{{ $periodos->firstWhere('activo', true)?->nombre ?? 'Sin periodo activo' }}</p>
                    <p class="text-sm text-base-content/65">Solo uno debe quedar habilitado</p>
                </div>
            </article>

            <article class="glass-card card">
                <div class="card-body gap-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Formularios</p>
                    <p class="text-3xl font-semibold text-accent">{{ $formularios->count() }}</p>
                    <p class="text-sm text-base-content/65">Definiciones disponibles</p>
                </div>
            </article>

            <article class="glass-card card">
                <div class="card-body gap-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Tipos activos</p>
                    <p class="text-3xl font-semibold text-info">{{ $formularios->where('activo', true)->count() }}</p>
                    <p class="text-sm text-base-content/65">Uno por tipo de evaluador</p>
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <article class="glass-card card">
                <div class="card-body gap-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Periodo</p>
                            <h2 class="card-title text-lg text-base-content">
                                {{ $editingPeriodoId ? 'Editar periodo' : 'Nuevo periodo' }}
                            </h2>
                        </div>

                        @if ($editingPeriodoId)
                            <button type="button" wire:click="createNewPeriodo" class="btn btn-ghost btn-sm">Nuevo periodo</button>
                        @endif
                    </div>

                    <form wire:submit="savePeriodo" class="space-y-4">
                        <label class="form-control w-full">
                            <span class="label-text text-sm font-medium">Nombre</span>
                            <input wire:model="periodoForm.nombre" type="text" class="input input-bordered w-full" placeholder="Ej. Periodo Lectivo 2026" />
                            @error('periodoForm.nombre')
                                <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                            @enderror
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="form-control w-full">
                                <span class="label-text text-sm font-medium">Fecha inicio</span>
                                <input wire:model="periodoForm.fecha_inicio" type="date" class="input input-bordered w-full" />
                                @error('periodoForm.fecha_inicio')
                                    <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                @enderror
                            </label>

                            <label class="form-control w-full">
                                <span class="label-text text-sm font-medium">Fecha fin</span>
                                <input wire:model="periodoForm.fecha_fin" type="date" class="input input-bordered w-full" />
                                @error('periodoForm.fecha_fin')
                                    <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                @enderror
                            </label>
                        </div>

                        <label class="label cursor-pointer justify-start gap-3 rounded-2xl border border-base-300 bg-base-200/40 px-4 py-3">
                            <input wire:model="periodoForm.activo" type="checkbox" class="checkbox checkbox-primary" />
                            <span class="label-text font-medium">Periodo activo para recepcion de evaluaciones</span>
                        </label>

                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary min-w-48">
                                <span wire:loading.remove wire:target="savePeriodo">Guardar periodo</span>
                                <span wire:loading wire:target="savePeriodo" class="loading loading-spinner loading-sm"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </article>

            <article class="glass-card card">
                <div class="card-body gap-4">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Listado</p>
                        <h2 class="card-title text-lg text-base-content">Periodos registrados</h2>
                    </div>

                    @if ($periodos->isEmpty())
                        <x-mary-alert title="Todavia no hay periodos configurados." icon="o-information-circle" class="alert-info" />
                    @else
                        <div class="space-y-3">
                            @foreach ($periodos as $periodo)
                                <article wire:key="periodo-{{ $periodo->id }}" class="rounded-[1.5rem] border border-base-300 bg-base-100/75 p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="font-semibold text-base-content">{{ $periodo->nombre }}</h3>
                                                <span class="badge {{ $periodo->activo ? 'badge-success' : 'badge-ghost' }} badge-sm">
                                                    {{ $periodo->activo ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </div>
                                            <p class="text-sm text-base-content/65">
                                                {{ $periodo->fecha_inicio?->format('d/m/Y') }} al {{ $periodo->fecha_fin?->format('d/m/Y') }}
                                            </p>
                                        </div>

                                        <button type="button" wire:click="editPeriodo({{ $periodo->id }})" class="btn btn-outline btn-sm">
                                            Editar
                                        </button>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <article class="glass-card card">
                <div class="card-body gap-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Formulario</p>
                            <h2 class="card-title text-lg text-base-content">
                                {{ $editingFormularioId ? 'Editar formulario' : 'Nuevo formulario' }}
                            </h2>
                        </div>

                        @if ($editingFormularioId)
                            <button type="button" wire:click="createNewFormulario" class="btn btn-ghost btn-sm">Nuevo formulario</button>
                        @endif
                    </div>

                    <form wire:submit="saveFormulario" class="space-y-4">
                        <label class="form-control w-full">
                            <span class="label-text text-sm font-medium">Nombre</span>
                            <input wire:model="formularioForm.nombre" type="text" class="input input-bordered w-full" placeholder="Ej. Evaluacion docente por alumno" />
                            @error('formularioForm.nombre')
                                <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                            @enderror
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="form-control w-full">
                                <span class="label-text text-sm font-medium">Tipo de evaluador</span>
                                <select wire:model="formularioForm.tipo_evaluador" class="select select-bordered w-full">
                                    <option value="{{ \App\Models\FormularioEvaluacion::TIPO_ALUMNO }}">Alumno</option>
                                    <option value="{{ \App\Models\FormularioEvaluacion::TIPO_FUNCIONARIO }}">Funcionario</option>
                                </select>
                                @error('formularioForm.tipo_evaluador')
                                    <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                @enderror
                            </label>

                            <div class="grid gap-4 grid-cols-2">
                                <label class="form-control w-full">
                                    <span class="label-text text-sm font-medium">Escala min</span>
                                    <input wire:model="formularioForm.escala_min" type="number" min="0" class="input input-bordered w-full" />
                                    @error('formularioForm.escala_min')
                                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="form-control w-full">
                                    <span class="label-text text-sm font-medium">Escala max</span>
                                    <input wire:model="formularioForm.escala_max" type="number" min="0" class="input input-bordered w-full" />
                                    @error('formularioForm.escala_max')
                                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                    @enderror
                                </label>
                            </div>
                        </div>

                        <label class="form-control w-full">
                            <span class="label-text text-sm font-medium">Descripcion</span>
                            <textarea wire:model="formularioForm.descripcion" rows="3" class="textarea textarea-bordered w-full" placeholder="Contexto y finalidad del formulario"></textarea>
                            @error('formularioForm.descripcion')
                                <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="label cursor-pointer justify-start gap-3 rounded-2xl border border-base-300 bg-base-200/40 px-4 py-3">
                            <input wire:model="formularioForm.activo" type="checkbox" class="checkbox checkbox-primary" />
                            <span class="label-text font-medium">Formulario activo para su tipo de evaluador</span>
                        </label>

                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary min-w-48">
                                <span wire:loading.remove wire:target="saveFormulario">Guardar formulario</span>
                                <span wire:loading wire:target="saveFormulario" class="loading loading-spinner loading-sm"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </article>

            <article class="glass-card card">
                <div class="card-body gap-4">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Listado</p>
                        <h2 class="card-title text-lg text-base-content">Formularios registrados</h2>
                    </div>

                    @if ($formularios->isEmpty())
                        <x-mary-alert title="Todavia no hay formularios configurados." icon="o-information-circle" class="alert-info" />
                    @else
                        <div class="space-y-3">
                            @foreach ($formularios as $formulario)
                                @php
                                    $pesoActivo = $formulario->criterios
                                        ->where('activo', true)
                                        ->whereIn('tipo_respuesta', [\App\Models\FormularioCriterio::TIPO_ESCALA, \App\Models\FormularioCriterio::TIPO_MIXTO])
                                        ->sum('peso');
                                @endphp
                                <article wire:key="formulario-{{ $formulario->id }}" class="rounded-[1.5rem] border border-base-300 bg-base-100/75 p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="font-semibold text-base-content">{{ $formulario->nombre }}</h3>
                                                <span class="badge {{ $formulario->activo ? 'badge-success' : 'badge-ghost' }} badge-sm">
                                                    {{ $formulario->activo ? 'Activo' : 'Inactivo' }}
                                                </span>
                                                <span class="badge badge-outline badge-sm">{{ ucfirst($formulario->tipo_evaluador) }}</span>
                                            </div>
                                            <p class="text-sm text-base-content/65">Escala {{ $formulario->escala_min }} a {{ $formulario->escala_max }} · {{ $formulario->criterios->count() }} criterios · peso activo {{ $pesoActivo }}</p>
                                        </div>

                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" wire:click="editFormulario({{ $formulario->id }})" class="btn btn-outline btn-sm">Editar</button>
                                            <button type="button" wire:click="selectFormulario({{ $formulario->id }})" class="btn btn-primary btn-sm">
                                                {{ $selectedFormularioId === $formulario->id ? 'Seleccionado' : 'Ver criterios' }}
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </article>
        </section>

        <section class="glass-card card">
            <div class="card-body gap-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Criterios</p>
                        <h2 class="card-title text-lg text-base-content">Administrar criterios del formulario</h2>
                    </div>

                    @if ($editingCriterioId)
                        <button type="button" wire:click="createNewCriterio" class="btn btn-ghost btn-sm">Nuevo criterio</button>
                    @endif
                </div>

                @if (! $this->selectedFormulario)
                    <x-mary-alert title="Selecciona un formulario para cargar o ajustar sus criterios." icon="o-information-circle" class="alert-info" />
                @else
                    @php
                        $pesoSeleccionado = $this->selectedFormulario->criterios
                            ->where('activo', true)
                            ->whereIn('tipo_respuesta', [\App\Models\FormularioCriterio::TIPO_ESCALA, \App\Models\FormularioCriterio::TIPO_MIXTO])
                            ->sum('peso');
                    @endphp

                    <div class="rounded-[1.5rem] border border-base-300 bg-base-200/40 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-base-content">{{ $this->selectedFormulario->nombre }}</p>
                                <p class="text-sm text-base-content/65">{{ ucfirst($this->selectedFormulario->tipo_evaluador) }} · Escala {{ $this->selectedFormulario->escala_min }} a {{ $this->selectedFormulario->escala_max }}</p>
                            </div>
                            <span class="badge {{ $pesoSeleccionado == 100.0 ? 'badge-success' : 'badge-warning' }} badge-lg">
                                Peso activo {{ $pesoSeleccionado }}
                            </span>
                        </div>
                    </div>

                    @if ($pesoSeleccionado != 100.0)
                        <x-mary-alert title="La suma de pesos de los criterios numericos activos deberia cerrar en 100 para mantener una configuracion consistente." icon="o-exclamation-triangle" class="alert-warning" />
                    @endif

                    <div class="grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                        <article class="rounded-[1.75rem] border border-base-300 bg-base-100/75 p-5">
                            <div class="space-y-1 mb-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Editor</p>
                                <h3 class="text-lg font-semibold text-base-content">
                                    {{ $editingCriterioId ? 'Editar criterio' : 'Nuevo criterio' }}
                                </h3>
                            </div>

                            <form wire:submit="saveCriterio" class="space-y-4">
                                <label class="form-control w-full">
                                    <span class="label-text text-sm font-medium">Pregunta</span>
                                    <textarea wire:model="criterioForm.pregunta" rows="3" class="textarea textarea-bordered w-full" placeholder="Texto de la pregunta"></textarea>
                                    @error('criterioForm.pregunta')
                                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="form-control w-full">
                                    <span class="label-text text-sm font-medium">Descripcion</span>
                                    <textarea wire:model="criterioForm.descripcion" rows="2" class="textarea textarea-bordered w-full" placeholder="Contexto opcional"></textarea>
                                    @error('criterioForm.descripcion')
                                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <div class="grid gap-4 md:grid-cols-3">
                                    <label class="form-control w-full">
                                        <span class="label-text text-sm font-medium">Orden</span>
                                        <input wire:model="criterioForm.orden" type="number" min="1" class="input input-bordered w-full" />
                                        @error('criterioForm.orden')
                                            <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <label class="form-control w-full">
                                        <span class="label-text text-sm font-medium">Peso</span>
                                        <input wire:model="criterioForm.peso" type="number" min="0" step="0.01" class="input input-bordered w-full" />
                                        @error('criterioForm.peso')
                                            <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <label class="form-control w-full">
                                        <span class="label-text text-sm font-medium">Tipo</span>
                                        <select wire:model="criterioForm.tipo_respuesta" class="select select-bordered w-full">
                                            <option value="{{ \App\Models\FormularioCriterio::TIPO_ESCALA }}">Escala</option>
                                            <option value="{{ \App\Models\FormularioCriterio::TIPO_TEXTO }}">Texto</option>
                                            <option value="{{ \App\Models\FormularioCriterio::TIPO_MIXTO }}">Mixto</option>
                                        </select>
                                        @error('criterioForm.tipo_respuesta')
                                            <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                        @enderror
                                    </label>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2">
                                    <label class="label cursor-pointer justify-start gap-3 rounded-2xl border border-base-300 bg-base-200/40 px-4 py-3">
                                        <input wire:model="criterioForm.obligatoria" type="checkbox" class="checkbox checkbox-primary" />
                                        <span class="label-text font-medium">Respuesta obligatoria</span>
                                    </label>

                                    <label class="label cursor-pointer justify-start gap-3 rounded-2xl border border-base-300 bg-base-200/40 px-4 py-3">
                                        <input wire:model="criterioForm.activo" type="checkbox" class="checkbox checkbox-primary" />
                                        <span class="label-text font-medium">Criterio activo</span>
                                    </label>
                                </div>

                                @error('criterio')
                                    <p class="text-sm font-medium text-error">{{ $message }}</p>
                                @enderror

                                <div class="flex justify-end">
                                    <button type="submit" class="btn btn-primary min-w-48">
                                        <span wire:loading.remove wire:target="saveCriterio">Guardar criterio</span>
                                        <span wire:loading wire:target="saveCriterio" class="loading loading-spinner loading-sm"></span>
                                    </button>
                                </div>
                            </form>
                        </article>

                        <article class="rounded-[1.75rem] border border-base-300 bg-base-100/75 p-5">
                            <div class="space-y-1 mb-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Actuales</p>
                                <h3 class="text-lg font-semibold text-base-content">Criterios del formulario</h3>
                            </div>

                            @if ($this->selectedFormulario->criterios->isEmpty())
                                <x-mary-alert title="Este formulario todavia no tiene criterios cargados." icon="o-information-circle" class="alert-info" />
                            @else
                                <div class="space-y-3">
                                    @foreach ($this->selectedFormulario->criterios as $criterio)
                                        <article wire:key="criterio-{{ $criterio->id }}" class="rounded-[1.5rem] border border-base-300 bg-base-100 p-4">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div class="space-y-1">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="badge badge-outline badge-sm">#{{ $criterio->orden }}</span>
                                                        <span class="badge {{ $criterio->activo ? 'badge-success' : 'badge-ghost' }} badge-sm">{{ $criterio->activo ? 'Activo' : 'Inactivo' }}</span>
                                                        <span class="badge badge-ghost badge-sm">{{ $criterio->tipo_respuesta }}</span>
                                                    </div>
                                                    <p class="font-medium text-base-content">{{ $criterio->pregunta }}</p>
                                                    <p class="text-sm text-base-content/65">Peso {{ $criterio->peso }} · {{ $criterio->obligatoria ? 'Obligatorio' : 'Opcional' }}</p>
                                                </div>

                                                <button type="button" wire:click="editCriterio({{ $criterio->id }})" class="btn btn-outline btn-sm">Editar</button>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    </div>
                @endif
            </div>
        </section>
    @endif
</div>