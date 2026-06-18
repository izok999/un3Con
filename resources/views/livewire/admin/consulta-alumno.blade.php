<?php

use App\Models\User;
use App\Services\AlumnoExternoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $documento = '';
    public string $tab = 'perfil';

    public ?array $alumno = null;
    public array $carreras = [];
    public array $extracto = [];
    public array $materias = [];
    public array $deudas = [];
    public array $asistencia = [];
    public array $malla = [];
    public array $certificados = [];

    public bool $buscado = false;
    public ?string $error = null;

    /** @var array<int, int> */
    public array $allowedSedeIds = [];

    /** @var array<int, string> */
    public array $allowedAcademicUnitNames = [];

    public function mount(): void
    {
        $this->allowedSedeIds = $this->resolveAllowedSedeIds();
        $this->allowedAcademicUnitNames = $this->resolveAllowedAcademicUnitNames();
    }

    public function buscar(): void
    {
        $this->validate([
            'documento' => 'required|string|min:3|max:20',
        ]);

        $this->reset(['alumno', 'carreras', 'extracto', 'materias', 'deudas', 'asistencia', 'malla', 'certificados', 'error']);
        $this->buscado = true;
        $this->tab = 'perfil';

        $service = app(AlumnoExternoService::class);

        try {
            $result = $service->resolverAlumno($this->documento);

            if (! $result) {
                $this->error = "No se encontró un alumno con documento «{$this->documento}».";
                return;
            }

            $aluId = (int) $result->alu_id;

            $toArrayMap = fn ($collection) => $collection->map(fn ($item) => (array) $item)->toArray();

            $carreras = $toArrayMap($service->carreras($aluId));
            $materias = $toArrayMap($service->materiasInscriptas($aluId));

            if ($this->isScopedAcademicAdmin() && ! $this->studentBelongsToManagedAcademicUnit($carreras, $materias)) {
                $this->error = 'Solo podés consultar alumnos vinculados a las facultades que tenés asignadas.';

                return;
            }

            $this->alumno = (array) $result;
            $this->carreras = $this->isScopedAcademicAdmin()
                ? $this->filterRowsByAllowedSedes($carreras, ['sed_id'])
                : $carreras;
            $this->materias = $this->isScopedAcademicAdmin()
                ? $this->filterRowsByAllowedSedes($materias, ['sed_id', 'rsc_idsed', 'imi_idsed'])
                : $materias;

            $this->extracto = $toArrayMap($service->extractoAcademico($aluId));
            $this->deudas = $toArrayMap($service->deudas($aluId));
            $this->asistencia = $toArrayMap($service->asistencia($aluId));
            $this->malla = $toArrayMap($service->mallaCurricular($aluId));
            $this->certificados = $toArrayMap($service->certificados($aluId));
        } catch (\Throwable $e) {
            $this->error = 'Error al consultar la base de datos externa: ' . $e->getMessage();
        }
    }

    public function limpiar(): void
    {
        $this->reset();
        $this->allowedSedeIds = $this->resolveAllowedSedeIds();
        $this->allowedAcademicUnitNames = $this->resolveAllowedAcademicUnitNames();
    }

    /**
     * @return array<int, int>
     */
    protected function resolveAllowedSedeIds(): array
    {
        /** @var ?User $user */
        $user = Auth::user();

        if (! $user || ! $user->isAcademicUnitAdmin()) {
            return [];
        }

        return $user->managedSedeIds();
    }

    /**
     * @return array<int, string>
     */
    protected function resolveAllowedAcademicUnitNames(): array
    {
        /** @var ?User $user */
        $user = Auth::user();

        if (! $user || ! $user->isAcademicUnitAdmin()) {
            return [];
        }

        return $user->academicUnitScopes()
            ->with('academicUnit:id,name')
            ->get()
            ->pluck('academicUnit.name')
            ->filter()
            ->values()
            ->all();
    }

    protected function isScopedAcademicAdmin(): bool
    {
        /** @var ?User $user */
        $user = Auth::user();

        return $user?->isAcademicUnitAdmin() ?? false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $candidateFields
     * @return array<int, array<string, mixed>>
     */
    protected function filterRowsByAllowedSedes(array $rows, array $candidateFields): array
    {
        return array_values(array_filter($rows, fn (array $row): bool => $this->rowMatchesAllowedSede($row, $candidateFields)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $carreras
     * @param  array<int, array<string, mixed>>  $materias
     */
    protected function studentBelongsToManagedAcademicUnit(array $carreras, array $materias): bool
    {
        return $this->filterRowsByAllowedSedes($carreras, ['sed_id']) !== []
            || $this->filterRowsByAllowedSedes($materias, ['sed_id', 'rsc_idsed', 'imi_idsed']) !== [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $candidateFields
     */
    protected function rowMatchesAllowedSede(array $row, array $candidateFields): bool
    {
        foreach ($candidateFields as $field) {
            $value = $row[$field] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (in_array((int) $value, $this->allowedSedeIds, true)) {
                return true;
            }
        }

        return false;
    }
}; ?>

<div>
    <x-slot name="header">Consulta de Alumnos</x-slot>

    @if (! empty($allowedAcademicUnitNames))
        <div class="alert alert-info mb-6">
            <x-icon name="o-shield-check" class="w-5 h-5" />
            <div class="space-y-1">
                <p class="font-medium">Consulta restringida por facultad</p>
                <p class="text-sm">Solo se habilitan alumnos vinculados a: {{ implode(', ', $allowedAcademicUnitNames) }}.</p>
            </div>
        </div>
    @endif

    {{-- Buscador --}}
    <div class="card bg-base-100 shadow-sm mb-6">
        <div class="card-body">
            <h3 class="card-title text-base mb-4">
                <x-icon name="o-magnifying-glass" class="w-5 h-5" />
                Buscar alumno por documento
            </h3>
            <form wire:submit="buscar" class="flex flex-col sm:flex-row gap-3">
                <div class="form-control flex-1">
                    <input wire:model="documento" type="text"
                           class="input input-bordered w-full"
                           placeholder="Ingrese número de cédula / documento"
                           autofocus />
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary gap-2">
                        <span wire:loading.remove wire:target="buscar">
                            <x-icon name="o-magnifying-glass" class="w-4 h-4" />
                        </span>
                        <span wire:loading wire:target="buscar" class="loading loading-spinner loading-sm"></span>
                        Buscar
                    </button>
                    @if($buscado)
                        <button type="button" wire:click="limpiar" class="btn btn-ghost">Limpiar</button>
                    @endif
                </div>
            </form>
            @error('documento')
                <p class="text-error text-sm mt-2">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- Error --}}
    @if($error)
        <div class="alert alert-error mb-6">
            <x-icon name="o-exclamation-triangle" class="w-5 h-5" />
            <span>{{ $error }}</span>
        </div>
    @endif

    {{-- Resultados --}}
    @if($alumno)
        {{-- Perfil resumido --}}
        <div class="card bg-base-100 shadow-sm mb-6">
            <div class="card-body flex flex-row items-center gap-4">
                <div class="avatar placeholder">
                    <div class="bg-primary text-primary-content rounded-full w-14">
                        <span class="text-xl">{{ substr($alumno['alu_nombre'] ?? '?', 0, 1) }}{{ substr($alumno['alu_apellido'] ?? '', 0, 1) }}</span>
                    </div>
                </div>
                <div>
                    <h2 class="text-lg font-bold">{{ $alumno['alu_nombre'] ?? '' }} {{ $alumno['alu_apellido'] ?? '' }}</h2>
                    <p class="text-base-content/60 text-sm">Doc: {{ $alumno['alu_perdoc'] ?? $documento }} · ID Alumno: {{ $alumno['alu_id'] }}</p>
                </div>
                <div class="ml-auto hidden sm:flex gap-4 text-sm">
                    <div class="stat place-items-center p-2">
                        <div class="stat-title text-xs">Carreras</div>
                        <div class="stat-value text-primary text-lg">{{ count($carreras) }}</div>
                    </div>
                    <div class="stat place-items-center p-2">
                        <div class="stat-title text-xs">Materias</div>
                        <div class="stat-value text-secondary text-lg">{{ count($materias) }}</div>
                    </div>
                    <div class="stat place-items-center p-2">
                        <div class="stat-title text-xs">Deudas</div>
                        <div class="stat-value text-error text-lg">{{ count($deudas) }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div role="tablist" class="tabs tabs-bordered mb-4">
            <button wire:click="$set('tab', 'perfil')" role="tab"
                    class="tab {{ $tab === 'perfil' ? 'tab-active' : '' }}">Perfil</button>
            <button wire:click="$set('tab', 'carreras')" role="tab"
                    class="tab {{ $tab === 'carreras' ? 'tab-active' : '' }}">Carreras ({{ count($carreras) }})</button>
            <button wire:click="$set('tab', 'extracto')" role="tab"
                    class="tab {{ $tab === 'extracto' ? 'tab-active' : '' }}">Extracto ({{ count($extracto) }})</button>
            <button wire:click="$set('tab', 'materias')" role="tab"
                    class="tab {{ $tab === 'materias' ? 'tab-active' : '' }}">Materias ({{ count($materias) }})</button>
            <button wire:click="$set('tab', 'deudas')" role="tab"
                    class="tab {{ $tab === 'deudas' ? 'tab-active' : '' }}">Deudas ({{ count($deudas) }})</button>
            <button wire:click="$set('tab', 'asistencia')" role="tab"
                    class="tab {{ $tab === 'asistencia' ? 'tab-active' : '' }}">Asistencia ({{ count($asistencia) }})</button>
            <button wire:click="$set('tab', 'malla')" role="tab"
                    class="tab {{ $tab === 'malla' ? 'tab-active' : '' }}">Malla ({{ count($malla) }})</button>
            <button wire:click="$set('tab', 'certificados')" role="tab"
                    class="tab {{ $tab === 'certificados' ? 'tab-active' : '' }}">Certificados ({{ count($certificados) }})</button>
        </div>

        {{-- Tab content --}}
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body overflow-x-auto">

                {{-- PERFIL --}}
                @if($tab === 'perfil')
                    <h3 class="font-semibold mb-3">Datos del alumno</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($alumno as $key => $value)
                            <div class="flex gap-2 text-sm">
                                <span class="font-mono text-base-content/50 min-w-48 shrink-0">{{ $key }}</span>
                                <span class="font-medium">{{ $value ?? '—' }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- CARRERAS --}}
                @if($tab === 'carreras')
                    @include('livewire.admin.partials.tabla-generica', ['datos' => $carreras, 'vacio' => 'Sin carreras registradas'])
                @endif

                {{-- EXTRACTO --}}
                @if($tab === 'extracto')
                    @include('livewire.admin.partials.tabla-generica', ['datos' => $extracto, 'vacio' => 'Sin registros en el extracto académico'])
                @endif

                {{-- MATERIAS --}}
                @if($tab === 'materias')
                    @include('livewire.admin.partials.tabla-generica', ['datos' => $materias, 'vacio' => 'Sin materias inscriptas'])
                @endif

                {{-- DEUDAS --}}
                @if($tab === 'deudas')
                    @include('livewire.admin.partials.tabla-generica', ['datos' => $deudas, 'vacio' => 'Sin deudas registradas'])
                @endif

                {{-- ASISTENCIA --}}
                @if($tab === 'asistencia')
                    @include('livewire.admin.partials.tabla-generica', ['datos' => $asistencia, 'vacio' => 'Sin registros de asistencia'])
                @endif

                {{-- MALLA --}}
                @if($tab === 'malla')
                    @include('livewire.admin.partials.tabla-generica', ['datos' => $malla, 'vacio' => 'Sin malla curricular disponible'])
                @endif

                {{-- CERTIFICADOS --}}
                @if($tab === 'certificados')
                    @include('livewire.admin.partials.tabla-generica', ['datos' => $certificados, 'vacio' => 'Sin certificados emitidos'])
                @endif

            </div>
        </div>
    @elseif($buscado && !$error)
        <div class="text-center py-12 text-base-content/50">
            <x-icon name="o-user" class="w-16 h-16 mx-auto mb-4 opacity-30" />
            <p>No se encontraron resultados.</p>
        </div>
    @elseif(!$buscado)
        <div class="text-center py-12 text-base-content/50">
            <x-icon name="o-magnifying-glass" class="w-16 h-16 mx-auto mb-4 opacity-30" />
            <p>Ingrese un número de documento para consultar los datos del alumno.</p>
        </div>
    @endif
</div>
