<?php

use App\Enums\RoleName;
use App\Models\Docente;
use App\Models\DocenteContexto;
use App\Models\User;
use App\Services\AlumnoExternoService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Collection $docentes;

    public string $search = '';

    public ?int $editingDocenteId = null;

    public ?int $selectedDocenteId = null;

    public array $docenteForm = [];

    public array $contextoForm = [];

    public bool $schemaReady = true;

    public string $schemaMessage = '';

    /** @var array<int, string> */
    public array $catCarreras = [];

    /** @var array<int, string> */
    public array $catSedes = [];

    /** @var array<int, string> */
    public array $catPeriodos = [];

    /** @var array<int, string> */
    public array $catTurnos = [];

    /** @var array<int, string> */
    public array $catSecciones = [];

    /** @var array<int, string> */
    public array $catMaterias = [];

    /** @var array<int, int> */
    public array $allowedSedeIds = [];

    public function boot(): void
    {
        $this->docentes = collect();
    }

    public function mount(): void
    {
        $this->resetDocenteForm();
        $this->resetContextoForm();
        $this->allowedSedeIds = $this->resolveAllowedSedeIds();

        $this->schemaReady = $this->schemaIsReady();

        if (! $this->schemaReady) {
            $this->schemaMessage = 'Las tablas locales de evaluación docente todavía no están disponibles. Ejecutá las migraciones del módulo para administrar docentes y contextos.';

            return;
        }

        $this->loadCatalogs();
        $this->loadDocentes();
    }

    public function getSelectedDocenteProperty(): ?Docente
    {
        /** @var ?Docente $docente */
        $docente = $this->docentes->firstWhere('id', $this->selectedDocenteId);

        return $docente;
    }

    public function updatedSearch(): void
    {
        if (! $this->schemaReady) {
            return;
        }

        $this->loadDocentes();
    }

    public function createNewDocente(): void
    {
        $this->resetDocenteForm();
        $this->resetValidation();
    }

    public function editDocente(int $docenteId): void
    {
        $docente = $this->findAccessibleDocenteOrFail($docenteId);

        $this->editingDocenteId = $docente->id;
        $this->selectedDocenteId = $docente->id;
        $this->fillDocenteForm($docente);
        $this->resetValidation();
    }

    public function selectDocente(int $docenteId): void
    {
        $this->findAccessibleDocenteOrFail($docenteId);

        $this->selectedDocenteId = $docenteId;
        $this->resetContextoForm();
        $this->resetValidation();
    }

    public function saveDocente(): void
    {
        if (! $this->schemaReady) {
            return;
        }

        $validated = $this->validate($this->docenteRules());
        $payload = [
            'nombre' => trim($validated['docenteForm']['nombre']),
            'documento' => $this->normalizeNullableString($validated['docenteForm']['documento'] ?? null),
            'docente_externo_id' => $this->normalizeNullableInt($validated['docenteForm']['docente_externo_id'] ?? null),
            'activo' => (bool) ($validated['docenteForm']['activo'] ?? false),
        ];

        if ($this->editingDocenteId) {
            $docente = $this->findAccessibleDocenteOrFail($this->editingDocenteId);
            $docente->fill($payload)->save();
            $message = 'Docente actualizado correctamente.';
        } else {
            $docente = Docente::query()->create($payload);
            $message = 'Docente creado correctamente.';
        }

        $docente->refresh();

        $this->editingDocenteId = $docente->id;
        $this->selectedDocenteId = $docente->id;
        $this->fillDocenteForm($docente);
        $this->loadDocentes();
        $this->resetValidation();

        session()->flash('status', $message);
    }

    public function saveContexto(): void
    {
        if (! $this->schemaReady) {
            return;
        }

        if (! $this->selectedDocenteId) {
            $this->addError('contexto', 'Seleccioná primero un docente para asignar el contexto.');

            return;
        }

        $validated = $this->validate($this->contextoRules());
        $payload = [
            'car_id' => $this->normalizeNullableInt($validated['contextoForm']['car_id'] ?? null),
            'sed_id' => $this->normalizeNullableInt($validated['contextoForm']['sed_id'] ?? null),
            'ple_id' => $this->normalizeNullableInt($validated['contextoForm']['ple_id'] ?? null),
            'mi2_id' => $this->normalizeNullableInt($validated['contextoForm']['mi2_id'] ?? null),
            'tur_id' => $this->normalizeNullableInt($validated['contextoForm']['tur_id'] ?? null),
            'sec_id' => $this->normalizeNullableInt($validated['contextoForm']['sec_id'] ?? null),
            'activo' => (bool) ($validated['contextoForm']['activo'] ?? false),
        ];

        if ($this->isScopedAcademicAdmin() && ! $this->canManageSede($payload['sed_id'])) {
            $this->addError('contextoForm.sed_id', 'Solo podés asignar contextos dentro de tus sedes habilitadas.');

            return;
        }

        $hasAtLeastOneScope = collect($payload)
            ->except('activo')
            ->contains(fn (mixed $value): bool => $value !== null);

        if (! $hasAtLeastOneScope) {
            $this->addError('contexto', 'Debes cargar al menos un identificador de contexto para el docente.');

            return;
        }

        try {
            DocenteContexto::query()->create([
                'docente_id' => $this->selectedDocenteId,
                ...$payload,
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                $this->addError('contexto', 'Ya existe un contexto idéntico para este docente.');

                return;
            }

            throw $exception;
        }

        $this->resetContextoForm();
        $this->loadDocentes();
        $this->resetValidation();

        session()->flash('status', 'Contexto agregado correctamente.');
    }

    public function removeContexto(int $contextoId): void
    {
        if (! $this->schemaReady) {
            return;
        }

        $contexto = DocenteContexto::query()->findOrFail($contextoId);

        if ($this->isScopedAcademicAdmin() && ! $this->canManageSede($contexto->sed_id)) {
            abort(403, 'No podés eliminar contextos fuera de tus sedes habilitadas.');
        }

        $contexto->delete();

        $this->loadDocentes();
        session()->flash('status', 'Contexto eliminado correctamente.');
    }

    protected function schemaIsReady(): bool
    {
        return Schema::hasTable('docentes')
            && Schema::hasTable('docente_contextos');
    }

    protected function loadCatalogs(): void
    {
        try {
            $service = app(AlumnoExternoService::class);
            $this->catCarreras = $service->catCarreras();
            $this->catSedes = $service->catSedes();
            $this->catPeriodos = $service->catPeriodos();
            $this->catTurnos = $service->catTurnos();
            $this->catSecciones = $service->catSecciones();

            if ($this->isScopedAcademicAdmin()) {
                $this->catSedes = array_intersect_key($this->catSedes, array_flip($this->allowedSedeIds));
            }
        } catch (\Throwable) {
            // Si la base externa no está disponible los catálogos quedan vacíos
        }
    }

    protected function resolveMiMaterias(): void
    {
        $mi2Ids = $this->docentes
            ->flatMap(fn (Docente $d) => $d->contextos ?? collect())
            ->pluck('mi2_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($mi2Ids)) {
            $this->catMaterias = [];

            return;
        }

        try {
            $this->catMaterias = app(AlumnoExternoService::class)->catMateriasPorIds($mi2Ids);
        } catch (\Throwable) {
            $this->catMaterias = [];
        }
    }

    protected function loadDocentes(): void
    {
        $search = trim($this->search);

        $query = Docente::query()
            ->with([
                'contextos' => fn ($contextosQuery) => $contextosQuery
                    ->when(
                        $this->isScopedAcademicAdmin(),
                        fn ($scopedQuery) => $scopedQuery->whereIn('sed_id', $this->allowedSedeIds),
                    )
                    ->orderByDesc('activo')
                    ->orderBy('id'),
            ])
            ->withCount([
                'contextos' => fn ($contextosQuery) => $contextosQuery
                    ->when(
                        $this->isScopedAcademicAdmin(),
                        fn ($scopedQuery) => $scopedQuery->whereIn('sed_id', $this->allowedSedeIds),
                    ),
            ])
            ->orderByDesc('activo')
            ->orderBy('nombre');

        if ($this->isScopedAcademicAdmin()) {
            $query->where(function ($builder): void {
                $builder
                    ->doesntHave('contextos')
                    ->orWhereHas('contextos', fn ($contextosQuery) => $contextosQuery->whereIn('sed_id', $this->allowedSedeIds));
            });
        }

        if ($search !== '') {
            $searchLike = '%'.$search.'%';

            $query->where(function ($builder) use ($searchLike): void {
                $builder
                    ->where('nombre', 'like', $searchLike)
                    ->orWhere('documento', 'like', $searchLike)
                    ->orWhereRaw('CAST(docente_externo_id AS TEXT) LIKE ?', [$searchLike]);
            });
        }

        $this->docentes = $query->get();

        if ($this->selectedDocenteId !== null && $this->docentes->doesntContain('id', $this->selectedDocenteId)) {
            $this->selectedDocenteId = null;
        }

        $this->resolveMiMaterias();
    }

    protected function docenteRules(): array
    {
        return [
            'docenteForm.nombre' => ['required', 'string', 'max:255'],
            'docenteForm.documento' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('docentes', 'documento')->ignore($this->editingDocenteId),
            ],
            'docenteForm.docente_externo_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('docentes', 'docente_externo_id')->ignore($this->editingDocenteId),
            ],
            'docenteForm.activo' => ['boolean'],
        ];
    }

    protected function contextoRules(): array
    {
        $sedIdRules = ['nullable', 'integer', 'min:1'];

        if ($this->isScopedAcademicAdmin()) {
            $sedIdRules = ['required', 'integer', 'min:1', Rule::in($this->allowedSedeIds)];
        }

        return [
            'contextoForm.car_id' => ['nullable', 'integer', 'min:1'],
            'contextoForm.sed_id' => $sedIdRules,
            'contextoForm.ple_id' => ['nullable', 'integer', 'min:1'],
            'contextoForm.mi2_id' => ['nullable', 'integer', 'min:1'],
            'contextoForm.tur_id' => ['nullable', 'integer', 'min:1'],
            'contextoForm.sec_id' => ['nullable', 'integer', 'min:1'],
            'contextoForm.activo' => ['boolean'],
        ];
    }

    protected function fillDocenteForm(Docente $docente): void
    {
        $this->docenteForm = [
            'nombre' => $docente->nombre,
            'documento' => $docente->documento ?? '',
            'docente_externo_id' => $docente->docente_externo_id !== null ? (string) $docente->docente_externo_id : '',
            'activo' => $docente->activo,
        ];
    }

    protected function resetDocenteForm(): void
    {
        $this->editingDocenteId = null;
        $this->docenteForm = [
            'nombre' => '',
            'documento' => '',
            'docente_externo_id' => '',
            'activo' => true,
        ];
    }

    protected function resetContextoForm(): void
    {
        $this->contextoForm = [
            'car_id' => '',
            'sed_id' => '',
            'ple_id' => '',
            'mi2_id' => '',
            'tur_id' => '',
            'sec_id' => '',
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

    protected function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array<int, int>
     */
    protected function resolveAllowedSedeIds(): array
    {
        /** @var ?User $user */
        $user = Auth::user();

        if (! $user || ! $user->hasRole(RoleName::AdminUnidadAcademica->value)) {
            return [];
        }

        return $user->managedSedeIds();
    }

    protected function isScopedAcademicAdmin(): bool
    {
        /** @var ?User $user */
        $user = Auth::user();

        return $user?->hasRole(RoleName::AdminUnidadAcademica->value) ?? false;
    }

    protected function canManageSede(?int $sedId): bool
    {
        if (! $this->isScopedAcademicAdmin()) {
            return true;
        }

        if ($sedId === null) {
            return false;
        }

        return in_array($sedId, $this->allowedSedeIds, true);
    }

    protected function findAccessibleDocenteOrFail(int $docenteId): Docente
    {
        return Docente::query()
            ->when(
                $this->isScopedAcademicAdmin(),
                fn ($query) => $query->where(function ($builder): void {
                    $builder
                        ->doesntHave('contextos')
                        ->orWhereHas('contextos', fn ($contextosQuery) => $contextosQuery->whereIn('sed_id', $this->allowedSedeIds));
                }),
            )
            ->findOrFail($docenteId);
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array($exception->errorInfo[0] ?? null, ['23000', '23505'], true);
    }
}; ?>

<div class="space-y-6">
    <x-slot name="header">Docentes para Evaluación</x-slot>

    <x-mary-header title="Docentes para Evaluación" subtitle="Carga local de docentes y contextos académicos habilitantes" separator />

    @if (session('status'))
        <x-mary-alert title="{{ session('status') }}" icon="o-check-circle" class="alert-success" />
    @endif

    @if (! $schemaReady)
        <x-mary-alert title="{{ $schemaMessage }}" icon="o-exclamation-triangle" class="alert-warning" />
    @else
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="glass-card card">
                <div class="card-body gap-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Docentes</p>
                    <p class="text-3xl font-semibold text-primary">{{ $docentes->count() }}</p>
                    <p class="text-sm text-base-content/65">Proyección local disponible</p>
                </div>
            </article>

            <article class="glass-card card">
                <div class="card-body gap-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Activos</p>
                    <p class="text-3xl font-semibold text-secondary">{{ $docentes->where('activo', true)->count() }}</p>
                    <p class="text-sm text-base-content/65">Docentes visibles para elegibilidad</p>
                </div>
            </article>

            <article class="glass-card card md:col-span-2 xl:col-span-2">
                <div class="card-body gap-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Contextos</p>
                    <p class="text-3xl font-semibold text-accent">{{ $docentes->sum('contextos_count') }}</p>
                    <p class="text-sm text-base-content/65">Coincidencias académicas cargadas para resolver elegibilidad</p>
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <article class="glass-card card">
                <div class="card-body gap-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Docente</p>
                            <h2 class="card-title text-lg text-base-content">
                                {{ $editingDocenteId ? 'Editar docente' : 'Nuevo docente' }}
                            </h2>
                        </div>

                        @if ($editingDocenteId)
                            <button type="button" wire:click="createNewDocente" class="btn btn-ghost btn-sm">
                                Nuevo registro
                            </button>
                        @endif
                    </div>

                    <form wire:submit="saveDocente" class="space-y-4">
                        <label class="form-control w-full">
                            <span class="label-text text-sm font-medium">Nombre completo</span>
                            <input wire:model="docenteForm.nombre" type="text" class="input input-bordered w-full" placeholder="Ej. Ada Lovelace" />
                            @error('docenteForm.nombre')
                                <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                            @enderror
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="form-control w-full">
                                <span class="label-text text-sm font-medium">Documento</span>
                                <input wire:model="docenteForm.documento" type="text" class="input input-bordered w-full" placeholder="Opcional" />
                                @error('docenteForm.documento')
                                    <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                @enderror
                            </label>

                            <label class="form-control w-full">
                                <span class="label-text text-sm font-medium">ID externo</span>
                                <input wire:model="docenteForm.docente_externo_id" type="number" min="1" class="input input-bordered w-full" placeholder="Opcional" />
                                @error('docenteForm.docente_externo_id')
                                    <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                @enderror
                            </label>
                        </div>

                        <label class="label cursor-pointer justify-start gap-3 rounded-2xl border border-base-300 bg-base-200/40 px-4 py-3">
                            <input wire:model="docenteForm.activo" type="checkbox" class="checkbox checkbox-primary" />
                            <span class="label-text font-medium">Docente activo para elegibilidad</span>
                        </label>

                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary min-w-48">
                                <span wire:loading.remove wire:target="saveDocente">
                                    {{ $editingDocenteId ? 'Guardar cambios' : 'Crear docente' }}
                                </span>
                                <span wire:loading wire:target="saveDocente" class="loading loading-spinner loading-sm"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </article>

            <article class="glass-card card">
                <div class="card-body gap-4">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Contexto habilitante</p>
                        <h2 class="card-title text-lg text-base-content">Asignar contexto al docente</h2>
                    </div>

                    @if (! $this->selectedDocente)
                        <x-mary-alert title="Seleccioná un docente desde el listado para asociar su contexto académico." icon="o-information-circle" class="alert-info" />
                    @else
                        <div class="rounded-2xl border border-base-300 bg-base-200/40 p-4">
                            <p class="text-sm font-semibold text-base-content">{{ $this->selectedDocente->nombre }}</p>
                            <p class="text-sm text-base-content/65">
                                Documento: {{ $this->selectedDocente->documento ?? 'Sin dato' }}
                                · Contextos cargados: {{ $this->selectedDocente->contextos_count }}
                            </p>
                        </div>

                        <form wire:submit="saveContexto" class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <label class="form-control w-full">
                                    <span class="label-text text-sm font-medium">Carrera</span>
                                    <select wire:model="contextoForm.car_id" class="select select-bordered w-full">
                                        <option value="">— Cualquier carrera —</option>
                                        @foreach ($catCarreras as $id => $nombre)
                                            <option value="{{ $id }}">{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error('contextoForm.car_id')
                                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="form-control w-full">
                                    <span class="label-text text-sm font-medium">Sede</span>
                                    <select wire:model="contextoForm.sed_id" class="select select-bordered w-full">
                                        <option value="">— Cualquier sede —</option>
                                        @foreach ($catSedes as $id => $nombre)
                                            <option value="{{ $id }}">{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error('contextoForm.sed_id')
                                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="form-control w-full">
                                    <span class="label-text text-sm font-medium">Período lectivo</span>
                                    <select wire:model="contextoForm.ple_id" class="select select-bordered w-full">
                                        <option value="">— Cualquier período —</option>
                                        @foreach ($catPeriodos as $id => $nombre)
                                            <option value="{{ $id }}">{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error('contextoForm.ple_id')
                                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="form-control w-full">
                                    <span class="label-text text-sm font-medium">Materia (ID)</span>
                                    <input wire:model="contextoForm.mi2_id" type="number" min="1" class="input input-bordered w-full" placeholder="ID de materia" />
                                    @error('contextoForm.mi2_id')
                                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="form-control w-full">
                                    <span class="label-text text-sm font-medium">Turno</span>
                                    <select wire:model="contextoForm.tur_id" class="select select-bordered w-full">
                                        <option value="">— Cualquier turno —</option>
                                        @foreach ($catTurnos as $id => $nombre)
                                            <option value="{{ $id }}">{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error('contextoForm.tur_id')
                                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="form-control w-full">
                                    <span class="label-text text-sm font-medium">Sección</span>
                                    <select wire:model="contextoForm.sec_id" class="select select-bordered w-full">
                                        <option value="">— Cualquier sección —</option>
                                        @foreach ($catSecciones as $id => $nombre)
                                            <option value="{{ $id }}">{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                    @error('contextoForm.sec_id')
                                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                    @enderror
                                </label>
                            </div>

                            <label class="label cursor-pointer justify-start gap-3 rounded-2xl border border-base-300 bg-base-200/40 px-4 py-3">
                                <input wire:model="contextoForm.activo" type="checkbox" class="checkbox checkbox-primary" />
                                <span class="label-text font-medium">Contexto activo para coincidencia</span>
                            </label>

                            @error('contexto')
                                <p class="text-sm font-medium text-error">{{ $message }}</p>
                            @enderror

                            <div class="flex justify-end">
                                <button type="submit" class="btn btn-primary min-w-48">
                                    <span wire:loading.remove wire:target="saveContexto">Agregar contexto</span>
                                    <span wire:loading wire:target="saveContexto" class="loading loading-spinner loading-sm"></span>
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </article>
        </section>

        <section class="space-y-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Listado operativo</p>
                    <h2 class="text-lg font-semibold text-base-content">Docentes cargados</h2>
                </div>

                <label class="form-control w-full md:max-w-sm">
                    <span class="label-text text-sm font-medium">Buscar</span>
                    <input wire:model.live.debounce.300ms="search" type="text" class="input input-bordered w-full" placeholder="Nombre, documento o ID externo" />
                </label>
            </div>

            @if ($docentes->isEmpty())
                <x-mary-alert title="Todavía no hay docentes cargados en la proyección local." icon="o-information-circle" class="alert-info" />
            @else
                <div class="grid grid-cols-1 gap-4 2xl:grid-cols-2">
                    @foreach ($docentes as $docente)
                        <article
                            wire:key="docente-{{ $docente->id }}"
                            @class([
                                'glass-card card border transition',
                                'border-primary/40' => $selectedDocenteId === $docente->id,
                                'border-base-300' => $selectedDocenteId !== $docente->id,
                            ])
                        >
                            <div class="card-body gap-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="card-title text-base text-base-content">{{ $docente->nombre }}</h3>
                                            <span @class([
                                                'badge badge-sm',
                                                'badge-success' => $docente->activo,
                                                'badge-ghost' => ! $docente->activo,
                                            ])>
                                                {{ $docente->activo ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-base-content/65">Documento: {{ $docente->documento ?? 'Sin dato' }}</p>
                                        <p class="text-sm text-base-content/65">ID externo: {{ $docente->docente_externo_id ?? 'Sin dato' }}</p>
                                    </div>

                                    <span class="badge badge-outline badge-sm">{{ $docente->contextos_count }} contextos</span>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="button" wire:click="editDocente({{ $docente->id }})" class="btn btn-outline btn-sm">
                                        Editar docente
                                    </button>
                                    <button type="button" wire:click="selectDocente({{ $docente->id }})" class="btn btn-primary btn-sm">
                                        {{ $selectedDocenteId === $docente->id ? 'Contexto activo' : 'Cargar contexto' }}
                                    </button>
                                </div>

                                @if ($docente->contextos->isEmpty())
                                    <div class="rounded-2xl border border-dashed border-base-300 bg-base-200/30 px-4 py-3 text-sm text-base-content/65">
                                        Sin contextos cargados todavía.
                                    </div>
                                @else
                                    <div class="overflow-x-auto rounded-2xl border border-base-300 bg-base-100/70">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Contexto</th>
                                                    <th class="text-center">Estado</th>
                                                    <th class="text-right">Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($docente->contextos as $contexto)
                                                    <tr wire:key="contexto-{{ $contexto->id }}">
                                                        <td class="text-sm text-base-content/80">
                                                            <div class="space-y-0.5">
                                                                @if ($contexto->car_id !== null)
                                                                    <div><span class="text-base-content/55">Carrera:</span> {{ $catCarreras[$contexto->car_id] ?? "ID {$contexto->car_id}" }}</div>
                                                                @endif
                                                                @if ($contexto->sed_id !== null)
                                                                    <div><span class="text-base-content/55">Sede:</span> {{ $catSedes[$contexto->sed_id] ?? "ID {$contexto->sed_id}" }}</div>
                                                                @endif
                                                                @if ($contexto->ple_id !== null)
                                                                    <div><span class="text-base-content/55">Período:</span> {{ $catPeriodos[$contexto->ple_id] ?? "ID {$contexto->ple_id}" }}</div>
                                                                @endif
                                                                @if ($contexto->mi2_id !== null)
                                                                    <div><span class="text-base-content/55">Materia:</span> {{ $catMaterias[$contexto->mi2_id] ?? "ID {$contexto->mi2_id}" }}</div>
                                                                @endif
                                                                @if ($contexto->tur_id !== null)
                                                                    <div><span class="text-base-content/55">Turno:</span> {{ $catTurnos[$contexto->tur_id] ?? "ID {$contexto->tur_id}" }}</div>
                                                                @endif
                                                                @if ($contexto->sec_id !== null)
                                                                    <div><span class="text-base-content/55">Sección:</span> {{ $catSecciones[$contexto->sec_id] ?? "ID {$contexto->sec_id}" }}</div>
                                                                @endif
                                                                @if ($contexto->car_id === null && $contexto->sed_id === null && $contexto->ple_id === null && $contexto->mi2_id === null && $contexto->tur_id === null && $contexto->sec_id === null)
                                                                    <span class="italic text-base-content/45">Global (sin restricciones)</span>
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <span @class([
                                                                'badge badge-sm',
                                                                'badge-success' => $contexto->activo,
                                                                'badge-ghost' => ! $contexto->activo,
                                                            ])>
                                                                {{ $contexto->activo ? 'Activo' : 'Inactivo' }}
                                                            </span>
                                                        </td>
                                                        <td class="text-right">
                                                            <button type="button" wire:click="removeContexto({{ $contexto->id }})" class="btn btn-ghost btn-xs text-error">
                                                                Eliminar
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</div>
