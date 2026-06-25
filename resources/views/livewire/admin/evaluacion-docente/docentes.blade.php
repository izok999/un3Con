<?php

use App\Enums\RoleName;
use App\Models\Docente;
use App\Models\User;
use App\Services\AlumnoExternoService;
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

    public bool $schemaReady = true;

    public string $schemaMessage = '';

    /** @var array<int, string> */
    public array $catCarreras = [];

    /** @var array<int, string> */
    public array $catSedes = [];

    /** @var array<int, int> */
    public array $allowedSedeIds = [];

    public bool $isGeneralAdmin = false;

    public bool $ready = false;

    /** @var array{total: int, activos: int, contextos: int} */
    public array $stats = [
        'total' => 0,
        'activos' => 0,
        'contextos' => 0,
    ];

    public function boot(): void
    {
        $this->docentes = collect();
    }

    public function mount(): void
    {
        $this->resetDocenteForm();
        $this->allowedSedeIds = $this->resolveAllowedSedeIds();

        /** @var ?User $user */
        $user = Auth::user();
        $this->isGeneralAdmin = $user?->isGeneralAdmin() ?? false;

        $this->schemaReady = $this->schemaIsReady();

        if (! $this->schemaReady) {
            $this->schemaMessage = 'Las tablas locales de evaluación docente todavía no están disponibles. Ejecutá las migraciones del módulo para administrar docentes y contextos.';
        }
    }

    public function inicializarComponente(): void
    {
        if (! $this->schemaReady) {
            return;
        }

        $this->loadCatalogs();
        $this->loadDocentes();
        $this->cargarStats();
        $this->ready = true;
    }

    public function deleteDocente(int $docenteId): void
    {
        if (! $this->isGeneralAdmin) {
            abort(403);
        }

        $docente = Docente::query()->findOrFail($docenteId);
        $evaluacionesCount = $docente->evaluaciones()->count();

        $docente->delete();

        if ($this->editingDocenteId === $docenteId) {
            $this->resetDocenteForm();
        }

        $this->loadDocentes();
        $this->cargarStats();

        $message = $evaluacionesCount > 0
            ? "Docente eliminado. Se preservan {$evaluacionesCount} evaluaciones históricas."
            : 'Docente eliminado correctamente.';

        session()->flash('status', $message);
    }

    public function updatedSearch(): void
    {
        if (! $this->schemaReady || ! $this->ready) {
            return;
        }

        $this->loadDocentes();
    }

    public function createNewDocente(): void
    {
        $this->selectedDocenteId = null;
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
        $this->cargarStats();
        $this->resetValidation();
        $this->dispatch('docente-saved');

        session()->flash('status', $message);
    }

    public function refreshDocentes(): void
    {
        $this->loadDocentes();
        $this->cargarStats();
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

            if ($this->isScopedAcademicAdmin()) {
                $this->catSedes = array_intersect_key($this->catSedes, array_flip($this->allowedSedeIds));
            }
        } catch (\Throwable) {
            // Si la base externa no está disponible los catálogos quedan vacíos
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
    }

    protected function cargarStats(): void
    {
        $totalQuery = Docente::query();
        $activosQuery = Docente::query()->where('activo', true);
        $contextosQuery = \App\Models\DocenteContexto::query();

        if ($this->isScopedAcademicAdmin()) {
            $scopeClosure = function ($builder): void {
                $builder
                    ->doesntHave('contextos')
                    ->orWhereHas('contextos', fn ($contextosQuery) => $contextosQuery->whereIn('sed_id', $this->allowedSedeIds));
            };

            $totalQuery->where($scopeClosure);
            $activosQuery->where($scopeClosure);
            $contextosQuery->whereIn('sed_id', $this->allowedSedeIds);
        }

        $this->stats = [
            'total' => $totalQuery->count(),
            'activos' => $activosQuery->count(),
            'contextos' => $contextosQuery->count(),
        ];
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
        $this->selectedDocenteId = null;
        $this->docenteForm = [
            'nombre' => '',
            'documento' => '',
            'docente_externo_id' => '',
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
}; ?>

<div
    class="space-y-6"
    x-data="{
        expandedDocenteId: null,
        editingDocenteId: {{ Js::from($editingDocenteId) }},
        minDocenteForm: false,
        deletingDocenteId: null,
        deletingDocenteNombre: '',

        toggleDocente(docenteId) {
            this.expandedDocenteId = (this.expandedDocenteId === docenteId) ? null : docenteId;
        },

        isExpanded(docenteId) {
            return this.expandedDocenteId === docenteId;
        },

        init() {
            this.$watch('editingDocenteId', (val) => {
                if (val && !this.minDocenteForm) {
                    this.minDocenteForm = true;
                }
            });
        }
    }"
    x-on:keydown.escape.window="deletingDocenteId = null"
    @docente-saved.window="
        editingDocenteId = $wire.get('editingDocenteId');
    "
    @contextos-updated.window="$wire.refreshDocentes()"
    wire:init="inicializarComponente"
>
    <x-slot name="header">Docentes para Evaluación</x-slot>

    <x-mary-header title="Docentes para Evaluación" subtitle="Carga local de docentes y contextos académicos habilitantes" separator />

    @if (session('status'))
        <x-mary-alert title="{{ session('status') }}" icon="o-check-circle" class="alert-success" />
    @endif

    @if (! $schemaReady)
        <x-mary-alert title="{{ $schemaMessage }}" icon="o-exclamation-triangle" class="alert-warning" />
    @elseif (! $ready)
        {{-- ===== SKELETAL LOADING ===== --}}
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="glass-card card">
                <div class="card-body gap-3">
                    <div class="skeleton h-3 w-20 rounded-lg"></div>
                    <div class="skeleton h-9 w-16 rounded-xl"></div>
                    <div class="skeleton h-3 w-32 rounded-lg"></div>
                </div>
            </article>
            <article class="glass-card card">
                <div class="card-body gap-3">
                    <div class="skeleton h-3 w-16 rounded-lg"></div>
                    <div class="skeleton h-9 w-16 rounded-xl"></div>
                    <div class="skeleton h-3 w-40 rounded-lg"></div>
                </div>
            </article>
            <article class="glass-card card md:col-span-2 xl:col-span-2">
                <div class="card-body gap-3">
                    <div class="skeleton h-3 w-20 rounded-lg"></div>
                    <div class="skeleton h-9 w-16 rounded-xl"></div>
                    <div class="skeleton h-3 w-56 rounded-lg"></div>
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            {{-- Teacher Form Skeleton --}}
            <article class="glass-card card">
                <div class="card-body gap-4">
                    <div class="space-y-2">
                        <div class="skeleton h-3 w-16 rounded-lg"></div>
                        <div class="skeleton h-6 w-40 rounded-lg"></div>
                    </div>
                    <div class="space-y-4">
                        <div class="space-y-1.5">
                            <div class="skeleton h-3 w-28 rounded-lg"></div>
                            <div class="skeleton h-12 w-full rounded-2xl"></div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-1.5">
                                <div class="skeleton h-3 w-20 rounded-lg"></div>
                                <div class="skeleton h-12 w-full rounded-2xl"></div>
                            </div>
                            <div class="space-y-1.5">
                                <div class="skeleton h-3 w-20 rounded-lg"></div>
                                <div class="skeleton h-12 w-full rounded-2xl"></div>
                            </div>
                        </div>
                        <div class="skeleton h-14 w-full rounded-2xl"></div>
                        <div class="flex justify-end">
                            <div class="skeleton h-10 w-48 rounded-[1.15rem]"></div>
                        </div>
                    </div>
                </div>
            </article>

            {{-- Context Panel Skeleton --}}
            <article class="glass-card card">
                <div class="card-body gap-4">
                    <div class="space-y-2">
                        <div class="skeleton h-3 w-24 rounded-lg"></div>
                        <div class="skeleton h-6 w-44 rounded-lg"></div>
                    </div>
                    <div class="space-y-3">
                        <div class="skeleton h-12 w-full rounded-2xl"></div>
                        <div class="skeleton h-12 w-full rounded-2xl"></div>
                        <div class="skeleton h-12 w-full rounded-2xl"></div>
                        <div class="flex justify-end">
                            <div class="skeleton h-10 w-36 rounded-[1.15rem]"></div>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        {{-- Teacher List Skeleton --}}
        <section class="space-y-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div class="space-y-2">
                    <div class="skeleton h-3 w-24 rounded-lg"></div>
                    <div class="skeleton h-6 w-44 rounded-lg"></div>
                </div>
                <div class="space-y-1.5 w-full md:max-w-sm">
                    <div class="skeleton h-3 w-12 rounded-lg"></div>
                    <div class="skeleton h-12 w-full rounded-2xl"></div>
                </div>
            </div>

            <div class="glass-card card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr class="border-b border-base-300">
                                <th class="w-8"></th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Docente</th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Documento</th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Carreras</th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50 text-center">Ctx</th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (range(1, 6) as $i)
                                <tr class="border-b border-base-300/60">
                                    <td class="w-8">
                                        <div class="skeleton size-4 rounded"></div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="skeleton h-4 w-36 rounded-lg"></div>
                                            <div class="skeleton h-4 w-12 rounded-full"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="skeleton h-4 w-24 rounded-lg"></div>
                                    </td>
                                    <td>
                                        <div class="flex gap-1">
                                            <div class="skeleton h-4 w-16 rounded-full"></div>
                                            <div class="skeleton h-4 w-20 rounded-full"></div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="skeleton h-5 w-8 rounded-full mx-auto"></div>
                                    </td>
                                    <td>
                                        <div class="skeleton h-4 w-16 rounded-lg"></div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @else
        {{-- ===== REAL CONTENT ===== --}}
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="glass-card card">
                <div class="card-body gap-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Docentes</p>
                    <p class="text-3xl font-semibold text-primary">{{ $stats['total'] }}</p>
                    <p class="text-sm text-base-content/65">Proyección local disponible</p>
                </div>
            </article>

            <article class="glass-card card">
                <div class="card-body gap-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Activos</p>
                    <p class="text-3xl font-semibold text-secondary">{{ $stats['activos'] }}</p>
                    <p class="text-sm text-base-content/65">Docentes visibles para elegibilidad</p>
                </div>
            </article>

            <article class="glass-card card md:col-span-2 xl:col-span-2">
                <div class="card-body gap-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Contextos</p>
                    <p class="text-3xl font-semibold text-accent">{{ $stats['contextos'] }}</p>
                    <p class="text-sm text-base-content/65">Coincidencias académicas cargadas para resolver elegibilidad</p>
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            {{-- Teacher Form Column --}}
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

            {{-- Context Assignment Column (child component) --}}
            <article class="glass-card card">
                <div class="card-body gap-4">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Contexto habilitante</p>
                        <h2 class="card-title text-lg text-base-content">Asignar contexto al docente</h2>
                    </div>

                    <livewire:admin.evaluacion-docente.docente-contextos
                        :selected-docente-id="$selectedDocenteId"
                        :allowed-sede-ids="$allowedSedeIds"
                        wire:key="docente-contextos-{{ $selectedDocenteId ?? 'none' }}"
                    />
                </div>
            </article>
        </section>

        {{-- Teacher List --}}
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
                <div class="glass-card card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr class="border-b border-base-300">
                                    <th></th>
                                    <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Docente</th>
                                    <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Documento</th>
                                    <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Carreras</th>
                                    <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50 text-center">Ctx</th>
                                    <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($docentes as $docente)
                                    @php
                                        $carrerasUnicas = $docente->contextos
                                            ->pluck('car_id')
                                            ->filter()
                                            ->unique()
                                            ->values();
                                    @endphp
                                    <tr
                                        wire:key="docente-row-{{ $docente->id }}"
                                        @class([
                                            'border-b border-base-300/60 transition cursor-pointer',
                                            'bg-primary/5' => $editingDocenteId === $docente->id,
                                            'hover:bg-base-200/40',
                                        ])
                                        x-on:click="
                                            toggleDocente({{ $docente->id }});
                                            $wire.set('selectedDocenteId', isExpanded({{ $docente->id }}) ? null : {{ $docente->id }});
                                            $wire.call('editDocente', {{ $docente->id }});
                                        "
                                    >
                                        <td class="w-8">
                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                :class="'size-4 transition-transform duration-200 ' + (isExpanded({{ $docente->id }}) ? 'rotate-90' : '')"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold text-sm text-base-content">{{ $docente->nombre }}</span>
                                                <span @class([
                                                    'badge badge-xs',
                                                    'badge-success' => $docente->activo,
                                                    'badge-ghost' => ! $docente->activo,
                                                ])>
                                                    {{ $docente->activo ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-sm text-base-content/70">
                                            {{ $docente->documento ?? '—' }}
                                        </td>
                                        <td>
                                            @if ($carrerasUnicas->isNotEmpty())
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach ($carrerasUnicas->take(3) as $carId)
                                                        <span class="badge badge-soft badge-sm text-xs">{{ $catCarreras[$carId] ?? "ID {$carId}" }}</span>
                                                    @endforeach
                                                    @if ($carrerasUnicas->count() > 3)
                                                        <span class="badge badge-outline badge-sm text-xs">+{{ $carrerasUnicas->count() - 3 }} más</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-sm italic text-base-content/40">Sin contextos</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span @class([
                                                'badge badge-sm',
                                                'badge-primary' => $docente->contextos_count > 0,
                                                'badge-ghost' => $docente->contextos_count === 0,
                                            ])>
                                                {{ $docente->contextos_count }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm text-base-content/70 underline decoration-dotted underline-offset-2">
                                                    Editar
                                                </span>
                                                @if ($isGeneralAdmin)
                                                    <button
                                                        type="button"
                                                        class="btn btn-ghost btn-xs text-error"
                                                        x-on:click.stop="
                                                            deletingDocenteId = {{ $docente->id }};
                                                            deletingDocenteNombre = @js($docente->nombre);
                                                        "
                                                        title="Eliminar docente"
                                                    >
                                                        Eliminar
                                                    </button>
                                                @endif
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

    {{-- Delete confirmation modal --}}
    <div
        x-cloak
        x-show="deletingDocenteId !== null"
        x-on:click.self="deletingDocenteId = null"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
    >
        <div
            class="card bg-base-100 shadow-2xl rounded-[1.75rem] max-w-md w-full p-6"
            x-on:click.stop=""
        >
            <h3 class="text-lg font-semibold text-base-content">
                Eliminar docente
            </h3>
            <p class="py-4 text-base-content/70">
                ¿Eliminar a <strong x-text="deletingDocenteNombre"></strong>?
            </p>
            <p class="text-sm text-base-content/50 mb-4">
                Las evaluaciones registradas se preservan como historial.
            </p>
            <div class="flex justify-end gap-3">
                <button
                    type="button"
                    class="btn btn-ghost btn-sm"
                    x-on:click="deletingDocenteId = null"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="btn btn-error btn-sm"
                    x-on:click="
                        $wire.deleteDocente(deletingDocenteId);
                        deletingDocenteId = null;
                    "
                >
                    Eliminar
                </button>
            </div>
        </div>
    </div>
</div>