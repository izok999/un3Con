<?php

use App\Enums\RoleName;
use App\Models\AcademicUnit;
use App\Models\User;
use App\Models\UserAcademicUnitScope;
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

    /** @var array<int, array<int, int|string>> */
    public array $selectedAcademicUnitsByUser = [];

    public function boot(): void
    {
        $this->admins = collect();
        $this->academicUnits = collect();
    }

    public function mount(): void
    {
        $this->loadAcademicUnits();
        $this->loadAdmins();
    }

    public function updatedSearch(): void
    {
        $this->loadAdmins();
    }

    public function saveScopes(int $userId): void
    {
        /** @var User $user */
        $user = User::role(RoleName::AdminUnidadAcademica->value)->findOrFail($userId);

        $selectedAcademicUnitIds = collect($this->selectedAcademicUnitsByUser[$userId] ?? [])
            ->map(static fn (mixed $academicUnitId): int => (int) $academicUnitId)
            ->filter(static fn (int $academicUnitId): bool => $academicUnitId > 0)
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

        DB::transaction(function () use ($user, $academicUnits): void {
            UserAcademicUnitScope::query()->where('user_id', $user->id)->delete();

            $academicUnits->each(function (AcademicUnit $academicUnit) use ($user): void {
                $defaultSedeId = (int) ($academicUnit->legacy_sede_ids[0] ?? 0);

                if ($defaultSedeId < 1) {
                    return;
                }

                UserAcademicUnitScope::query()->create([
                    'user_id' => $user->id,
                    'academic_unit_id' => $academicUnit->id,
                    'sed_id' => $defaultSedeId,
                ]);
            });
        });

        $this->loadAdmins();
        $this->resetValidation();

        session()->flash('status', 'Facultades asignadas correctamente.');
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

        $this->admins = User::query()
            ->role(RoleName::AdminUnidadAcademica->value)
            ->with(['academicUnitScopes.academicUnit'])
            ->when($search !== '', function ($query) use ($search): void {
                $searchLike = '%'.$search.'%';

                $query->where(function ($builder) use ($searchLike): void {
                    $builder
                        ->where('name', 'like', $searchLike)
                        ->orWhere('email', 'like', $searchLike)
                        ->orWhere('documento', 'like', $searchLike);
                });
            })
            ->orderBy('name')
            ->get();

        foreach ($this->admins as $admin) {
            $this->selectedAcademicUnitsByUser[$admin->id] = $admin->academicUnitScopes
                ->pluck('academic_unit_id')
                ->filter()
                ->map(static fn (mixed $academicUnitId): string => (string) $academicUnitId)
                ->values()
                ->all();
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
            <label class="form-control w-full md:max-w-md">
                <span class="label-text text-sm font-medium">Buscar administrador</span>
                <input wire:model.live.debounce.300ms="search" type="text" class="input input-bordered w-full" placeholder="Nombre, correo o documento" />
            </label>

            @if ($admins->isEmpty())
                <x-mary-alert title="No hay administradores por unidad académica para mostrar." icon="o-information-circle" class="alert-info" />
            @else
                <div class="space-y-4">
                    @foreach ($admins as $admin)
                        <article wire:key="academic-unit-admin-{{ $admin->id }}" class="rounded-[1.75rem] border border-base-300 bg-base-100/85 p-5 shadow-sm">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                <div class="space-y-2">
                                    <div>
                                        <h3 class="text-lg font-semibold text-base-content">{{ $admin->name }}</h3>
                                        <p class="text-sm text-base-content/65">{{ $admin->email }} · Documento: {{ $admin->documento ?? 'Sin dato' }}</p>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        @forelse ($admin->academicUnitScopes as $scope)
                                            <span class="badge badge-outline badge-sm">{{ $scope->academicUnit?->name ?? 'Sin facultad' }}</span>
                                        @empty
                                            <span class="badge badge-warning badge-sm">Sin facultades asignadas</span>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="w-full xl:max-w-3xl">
                                    <fieldset class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                        @foreach ($academicUnits as $academicUnit)
                                            <label class="label cursor-pointer justify-start gap-3 rounded-2xl border border-base-300 bg-base-200/40 px-4 py-3">
                                                <input
                                                    type="checkbox"
                                                    value="{{ $academicUnit->id }}"
                                                    wire:model="selectedAcademicUnitsByUser.{{ $admin->id }}"
                                                    class="checkbox checkbox-primary"
                                                />
                                                <span class="label-text text-sm font-medium leading-tight">{{ $academicUnit->name }}</span>
                                            </label>
                                        @endforeach
                                    </fieldset>

                                    <div class="mt-4 flex justify-end">
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