<?php

use App\Enums\RoleName;
use App\Models\Docente;
use App\Models\DocenteContexto;
use App\Models\User;
use App\Services\AlumnoExternoService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $selectedDocenteId = null;

    /** @var array<int, int> */
    public array $allowedSedeIds = [];

    public array $contextoForm = [];

    /** @var array<int, string> */
    public array $catCarreras = [];

    /** @var array<int, string> */
    public array $catSedes = [];

    /** @var array<int, string> */
    public array $catPeriodos = [];

    /** @var array<int, string> */
    public array $catPeriodosEvaluacion = [];

    /** @var array<int, string> */
    public array $catTurnos = [];

    /** @var array<int, string> */
    public array $catSecciones = [];

    /** @var array<int, string> */
    public array $catMaterias = [];

    /**
     * Contextos del docente en el sistema externo (BD legacy).
     *
     * @var array<int, array{car_id: int|null, sed_id: int|null, ple_id: int|null, mi2_id: int|null, tur_id: int|null, sec_id: int|null}>
     */
    public array $contextosExternos = [];

    public string $filtroPleCodigo = '';

    /** @var array<int, bool> */
    public array $selectedExternos = [];

    public ?string $contextoGuardado = null;

    public ?Docente $docente = null;

    public bool $schemaReady = true;

    public function mount(?int $selectedDocenteId = null, array $allowedSedeIds = []): void
    {
        $this->selectedDocenteId = $selectedDocenteId;
        $this->allowedSedeIds = $allowedSedeIds;
        $this->resetContextoForm();

        $this->schemaReady = Schema::hasTable('docentes') && Schema::hasTable('docente_contextos');

        if (! $this->schemaReady) {
            return;
        }

        $this->loadCatalogs();

        if ($this->selectedDocenteId) {
            $this->loadDocente();
        }
    }

    public function updatedSelectedDocenteId(): void
    {
        $this->contextosExternos = [];
        $this->filtroPleCodigo = '';
        $this->selectedExternos = [];
        $this->contextoGuardado = null;
        $this->docente = null;
        $this->resetContextoForm();

        if ($this->selectedDocenteId) {
            $this->loadDocente();
        }
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
        $payload = $this->buildContextoPayload($validated);

        if ($payload === null) {
            return;
        }

        if (! $this->persistContexto($payload)) {
            return;
        }

        $this->contextoGuardado = $this->describeContexto($payload);
        $this->resetContextoForm();
        $this->loadDocente();
        $this->resetValidation();
        $this->dispatch('contextos-updated');
    }

    public function saveContextoYContinuar(): void
    {
        if (! $this->schemaReady) {
            return;
        }

        if (! $this->selectedDocenteId) {
            $this->addError('contexto', 'Seleccioná primero un docente para asignar el contexto.');

            return;
        }

        $validated = $this->validate($this->contextoRules());
        $payload = $this->buildContextoPayload($validated);

        if ($payload === null) {
            return;
        }

        if (! $this->persistContexto($payload)) {
            return;
        }

        $this->resetContextoFormCascade('tur_id', 'sec_id');
        $this->loadDocente();
        $this->resetValidation();
        $this->contextoGuardado = $this->describeContexto($payload).' — seguí cargando.';
        $this->dispatch('contextos-updated');
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

        $this->loadDocente();
        $this->contextoGuardado = null;
        $this->dispatch('contextos-updated');
        session()->flash('status', 'Contexto eliminado correctamente.');
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

        $this->loadDocente();
        $this->contextoGuardado = null;
        $this->dispatch('contextos-updated');
        session()->flash('status', 'Contexto importado correctamente.');
    }

    public function importarContextosSeleccionados(): void
    {
        if (! $this->schemaReady || ! $this->selectedDocenteId || empty($this->selectedExternos)) {
            return;
        }

        $imported = 0;

        foreach ($this->selectedExternos as $index => $checked) {
            if (! $checked || ! isset($this->contextosExternos[$index])) {
                continue;
            }

            $ctx = $this->contextosExternos[$index];

            if ($this->isScopedAcademicAdmin() && ! $this->canManageSede($ctx['sed_id'])) {
                continue;
            }

            $contexto = DocenteContexto::firstOrCreate(
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

            if ($contexto->wasRecentlyCreated) {
                $imported++;
            }
        }

        $this->selectedExternos = [];
        $this->loadDocente();
        $this->contextoGuardado = null;
        $this->dispatch('contextos-updated');
        session()->flash('status', "{$imported} contexto(s) importado(s).");
    }

    public function sincronizarContextosDocente(): void
    {
        if (! $this->schemaReady || ! $this->selectedDocenteId) {
            return;
        }

        $docente = $this->docente ?? Docente::query()->find($this->selectedDocenteId);

        if (! $docente) {
            return;
        }

        if (blank($docente->documento)) {
            $this->addError('sync', 'El docente no tiene documento registrado para consultar el sistema externo.');

            return;
        }

        try {
            $contextos = app(AlumnoExternoService::class)->contextosDocentePorDocumento($docente->documento);
        } catch (\Throwable) {
            $this->addError('sync', 'No se pudo conectar al sistema externo.');

            return;
        }

        if ($contextos->isEmpty()) {
            session()->flash('status', "Sin datos externos para {$docente->nombre}. No se encontraron asignaciones en el sistema.");

            return;
        }

        // Load materia names from any mi2_ids in the external data
        $mi2Ids = collect($contextos)->pluck('mi2_id')->filter()->unique()->all();
        $missing = array_diff($mi2Ids, array_keys($this->catMaterias));

        if (! empty($missing)) {
            try {
                $this->catMaterias = array_merge($this->catMaterias, app(AlumnoExternoService::class)->catMateriasPorIds($missing));
            } catch (\Throwable) {
                // Keep catalogs as-is
            }
        }

        // Store unsorted external contextos for display
        $this->contextosExternos = $contextos
            ->sortByDesc('ple_id')
            ->values()
            ->all();

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

        $this->loadDocente();
        $this->dispatch('contextos-updated');

        $total = $contextos->count();
        $skipped = $total - $created;
        $msg = "Sincronización completa: {$created} contexto(s) nuevo(s) importado(s)";

        if ($skipped > 0) {
            $msg .= ", {$skipped} ya existían.";
        }

        session()->flash('status', $msg);
    }

    protected function persistContexto(array $payload): bool
    {
        try {
            DocenteContexto::query()->create([
                'docente_id' => $this->selectedDocenteId,
                ...$payload,
            ]);

            return true;
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                $this->addError('contexto', 'Ya existe un contexto idéntico para este docente.');

                return false;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>|null
     */
    protected function buildContextoPayload(array $validated): ?array
    {
        $payload = [
            'car_id' => $this->normalizeNullableInt($validated['contextoForm']['car_id'] ?? null),
            'sed_id' => $this->normalizeNullableInt($validated['contextoForm']['sed_id'] ?? null),
            'ple_id' => $this->normalizeNullableInt($validated['contextoForm']['ple_id'] ?? null),
            'periodo_evaluacion_id' => $this->normalizeNullableInt($validated['contextoForm']['periodo_evaluacion_id'] ?? null),
            'mi2_id' => $this->normalizeNullableInt($validated['contextoForm']['mi2_id'] ?? null),
            'tur_id' => $this->normalizeNullableInt($validated['contextoForm']['tur_id'] ?? null),
            'sec_id' => $this->normalizeNullableInt($validated['contextoForm']['sec_id'] ?? null),
            'activo' => (bool) ($validated['contextoForm']['activo'] ?? false),
        ];

        if ($this->isScopedAcademicAdmin() && ! $this->canManageSede($payload['sed_id'])) {
            $this->addError('contextoForm.sed_id', 'Solo podés asignar contextos dentro de tus sedes habilitadas.');

            return null;
        }

        $hasAtLeastOneScope = collect($payload)
            ->except('activo')
            ->contains(fn (mixed $value): bool => $value !== null);

        if (! $hasAtLeastOneScope) {
            $this->addError('contexto', 'Debes cargar al menos un identificador de contexto para el docente.');

            return null;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function describeContexto(array $payload): string
    {
        $parts = [];

        if ($payload['sed_id']) {
            $parts[] = $this->catSedes[$payload['sed_id']] ?? "Sede {$payload['sed_id']}";
        }

        if ($payload['car_id']) {
            $parts[] = $this->catCarreras[$payload['car_id']] ?? "Carrera {$payload['car_id']}";
        }

        if ($payload['mi2_id']) {
            $parts[] = $this->catMaterias[$payload['mi2_id']] ?? "Materia {$payload['mi2_id']}";
        }

        if ($payload['ple_id']) {
            $parts[] = $this->catPeriodos[$payload['ple_id']] ?? "Período {$payload['ple_id']}";
        }

        if ($payload['periodo_evaluacion_id']) {
            $parts[] = $this->catPeriodosEvaluacion[$payload['periodo_evaluacion_id']] ?? "Eval #{$payload['periodo_evaluacion_id']}";
        }

        return implode(' · ', $parts) ?: 'Contexto genérico';
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

        $this->catPeriodosEvaluacion = \App\Models\PeriodoEvaluacion::query()
            ->orderByDesc('fecha_inicio')
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => "{$p->nombre} ({$p->fecha_inicio->format('d/m/Y')} — {$p->fecha_fin->format('d/m/Y')})"])
            ->all();
    }

    protected function loadDocente(): void
    {
        $this->docente = Docente::query()
            ->with([
                'contextos' => fn ($q) => $q->orderByDesc('activo')->orderBy('id'),
            ])
            ->withCount(['contextos'])
            ->find($this->selectedDocenteId);

        // Pre-load materia names from existing contexts so the form dropdown
        // shows real names even before the operator syncs external assignments.
        $this->resolveMiMaterias();
    }

    /**
     * Sedes disponibles para el formulario (con scope para admin de unidad).
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
     * Carreras disponibles según la sede seleccionada.
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
     * Materias disponibles según sede + carrera.
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
     * Períodos disponibles según sede + carrera (+ materia opcional).
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
     * Turnos disponibles según sede + carrera (+ materia, período opcionales).
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
     * Secciones disponibles según sede + carrera (+ materia, período, turno opcionales).
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
            'contextoForm.periodo_evaluacion_id' => ['nullable', 'integer', 'min:1', Rule::in(array_keys($this->catPeriodosEvaluacion))],
            'contextoForm.mi2_id' => ['nullable', 'integer', 'min:1'],
            'contextoForm.tur_id' => ['nullable', 'integer', 'min:1'],
            'contextoForm.sec_id' => ['nullable', 'integer', 'min:1'],
            'contextoForm.activo' => ['boolean'],
        ];
    }

    protected function resetContextoForm(): void
    {
        $this->contextoForm = [
            'car_id' => '',
            'sed_id' => '',
            'ple_id' => '',
            'periodo_evaluacion_id' => '',
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

    protected function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
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

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array($exception->errorInfo[0] ?? null, ['23000', '23505'], true);
    }

    protected function resolveMiMaterias(): void
    {
        $contextos = $this->docente?->contextos ?? collect();

        $mi2Ids = $contextos
            ->pluck('mi2_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($mi2Ids)) {
            return;
        }

        $missing = array_diff($mi2Ids, array_keys($this->catMaterias));

        if (empty($missing)) {
            return;
        }

        try {
            $this->catMaterias = array_merge($this->catMaterias, app(AlumnoExternoService::class)->catMateriasPorIds($missing));
        } catch (\Throwable) {
            // Catálogo de materias queda incompleto pero funcional
        }
    }
}; ?>

<div
    x-data="{ clearing: false }"
    @contexto-saved.window="
        if ($event.detail?.docenteId == {{ $selectedDocenteId ?? 0 }}) {
            clearing = false;
        }
    "
>
    @if (! $schemaReady)
        <x-mary-alert title="Las tablas de contexto no están disponibles." icon="o-exclamation-triangle" class="alert-warning" />
    @elseif (! $docente)
        <x-mary-alert title="Seleccioná un docente desde el listado para gestionar sus contextos." icon="o-information-circle" class="alert-info" />
    @else
        {{-- Docente info --}}
        <div class="rounded-2xl border border-base-300 bg-base-200/40 p-4">
            <p class="text-sm font-semibold text-base-content">{{ $docente->nombre }}</p>
            <p class="text-sm text-base-content/65">
                Documento: {{ $docente->documento ?? 'Sin dato' }}
                · Contextos cargados: {{ $docente->contextos_count }}
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
                            wire:click="sincronizarContextosDocente"
                            wire:loading.attr="disabled"
                            wire:target="sincronizarContextosDocente"
                            class="btn btn-secondary btn-sm"
                        >
                            <span wire:loading.remove wire:target="sincronizarContextosDocente">Importar todos</span>
                            <span wire:loading wire:target="sincronizarContextosDocente" class="loading loading-spinner loading-xs"></span>
                        </button>
                    </div>
                </div>

                @php
                    $importedFingerprints = ($docente->contextos ?? collect())
                        ->map(fn ($c) => "{$c->car_id}|{$c->sed_id}|{$c->ple_id}|{$c->mi2_id}|{$c->tur_id}|{$c->sec_id}")
                        ->all();
                    $filteredContextos = collect($contextosExternos)
                        ->when(
                            $filtroPleCodigo !== '',
                            fn ($col) => $col->filter(fn ($c) => (string) ($c['ple_id'] ?? '') === $filtroPleCodigo),
                        )
                        ->all();
                    $pendingCount = 0;
                    foreach ($filteredContextos as $idx => $ctx) {
                        $fp = "{$ctx['car_id']}|{$ctx['sed_id']}|{$ctx['ple_id']}|{$ctx['mi2_id']}|{$ctx['tur_id']}|{$ctx['sec_id']}";
                        if (! in_array($fp, $importedFingerprints)) {
                            $pendingCount++;
                        }
                    }
                @endphp

                @if ($pendingCount > 1)
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="importarContextosSeleccionados"
                            wire:loading.attr="disabled"
                            wire:target="importarContextosSeleccionados"
                            class="btn btn-primary btn-sm"
                        >
                            <span wire:loading.remove wire:target="importarContextosSeleccionados">Importar seleccionados</span>
                            <span wire:loading wire:target="importarContextosSeleccionados" class="loading loading-spinner loading-xs"></span>
                        </button>
                        <span class="text-xs text-base-content/50">Usá los checkboxes para seleccionar</span>
                    </div>
                @endif

                <div class="overflow-x-auto rounded-2xl border border-base-300 bg-base-100/70">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th class="w-8"></th>
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
                                    <td>
                                        @if (! $yaImportado)
                                            <input
                                                type="checkbox"
                                                wire:model="selectedExternos.{{ $idx }}"
                                                value="1"
                                                class="checkbox checkbox-primary checkbox-xs"
                                            />
                                        @endif
                                    </td>
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
                                    <td colspan="5" class="py-3 text-center text-sm text-base-content/55">Sin resultados para el período seleccionado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif (filled($docente->documento))
            <div class="space-y-3">
                <x-mary-alert title="Hacé clic en "Importar todos" para traer las asignaciones del sistema externo." icon="o-information-circle" class="alert-info" />
            </div>
        @endif

        {{-- Manual context form — always visible when a docente is selected --}}
        @if ($contextoGuardado)
            <div class="rounded-2xl border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
                <span class="font-medium">✓ Agregado:</span> {{ $contextoGuardado }}
            </div>
        @endif

        <div class="border-t border-base-300 pt-2">
            <p class="text-sm font-medium text-base-content/65 px-1 py-1">Agregar contexto manualmente</p>
        </div>

        <form wire:submit="saveContexto" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {{-- 1. Sede (root of cascade) --}}
                <label class="form-control w-full">
                    <span class="label-text text-sm font-medium">Sede</span>
                    <select
                        wire:model.live="contextoForm.sed_id"
                        x-on:change="
                            $wire.contextoForm.car_id = '';
                            $wire.contextoForm.mi2_id = '';
                            $wire.contextoForm.ple_id = '';
                            $wire.contextoForm.tur_id = '';
                            $wire.contextoForm.sec_id = '';
                        "
                        class="select select-bordered w-full"
                    >
                        <option value="">— Cualquier sede —</option>
                        @foreach ($this->formSedes as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('contextoForm.sed_id')
                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                    @enderror
                </label>

                {{-- 2. Carrera → filtered by sede --}}
                <label class="form-control w-full">
                    <span class="label-text text-sm font-medium">Carrera</span>
                    <select
                        wire:model.live="contextoForm.car_id"
                        x-on:change="
                            $wire.contextoForm.mi2_id = '';
                            $wire.contextoForm.ple_id = '';
                            $wire.contextoForm.tur_id = '';
                            $wire.contextoForm.sec_id = '';
                        "
                        class="select select-bordered w-full"
                    >
                        <option value="">— Cualquier carrera —</option>
                        @foreach ($this->formCarreras as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
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
                        <select
                            wire:model.live="contextoForm.mi2_id"
                            x-on:change="
                                $wire.contextoForm.ple_id = '';
                                $wire.contextoForm.tur_id = '';
                                $wire.contextoForm.sec_id = '';
                            "
                            class="select select-bordered w-full"
                        >
                            <option value="">— Cualquier materia —</option>
                            @foreach ($this->formMaterias as $id => $nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </select>
                    @elseif ($contextoForm['sed_id'] && $contextoForm['car_id'])
                        {{-- Sede + carrera seleccionados pero catálogo vacío (probablemente sin acceso a BD externa) --}}
                        <input wire:model="contextoForm.mi2_id" type="number" min="1" class="input input-bordered w-full" placeholder="ID de materia" />
                    @else
                        <select class="select select-bordered w-full" disabled>
                            <option value="">Seleccioná sede y carrera primero</option>
                        </select>
                    @endif
                    @error('contextoForm.mi2_id')
                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                    @enderror
                </label>

                {{-- 4. Período → filtered by sede + carrera + materia --}}
                <label class="form-control w-full">
                    <span class="label-text text-sm font-medium">Período lectivo</span>
                    <select
                        wire:model.live="contextoForm.ple_id"
                        x-on:change="
                            $wire.contextoForm.tur_id = '';
                            $wire.contextoForm.sec_id = '';
                        "
                        class="select select-bordered w-full"
                    >
                        <option value="">— Cualquier período —</option>
                        @foreach ($this->formPeriodos as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('contextoForm.ple_id')
                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                    @enderror
                </label>

                {{-- 5. Periodo de evaluación --}}
                <label class="form-control w-full">
                    <span class="label-text text-sm font-medium">Período de evaluación</span>
                    <select wire:model.live="contextoForm.periodo_evaluacion_id" class="select select-bordered w-full">
                        <option value="">— Sin período asignado —</option>
                        @foreach ($this->catPeriodosEvaluacion as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('contextoForm.periodo_evaluacion_id')
                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                    @enderror
                </label>

                {{-- 6. Turno → filtered by all above --}}
                <label class="form-control w-full">
                    <span class="label-text text-sm font-medium">Turno</span>
                    <select
                        wire:model.live="contextoForm.tur_id"
                        x-on:change="$wire.contextoForm.sec_id = ''"
                        class="select select-bordered w-full"
                    >
                        <option value="">— Cualquier turno —</option>
                        @foreach ($this->formTurnos as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('contextoForm.tur_id')
                        <span class="mt-1 text-sm font-medium text-error">{{ $message }}</span>
                    @enderror
                </label>

                {{-- 6. Sección → filtered by all above --}}
                <label class="form-control w-full">
                    <span class="label-text text-sm font-medium">Sección</span>
                    <select wire:model.live="contextoForm.sec_id" class="select select-bordered w-full">
                        <option value="">— Cualquier sección —</option>
                        @foreach ($this->formSecciones as $id => $nombre)
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

            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    wire:click="saveContextoYContinuar"
                    wire:loading.attr="disabled"
                    wire:target="saveContexto, saveContextoYContinuar"
                    class="btn btn-ghost"
                >
                    <span wire:loading.remove wire:target="saveContexto, saveContextoYContinuar">Guardar y agregar otro</span>
                    <span wire:loading wire:target="saveContexto, saveContextoYContinuar" class="loading loading-spinner loading-sm"></span>
                </button>
                <button type="submit" class="btn btn-primary min-w-48">
                    <span wire:loading.remove wire:target="saveContexto, saveContextoYContinuar">Agregar contexto</span>
                    <span wire:loading wire:target="saveContexto, saveContextoYContinuar" class="loading loading-spinner loading-sm"></span>
                </button>
            </div>
        </form>

        {{-- Synced contexts list --}}
        @if ($docente->contextos->isNotEmpty())
            <div class="border-t border-base-300 pt-2">
                <p class="text-sm font-medium text-base-content/65 px-1 py-1">Contextos cargados</p>
            </div>

            @php $this->resolveMiMaterias(); @endphp

            <div class="overflow-x-auto rounded-2xl border border-base-300 bg-base-100/70">
                <table class="table table-xs">
                    <thead>
                        <tr>
                            <th class="text-xs">Carrera</th>
                            <th class="text-xs">Sede</th>
                            <th class="text-xs">Período</th>
                            <th class="text-xs">Materia</th>
                            <th class="text-xs">Turno</th>
                            <th class="text-xs">Sección</th>
                            <th class="text-xs text-center">Estado</th>
                            <th class="text-xs text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($docente->contextos as $contexto)
                            <tr wire:key="contexto-{{ $contexto->id }}">
                                <td class="text-xs text-base-content/80">{{ $contexto->car_id ? ($catCarreras[$contexto->car_id] ?? "ID {$contexto->car_id}") : '—' }}</td>
                                <td class="text-xs text-base-content/80">{{ $contexto->sed_id ? ($catSedes[$contexto->sed_id] ?? "ID {$contexto->sed_id}") : '—' }}</td>
                                <td class="text-xs text-base-content/80 whitespace-nowrap">{{ $contexto->ple_id ? ($catPeriodos[$contexto->ple_id] ?? "ID {$contexto->ple_id}") : '—' }}</td>
                                <td class="text-xs text-base-content/80">{{ $contexto->mi2_id ? ($catMaterias[$contexto->mi2_id] ?? "ID {$contexto->mi2_id}") : '—' }}</td>
                                <td class="text-xs text-base-content/80">{{ $contexto->tur_id ? ($catTurnos[$contexto->tur_id] ?? "ID {$contexto->tur_id}") : '—' }}</td>
                                <td class="text-xs text-base-content/80">{{ $contexto->sec_id ? ($catSecciones[$contexto->sec_id] ?? "ID {$contexto->sec_id}") : '—' }}</td>
                                <td class="text-center">
                                    <span @class([
                                        'badge badge-xs',
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
    @endif
</div>