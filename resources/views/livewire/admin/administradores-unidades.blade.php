<?php

use App\Enums\RoleName;
use App\Models\AcademicUnit;
use App\Models\User;
use App\Models\UserAcademicUnitScope;
use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Collection $admins;

    public Collection $academicUnits;

    public string $search = '';

    public string $filterAcademicUnit = '';

    /** @var array<int, array<int|string, bool>> */
    public array $selectedAcademicUnitsByUser = [];

    /** @var array<int, array<int, int>> */
    public array $selectedSedesByUser = [];

    /** @var array<int, array<int|string, bool>> */
    public array $originalAcademicUnitsByUser = [];

    /** @var array<int, string> */
    public array $sedesMap = [];

    public function mount(): void
    {
        $this->sedesMap = app(AlumnoExternoService::class)->catSedes();
        $this->loadAcademicUnits();
        $this->loadAdmins();
    }

    public function updatedSearch(): void
    {
        $this->loadAdmins();
    }

    public function updatedFilterAcademicUnit(): void
    {
        $this->loadAdmins();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterAcademicUnit = '';

        $this->loadAdmins();
    }

    public function saveScopes(int $userId): void
    {
        /** @var User $user */
        $user = User::role(RoleName::AdminUnidadAcademica->value)->findOrFail($userId);

        $selectedAcademicUnitIds = collect($this->selectedAcademicUnitsByUser[$userId] ?? [])
            ->filter(static fn (bool $checked): bool => $checked)
            ->keys()
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        validator(
            ['academic_unit_ids' => $selectedAcademicUnitIds],
            [
                'academic_unit_ids' => ['array'],
                'academic_unit_ids.*' => ['integer', Rule::exists('academic_units', 'id')],
            ],
        )->validate();

        $academicUnits = AcademicUnit::query()
            ->whereIn('id', $selectedAcademicUnitIds)
            ->orderBy('name')
            ->get();

        $sedesByUnit = $this->selectedSedesByUser[$userId] ?? [];

        $assignedBy = (int) auth()->id();
        $assignedAt = now();

        DB::transaction(function () use ($user, $academicUnits, $sedesByUnit, $assignedBy, $assignedAt): void {
            UserAcademicUnitScope::query()->where('user_id', $user->id)->delete();

            $academicUnits->each(function (AcademicUnit $academicUnit) use ($user, $sedesByUnit, $assignedBy, $assignedAt): void {
                $chosenSedeId = (int) ($sedesByUnit[$academicUnit->id] ?? 0);
                $defaultSedeId = (int) ($academicUnit->legacy_sede_ids[0] ?? 0);

                $finalSedeId = $chosenSedeId > 0
                    && in_array($chosenSedeId, $academicUnit->legacy_sede_ids, true)
                    ? $chosenSedeId
                    : $defaultSedeId;

                if ($finalSedeId < 1) {
                    return;
                }

                UserAcademicUnitScope::query()->create([
                    'user_id' => $user->id,
                    'academic_unit_id' => $academicUnit->id,
                    'sed_id' => $finalSedeId,
                    'assigned_by' => $assignedBy,
                    'assigned_at' => $assignedAt,
                ]);
            });
        });

        $this->loadAdmins();
        $this->resetValidation();

        session()->flash('status', 'Facultades asignadas correctamente.');
    }

    public function clearScopes(int $userId): void
    {
        /** @var User $user */
        $user = User::role(RoleName::AdminUnidadAcademica->value)->findOrFail($userId);

        DB::transaction(function () use ($user): void {
            UserAcademicUnitScope::query()->where('user_id', $user->id)->delete();
        });

        $this->loadAdmins();

        session()->flash('status', 'Asignaciones eliminadas correctamente.');
    }

    public function hasUnsavedChanges(int $userId): bool
    {
        $selectedIds = collect($this->selectedAcademicUnitsByUser[$userId] ?? [])
            ->filter(static fn (bool $checked): bool => $checked)
            ->keys()
            ->map(static fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $originalIds = collect($this->originalAcademicUnitsByUser[$userId] ?? [])
            ->filter(static fn (bool $checked): bool => $checked)
            ->keys()
            ->map(static fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($selectedIds !== $originalIds) {
            return true;
        }

        $sedeChanges = $this->selectedSedesByUser[$userId] ?? [];
        foreach ($sedeChanges as $academicUnitId => $sedId) {
            if ((int) $sedId < 1) {
                continue;
            }

            $scope = $this->admins
                ->firstWhere('id', $userId)
                ?->academicUnitScopes
                ?->firstWhere('academic_unit_id', $academicUnitId);

            if ($scope && (int) $scope->sed_id === (int) $sedId) {
                continue;
            }

            $academicUnit = $this->academicUnits->firstWhere('id', $academicUnitId);
            $defaultSedeId = (int) ($academicUnit?->legacy_sede_ids[0] ?? 0);

            if ($defaultSedeId > 0 && $defaultSedeId !== (int) $sedId) {
                return true;
            }
        }

        return false;
    }

    protected function loadAcademicUnits(): void
    {
        $this->academicUnits = AcademicUnit::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    protected function loadAdmins(): void
    {
        $search = trim($this->search);
        $filterUnit = trim($this->filterAcademicUnit);

        $this->admins = User::query()
            ->role(RoleName::AdminUnidadAcademica->value)
            ->with(['academicUnitScopes.academicUnit', 'academicUnitScopes.assignedBy'])
            ->when($search !== '', function ($query) use ($search): void {
                $searchLike = '%'.$search.'%';

                $query->where(function ($builder) use ($searchLike): void {
                    $builder
                        ->where('name', 'like', $searchLike)
                        ->orWhere('email', 'like', $searchLike)
                        ->orWhere('documento', 'like', $searchLike);
                });
            })
            ->when($filterUnit !== '' && $filterUnit !== '0', function ($query) use ($filterUnit): void {
                $query->whereHas('academicUnitScopes', function ($builder) use ($filterUnit): void {
                    $builder->where('academic_unit_id', (int) $filterUnit);
                });
            })
            ->orderBy('name')
            ->get();

        $this->selectedAcademicUnitsByUser = [];
        $this->originalAcademicUnitsByUser = [];
        $this->selectedSedesByUser = [];

        foreach ($this->admins as $admin) {
            foreach ($this->academicUnits as $unit) {
                $key = (string) $unit->id;
                $this->selectedAcademicUnitsByUser[$admin->id][$key] = false;
                $this->originalAcademicUnitsByUser[$admin->id][$key] = false;
            }

            foreach ($admin->academicUnitScopes as $scope) {
                if ($scope->academic_unit_id) {
                    $key = (string) $scope->academic_unit_id;
                    $this->selectedAcademicUnitsByUser[$admin->id][$key] = true;
                    $this->originalAcademicUnitsByUser[$admin->id][$key] = true;
                    $this->selectedSedesByUser[$admin->id][$scope->academic_unit_id] = (int) $scope->sed_id;
                }
            }
        }
    }
}; ?>

<div class="space-y-6">
    <x-slot name="header">Administradores por Unidad Académica</x-slot>

    <x-mary-header
        title="Administradores por Unidad Académica"
        subtitle="Asigná facultades a usuarios con rol ADMIN_UNIDAD_ACADEMICA. Si un usuario no tiene facultades asignadas, no podrá usar los módulos administrativos compartidos."
        separator
    />

    @if (session('status'))
        <x-mary-alert title="{{ session('status') }}" icon="o-check-circle" class="alert-success" />
    @endif

    <section class="grid gap-4 md:grid-cols-3">
        <article class="glass-card card">
            <div class="card-body gap-1">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Admins UAC</p>
                <p class="text-3xl font-semibold text-primary">{{ $admins->count() }}</p>
                <p class="text-sm text-base-content/65">Usuarios con rol administrativo por facultad</p>
            </div>
        </article>

        <article class="glass-card card">
            <div class="card-body gap-1">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Facultades</p>
                <p class="text-3xl font-semibold text-secondary">{{ $academicUnits->count() }}</p>
                <p class="text-sm text-base-content/65">Catálogo local de unidades académicas activas</p>
            </div>
        </article>

        <article class="glass-card card">
            <div class="card-body gap-1">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Cobertura</p>
                <p class="text-3xl font-semibold text-accent">{{ $admins->filter(fn ($admin) => $admin->academicUnitScopes->isNotEmpty())->count() }}</p>
                <p class="text-sm text-base-content/65">Admins con al menos una facultad asignada</p>
            </div>
        </article>
    </section>

    <section class="glass-card card">
        <div class="card-body gap-4">
            <div class="flex flex-col gap-4 md:flex-row md:items-end">
                <div class="form-control w-full md:max-w-md">
                    <span class="label-text text-sm font-medium mb-1">Buscar administrador</span>
                    <input wire:model.live.debounce.300ms="search" type="text" class="input input-bordered w-full" placeholder="Nombre, correo o documento" />
                </div>

                <div class="form-control w-full md:max-w-xs">
                    <span class="label-text text-sm font-medium mb-1">Filtrar por facultad</span>
                    <select wire:model.change="filterAcademicUnit" class="select select-bordered w-full">
                        <option value="">Todas las facultades</option>
                        <option value="0">Sin facultades asignadas</option>
                        @foreach ($academicUnits as $academicUnit)
                            <option value="{{ $academicUnit->id }}">{{ $academicUnit->name }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($search !== '' || $filterAcademicUnit !== '')
                    <button type="button" wire:click="clearFilters" class="btn btn-ghost btn-sm shrink-0">
                        <x-icon name="o-x-mark" class="h-4 w-4" />
                        Limpiar filtros
                    </button>
                @endif
            </div>

            @if ($admins->isEmpty())
                <x-mary-alert title="No hay administradores por unidad académica para mostrar." icon="o-information-circle" class="alert-info" />
            @else
                <div class="space-y-4">
                    @foreach ($admins as $admin)
                        @php
                            $unsaved = $this->hasUnsavedChanges($admin->id);
                        @endphp
                        <article
                            wire:key="academic-unit-admin-{{ $admin->id }}"
                            @class([
                                'rounded-[1.75rem] border p-5 shadow-sm transition-colors',
                                'border-warning bg-warning/5 ring-1 ring-warning/30' => $unsaved,
                                'border-base-300 bg-base-100/85' => ! $unsaved,
                            ])
                        >
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2 lg:min-w-0 lg:flex-1">
                                    <div class="flex items-start gap-3">
                                        <div class="min-w-0">
                                            <h3 class="text-lg font-semibold text-base-content truncate">{{ $admin->name }}</h3>
                                            <p class="text-sm text-base-content/65 truncate">{{ $admin->email }} · Documento: {{ $admin->documento ?? 'Sin dato' }}</p>
                                        </div>
                                        @if ($unsaved)
                                            <span class="badge badge-warning badge-sm mt-1 shrink-0">Cambios sin guardar</span>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        @forelse ($admin->academicUnitScopes as $scope)
                                            <div class="flex flex-col gap-0.5">
                                                <span class="badge badge-outline badge-sm">
                                                    <span class="truncate max-w-[180px]">{{ $scope->academicUnit?->name ?? 'Sin facultad' }}</span>
                                                    <span class="opacity-60 ml-1 shrink-0">(Sede: {{ $scope->sed_id }})</span>
                                                </span>
                                                @if ($scope->assignedBy)
                                                    <span class="text-[0.65rem] text-base-content/40 leading-none">
                                                        Asignado por {{ $scope->assignedBy->name }} el {{ $scope->assigned_at?->format('d/m/Y') }}
                                                    </span>
                                                @endif
                                            </div>
                                        @empty
                                            <span class="badge badge-warning badge-sm">Sin facultades asignadas</span>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="w-full lg:max-w-2xl lg:shrink-0">
                                    <fieldset class="grid gap-2 md:grid-cols-2">
                                        @foreach ($academicUnits as $academicUnit)
                                            @php
                                                $sedeIds = $academicUnit->legacy_sede_ids;
                                                $hasMultipleSedes = count($sedeIds) > 1;
                                                $defaultSedeId = (int) ($sedeIds[0] ?? 0);
                                                $isChecked = $selectedAcademicUnitsByUser[$admin->id][(string) $academicUnit->id] ?? false;
                                            @endphp
                                            <div class="rounded-2xl border border-base-300 bg-base-200/40 px-3 py-2.5 min-w-0">
                                                <x-checkbox
                                                    wire:model="selectedAcademicUnitsByUser.{{ $admin->id }}.{{ $academicUnit->id }}"
                                                >
                                                    <x-slot:label>
                                                        <span class="text-sm font-medium leading-tight truncate">{{ $academicUnit->name }}</span>
                                                    </x-slot:label>
                                                </x-checkbox>

                                                @if ($hasMultipleSedes)
                                                    <select
                                                        wire:model="selectedSedesByUser.{{ $admin->id }}.{{ $academicUnit->id }}"
                                                        @class([
                                                            'select select-bordered select-xs w-full mt-2',
                                                            'select-disabled opacity-50' => ! $isChecked,
                                                        ])
                                                        @disabled(! $isChecked)
                                                    >
                                                        <option value="">Sede por defecto</option>
                                                        @foreach ($sedeIds as $sedeId)
                                                            <option value="{{ (int) $sedeId }}">{{ $sedesMap[(int) $sedeId] ?? "Sede {$sedeId}" }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <p class="text-xs text-base-content/50 mt-1 pl-1">
                                                        {{ $sedesMap[$defaultSedeId] ?? "Sede {$defaultSedeId}" }}
                                                    </p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </fieldset>

                                    <div class="mt-4 flex justify-end gap-3">
                                        <button
                                            type="button"
                                            wire:click="clearScopes({{ $admin->id }})"
                                            class="btn btn-ghost btn-sm text-error"
                                            x-data
                                            x-on:click.prevent="
                                                if (confirm('¿Eliminar todas las facultades asignadas a ' + @js($admin->name) + '?')) {
                                                    $wire.clearScopes({{ $admin->id }})
                                                }
                                            "
                                        >
                                            <x-icon name="o-trash" class="h-4 w-4" />
                                            Quitar todas
                                        </button>

                                        <button type="button" wire:click="saveScopes({{ $admin->id }})" class="btn btn-primary min-w-48">
                                            <span wire:loading.remove wire:target="saveScopes({{ $admin->id }})">Guardar facultades</span>
                                            <span wire:loading wire:target="saveScopes({{ $admin->id }})" class="loading loading-spinner loading-sm"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>