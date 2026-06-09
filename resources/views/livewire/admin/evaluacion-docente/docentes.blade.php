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
use Livewire\Attributes\Computed;
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

    /**
     * Contextos del docente seleccionado en el sistema externo (BD legacy).
     *
     * @var array<int, array{car_id: int|null, sed_id: int|null, ple_id: int|null, mi2_id: int|null, tur_id: int|null, sec_id: int|null}>
     */
    public array $contextosExternos = [];

    public string $filtroPleCodigo = '';

    public bool $showManualForm = false;

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

    public function updatedContextoForm(mixed $value, string $key): void
    {
        match ($key) {
            'sed_id' => $this->resetContextoFormCascade('car_id', 'mi2_id', 'ple_id', 'tur_id', 'sec_id'),
            'car_id' => $this->resetContextoFormCascade('mi2_id', 'ple_id', 'tur_id', 'sec_id'),
            'mi2_id' => $this->resetContextoFormCascade('ple_id', 'tur_id', 'sec_id'),
            'ple_id' => $this->resetContextoFormCascade('tur_id', 'sec_id'),
            'tur_id' => $this->resetContextoFormCascade('sec_id'),
            default => null,
        };
    }

    public function onContextoFieldChanged(string $field, string $value): void
    {
        $this->contextoForm[$field] = $value;

        $cascade = match ($field) {
            'sed_id' => ['car_id', 'mi2_id', 'ple_id', 'tur_id', 'sec_id'],
            'car_id' => ['mi2_id', 'ple_id', 'tur_id', 'sec_id'],
            'mi2_id' => ['ple_id', 'tur_id', 'sec_id'],
            'ple_id' => ['tur_id', 'sec_id'],
            'tur_id' => ['sec_id'],
            default => [],
        };

        $this->resetContextoFormCascade(...$cascade);
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
        $docente = $this->findAccessibleDocenteOrFail($docenteId);

        $this->selectedDocenteId = $docenteId;
        $this->contextosExternos = [];
        $this->filtroPleCodigo = '';
        $this->showManualForm = true;
        $this->resetContextoForm();
        $this->resetValidation();

        if (filled($docente->documento)) {
            $this->loadContextosExternos($docente->documento);
        }
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

    protected function loadContextosExternos(string $documento): void
    {
        try {
            $service = app(AlumnoExternoService::class);

            $contextos = $service->contextosDocentePorDocumento($documento)
                ->sortByDesc('ple_id')
                ->values()
                ->all();

            // Enrich catMaterias with names for mi2_ids not yet loaded
            $mi2Ids = collect($contextos)->pluck('mi2_id')->filter()->unique()->values()->all();
            $missing = array_diff($mi2Ids, array_keys($this->catMaterias));

            if (! empty($missing)) {
                $this->catMaterias = array_merge($this->catMaterias, $service->catMateriasPorIds($missing));
            }

            $this->contextosExternos = $contextos;
            $this->showManualForm = empty($contextos);
        } catch (\Throwable) {
            $this->contextosExternos = [];
            $this->showManualForm = true;
        }
    }

    /**
     * Sedes available for the form (scoped for unit admins).
     *
     * @return array<int, string>
     */
    #[Computed]
    public function formSedes(): array
    {
        $sedes = $this->catSedes;

        if ($this->isScopedAcademicAdmin() && ! empty($this->allowedSedeIds)) {
            $sedes = array_intersect_key($sedes, array_flip($this->allowedSedeIds));
        }

        return $sedes;
    }

    /**
     * Carreras available for the selected sede (full external catalogue).
     *
     * @return array<int, string>
     */
    #[Computed]
    public function formCarreras(): array
    {
        $sedId = $this->normalizeNullableInt($this->contextoForm['sed_id'] ?? null);

        if ($sedId === null) {
            return $this->catCarreras;
        }

        try {
            return app(AlumnoExternoService::class)->catCarrerasPorSede($sedId);
        } catch (\Throwable) {
            return $this->catCarreras;
        }
    }

    /**
     * Materias available for the selected sede + carrera (full external catalogue).
     *
     * @return array<int, string>
     */
    #[Computed]
    public function formMaterias(): array
    {
        $sedId = $this->normalizeNullableInt($this->contextoForm['sed_id'] ?? null);
        $carId = $this->normalizeNullableInt($this->contextoForm['car_id'] ?? null);

        if ($sedId === null || $carId === null) {
            return $this->catMaterias;
        }

        try {
            return app(AlumnoExternoService::class)->catMateriasPorCarreraYSede($carId, $sedId);
        } catch (\Throwable) {
            return $this->catMaterias;
        }
    }

    /**
     * Periodos for selected sede + carrera (+ optional materia).
     *
     * @return array<int, string>
     */
    #[Computed]
    public function formPeriodos(): array
    {
        $sedId = $this->normalizeNullableInt($this->contextoForm['sed_id'] ?? null);
        $carId = $this->normalizeNullableInt($this->contextoForm['car_id'] ?? null);
        $mi2Id = $this->normalizeNullableInt($this->contextoForm['mi2_id'] ?? null);

        if ($sedId === null || $carId === null) {
            return $this->catPeriodos;
        }

        try {
            return app(AlumnoExternoService::class)->catPeriodosPorCarreraYSede($carId, $sedId, $mi2Id);
        } catch (\Throwable) {
            return $this->catPeriodos;
        }
    }

    /**
     * Turnos for selected sede + carrera (+ optional materia, periodo).
     *
     * @return array<int, string>
     */
    #[Computed]
    public function formTurnos(): array
    {
        $sedId = $this->normalizeNullableInt($this->contextoForm['sed_id'] ?? null);
        $carId = $this->normalizeNullableInt($this->contextoForm['car_id'] ?? null);
        $mi2Id = $this->normalizeNullableInt($this->contextoForm['mi2_id'] ?? null);
        $pleId = $this->normalizeNullableInt($this->contextoForm['ple_id'] ?? null);

        if ($sedId === null || $carId === null) {
            return $this->catTurnos;
        }

        try {
            return app(AlumnoExternoService::class)->catTurnosPorCarreraYSede($carId, $sedId, $mi2Id, $pleId);
        } catch (\Throwable) {
            return $this->catTurnos;
        }
    }

    /**
     * Secciones for selected sede + carrera (+ optional materia, periodo, turno).
     *
     * @return array<int, string>
     */
    #[Computed]
    public function formSecciones(): array
    {
        $sedId = $this->normalizeNullableInt($this->contextoForm['sed_id'] ?? null);
        $carId = $this->normalizeNullableInt($this->contextoForm['car_id'] ?? null);
        $mi2Id = $this->normalizeNullableInt($this->contextoForm['mi2_id'] ?? null);
        $pleId = $this->normalizeNullableInt($this->contextoForm['ple_id'] ?? null);
        $turId = $this->normalizeNullableInt($this->contextoForm['tur_id'] ?? null);

        if ($sedId === null || $carId === null) {
            return $this->catSecciones;
        }

        try {
            return app(AlumnoExternoService::class)->catSeccionesPorCarreraYSede($carId, $sedId, $mi2Id, $pleId, $turId);
        } catch (\Throwable) {
            return $this->catSecciones;
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

    protected function resetContextoFormCascade(string ...$fields): void
    {
        foreach ($fields as $field) {
            $this->contextoForm[$field] = '';
        }
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

    public function importarContextoExterno(int $index): void
    {
        if (! $this->schemaReady || ! $this->selectedDocenteId) {
            return;
        }

        if (! isset($this->contextosExternos[$index])) {
            return;
        }

        $ctx = $this->contextosExternos[$index];

        if ($this->isScopedAcademicAdmin() && ! $this->canManageSede($ctx['sed_id'])) {
            $this->addError("importar_{$index}", 'Este contexto pertenece a una sede fuera de tu scope.');

            return;
        }

        DocenteContexto::firstOrCreate(
            [
                'docente_id' => $this->selectedDocenteId,
                'car_id' => $ctx['car_id'],
                'sed_id' => $ctx['sed_id'],
                'ple_id' => $ctx['ple_id'],
                'mi2_id' => $ctx['mi2_id'],
                'tur_id' => $ctx['tur_id'],
                'sec_id' => $ctx['sec_id'],
            ],
            ['activo' => true],
        );

        $this->loadDocentes();
        session()->flash('status', 'Contexto importado correctamente.');
    }

    public function toggleManualForm(): void
    {
        $this->showManualForm = ! $this->showManualForm;
    }

    public function sincronizarContextosDocente(int $docenteId): void
    {
        if (! $this->schemaReady) {
            return;
        }

        $docente = $this->findAccessibleDocenteOrFail($docenteId);

        if (blank($docente->documento)) {
            $this->addError("sync_{$docenteId}", 'El docente no tiene documento registrado para consultar el sistema externo.');

            return;
        }

        try {
            $contextos = app(AlumnoExternoService::class)->contextosDocentePorDocumento($docente->documento);
        } catch (\Throwable) {
            $this->addError("sync_{$docenteId}", 'No se pudo conectar al sistema externo.');

            return;
        }

        if ($contextos->isEmpty()) {
            session()->flash('status', "Sin datos externos para {$docente->nombre}. No se encontraron asignaciones en el sistema.");

            return;
        }

        $created = 0;

        foreach ($contextos as $ctx) {
            $contexto = DocenteContexto::firstOrCreate(
                [
                    'docente_id' => $docente->id,
                    'car_id' => $ctx['car_id'],
                    'sed_id' => $ctx['sed_id'],
                    'ple_id' => $ctx['ple_id'],
                    'mi2_id' => $ctx['mi2_id'],
                    'tur_id' => $ctx['tur_id'],
                    'sec_id' => $ctx['sec_id'],
                ],
                ['activo' => true],
            );

            if ($contexto->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->loadDocentes();

        $total = $contextos->count();
        $skipped = $total - $created;
        $msg = "Sincronizacion completa: {$created} contexto(s) nuevo(s) importado(s)";

        if ($skipped > 0) {
            $msg .= ", {$skipped} ya existian.";
        }

        session()->flash('status', $msg);
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
                        {{-- Docente info --}}
                        <div class="rounded-2xl border border-base-300 bg-base-200/40 p-4">
                            <p class="text-sm font-semibold text-base-content">{{ $this->selectedDocente->nombre }}</p>
                            <p class="text-sm text-base-content/65">
                                Documento: {{ $this->selectedDocente->documento ?? 'Sin dato' }}
                                · Contextos cargados: {{ $this->selectedDocente->contextos_count }}
                            </p>
                        </div>

                        {{-- External assignments from legacy --}}
                        @if (! empty($contextosExternos))
                            <div class="space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-base-content">Asignaciones en el sistema externo</p>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <select wire:model.live="filtroPleCodigo" class="select select-bordered select-sm">
                                            <option value="">Todos los períodos</option>
                                            @foreach (collect($contextosExternos)->pluck('ple_id')->filter()->unique()->sortDesc()->values() as $pleId)
                                                <option value="{{ $pleId }}">{{ $catPeriodos[$pleId] ?? "Período {$pleId}" }}</option>
                                            @endforeach
                                        </select>

                                        <button
                                            type="button"
                                            wire:click="sincronizarContextosDocente({{ $this->selectedDocente->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="sincronizarContextosDocente({{ $this->selectedDocente->id }})"
                                            class="btn btn-secondary btn-sm"
                                        >
                                            <span wire:loading.remove wire:target="sincronizarContextosDocente({{ $this->selectedDocente->id }})">Importar todos</span>
                                            <span wire:loading wire:target="sincronizarContextosDocente({{ $this->selectedDocente->id }})" class="loading loading-spinner loading-xs"></span>
                                        </button>
                                    </div>
                                </div>

                                @php
                                    $importedFingerprints = ($this->selectedDocente->contextos ?? collect())
                                        ->map(fn ($c) => "{$c->car_id}|{$c->sed_id}|{$c->ple_id}|{$c->mi2_id}|{$c->tur_id}|{$c->sec_id}")
                                        ->all();
                                    $filteredContextos = collect($contextosExternos)
                                        ->when(
                                            $filtroPleCodigo !== '',
                                            fn ($col) => $col->filter(fn ($c) => (string) ($c['ple_id'] ?? '') === $filtroPleCodigo),
                                        )
                                        ->all();
                                @endphp

                                <div class="overflow-x-auto rounded-2xl border border-base-300 bg-base-100/70">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Materia</th>
                                                <th>Período</th>
                                                <th>Sede · Turno · Sección</th>
                                                <th class="text-right">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($filteredContextos as $idx => $ctx)
                                                @php
                                                    $fingerprint = "{$ctx['car_id']}|{$ctx['sed_id']}|{$ctx['ple_id']}|{$ctx['mi2_id']}|{$ctx['tur_id']}|{$ctx['sec_id']}";
                                                    $yaImportado = in_array($fingerprint, $importedFingerprints);
                                                @endphp
                                                <tr wire:key="extctx-{{ $idx }}" @class(['opacity-50' => $yaImportado])>
                                                    <td class="text-sm">
                                                        <p class="font-medium">{{ $catMaterias[$ctx['mi2_id'] ?? 0] ?? ($ctx['mi2_id'] ? "ID {$ctx['mi2_id']}" : '—') }}</p>
                                                        @if ($ctx['car_id'])
                                                            <p class="text-xs text-base-content/55">{{ $catCarreras[$ctx['car_id']] ?? "Carrera {$ctx['car_id']}" }}</p>
                                                        @endif
                                                    </td>
                                                    <td class="whitespace-nowrap text-sm text-base-content/70">
                                                        {{ $ctx['ple_id'] ? ($catPeriodos[$ctx['ple_id']] ?? "ID {$ctx['ple_id']}") : '—' }}
                                                    </td>
                                                    <td class="text-sm text-base-content/70">
                                                        <p>{{ $ctx['sed_id'] ? ($catSedes[$ctx['sed_id']] ?? "Sede {$ctx['sed_id']}") : '—' }}</p>
                                                        @if ($ctx['tur_id'] || $ctx['sec_id'])
                                                            <p class="text-xs">
                                                                {{ $ctx['tur_id'] ? ($catTurnos[$ctx['tur_id']] ?? "Turno {$ctx['tur_id']}") : '' }}{{ ($ctx['tur_id'] && $ctx['sec_id']) ? ' · ' : '' }}{{ $ctx['sec_id'] ? ($catSecciones[$ctx['sec_id']] ?? "Sección {$ctx['sec_id']}") : '' }}
                                                            </p>
                                                        @endif
                                                    </td>
                                                    <td class="text-right">
                                                        @if ($yaImportado)
                                                            <span class="badge badge-success badge-sm">✓ Importado</span>
                                                        @else
                                                            <button
                                                                type="button"
                                                                wire:click="importarContextoExterno({{ $idx }})"
                                                                wire:loading.attr="disabled"
                                                                wire:target="importarContextoExterno({{ $idx }})"
                                                                class="btn btn-primary btn-xs"
                                                            >
                                                                <span wire:loading.remove wire:target="importarContextoExterno({{ $idx }})">Importar</span>
                                                                <span wire:loading wire:target="importarContextoExterno({{ $idx }})" class="loading loading-spinner loading-xs"></span>
                                                            </button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="py-3 text-center text-sm text-base-content/55">Sin resultados para el período seleccionado.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @elseif (filled($this->selectedDocente->documento))
                            <x-mary-alert title="No se encontraron asignaciones en el sistema externo para este docente." icon="o-information-circle" class="alert-info" />
                        @endif

                        {{-- Manual form toggle --}}
                        <div class="border-t border-base-300 pt-2">
                            <button
                                type="button"
                                wire:click="toggleManualForm"
                                class="flex w-full items-center justify-between rounded-xl px-1 py-2 text-sm font-medium text-base-content/65 hover:text-base-content"
                            >
                                <span>Agregar contexto manualmente</span>
                                <svg xmlns="http://www.w3.org/2000/svg" @class(['size-4 transition-transform duration-150', 'rotate-180' => $showManualForm]) fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>

                        @if ($showManualForm)
                            <form wire:submit="saveContexto" class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    {{-- 1. Sede (root of cascade) --}}
                                    <label class="form-control w-full">
                                        <span class="label-text text-sm font-medium">Sede</span>
                                        <select wire:change="onContextoFieldChanged('sed_id', $event.target.value)" class="select select-bordered w-full">
                                            <option value="">— Cualquier sede —</option>
                                            @foreach ($this->formSedes as $id => $nombre)
                                                <option value="{{ $id }}" @selected((string) ($contextoForm['sed_id'] ?? '') === (string) $id)>{{ $nombre }}</option>
                                            @endforeach
                                        </select>
                                        @error('contextoForm.sed_id')
                                            <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    {{-- 2. Carrera → filtered by sede --}}
                                    <label class="form-control w-full">
                                        <span class="label-text text-sm font-medium">Carrera</span>
                                        <select wire:change="onContextoFieldChanged('car_id', $event.target.value)" class="select select-bordered w-full">
                                            <option value="">— Cualquier carrera —</option>
                                            @foreach ($this->formCarreras as $id => $nombre)
                                                <option value="{{ $id }}" @selected((string) ($contextoForm['car_id'] ?? '') === (string) $id)>{{ $nombre }}</option>
                                            @endforeach
                                        </select>
                                        @error('contextoForm.car_id')
                                            <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    {{-- 3. Materia → filtered by sede + carrera --}}
                                    <label class="form-control w-full">
                                        <span class="label-text text-sm font-medium">Materia</span>
                                        @if (! empty($this->formMaterias))
                                            <select wire:change="onContextoFieldChanged('mi2_id', $event.target.value)" class="select select-bordered w-full">
                                                <option value="">— Cualquier materia —</option>
                                                @foreach ($this->formMaterias as $id => $nombre)
                                                    <option value="{{ $id }}" @selected((string) ($contextoForm['mi2_id'] ?? '') === (string) $id)>{{ $nombre }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input wire:model="contextoForm.mi2_id" type="number" min="1" class="input input-bordered w-full" placeholder="ID de materia" />
                                        @endif
                                        @error('contextoForm.mi2_id')
                                            <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    {{-- 4. Período → filtered by sede + carrera + materia --}}
                                    <label class="form-control w-full">
                                        <span class="label-text text-sm font-medium">Período lectivo</span>
                                        <select wire:change="onContextoFieldChanged('ple_id', $event.target.value)" class="select select-bordered w-full">
                                            <option value="">— Cualquier período —</option>
                                            @foreach ($this->formPeriodos as $id => $nombre)
                                                <option value="{{ $id }}" @selected((string) ($contextoForm['ple_id'] ?? '') === (string) $id)>{{ $nombre }}</option>
                                            @endforeach
                                        </select>
                                        @error('contextoForm.ple_id')
                                            <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    {{-- 5. Turno → filtered by all above --}}
                                    <label class="form-control w-full">
                                        <span class="label-text text-sm font-medium">Turno</span>
                                        <select wire:change="onContextoFieldChanged('tur_id', $event.target.value)" class="select select-bordered w-full">
                                            <option value="">— Cualquier turno —</option>
                                            @foreach ($this->formTurnos as $id => $nombre)
                                                <option value="{{ $id }}" @selected((string) ($contextoForm['tur_id'] ?? '') === (string) $id)>{{ $nombre }}</option>
                                            @endforeach
                                        </select>
                                        @error('contextoForm.tur_id')
                                            <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    {{-- 6. Sección → filtered by all above --}}
                                    <label class="form-control w-full">
                                        <span class="label-text text-sm font-medium">Sección</span>
                                        <select wire:change="onContextoFieldChanged('sec_id', $event.target.value)" class="select select-bordered w-full">
                                            <option value="">— Cualquier sección —</option>
                                            @foreach ($this->formSecciones as $id => $nombre)
                                                <option value="{{ $id }}" @selected((string) ($contextoForm['sec_id'] ?? '') === (string) $id)>{{ $nombre }}</option>
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
                                    @if (filled($docente->documento))
                                        <button
                                            type="button"
                                            wire:click="sincronizarContextosDocente({{ $docente->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="sincronizarContextosDocente({{ $docente->id }})"
                                            class="btn btn-secondary btn-sm"
                                            title="Importar contextos desde el sistema externo"
                                        >
                                            <span wire:loading.remove wire:target="sincronizarContextosDocente({{ $docente->id }})">Sincronizar</span>
                                            <span wire:loading wire:target="sincronizarContextosDocente({{ $docente->id }})" class="loading loading-spinner loading-xs"></span>
                                        </button>
                                    @endif
                                </div>

                                @error("sync_{$docente->id}")
                                    <p class="text-sm font-medium text-error">{{ $message }}</p>
                                @enderror

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
