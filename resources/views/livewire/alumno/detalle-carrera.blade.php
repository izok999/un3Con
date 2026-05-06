<?php

use App\Services\AlumnoExternoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?object $alumno = null;
    public ?object $carrera = null;
    public string $error = '';

    public function mount(int $halId, AlumnoExternoService $service): void
    {
        $documento = Auth::user()?->documento;

        if (! $documento) {
            $this->error = 'Tu cuenta no tiene documento asociado. Contactá al administrador.';

            return;
        }

        $this->alumno = $service->resolverAlumno($documento);

        if (! $this->alumno) {
            $this->error = 'No se encontró un alumno con el documento registrado en tu cuenta.';

            return;
        }

        $this->carrera = $service->carreras($this->alumno->alu_id)
            ->first(fn (object $c): bool => (int) $c->hal_id === $halId);

        abort_if(! $this->carrera, 404);
        abort_if(! isset($this->carrera->hal_idrsc, $this->carrera->hal_idple), 404);
    }
}; ?>

<div>
    {{-- Breadcrumb / back --}}
    <div class="mb-4">
        <a href="{{ route('alumno.carreras') }}" class="inline-flex items-center gap-1.5 text-sm text-base-content/55 transition hover:text-primary">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            Mis carreras
        </a>
    </div>

    @if($error !== '')
        <x-mary-alert title="{{ $error }}" icon="o-exclamation-triangle" class="alert-warning" />
    @elseif($carrera)
        <div class="space-y-6">

            {{-- Hero card: disponible de inmediato desde mount() (viene del cache de carreras) --}}
            <div class="relative overflow-hidden rounded-[1.75rem] border border-primary/15 bg-base-100/85 p-6 shadow-sm">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-secondary to-accent"></div>

                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-base-content/45">Detalle de carrera</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-semibold text-primary">{{ $carrera->pac_descri }}</h1>
                        @if($carrera->hal_vigent)
                            <span class="badge badge-success badge-sm">Vigente</span>
                        @else
                            <span class="badge badge-warning badge-sm">No vigente</span>
                        @endif
                    </div>
                    <p class="text-base-content/70">{{ $carrera->uac_descri }}</p>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-base-200/70 p-3">
                        <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">Sede</p>
                        <p class="mt-1 font-medium text-base-content">{{ $carrera->ciu_descri }}</p>
                    </div>
                    <div class="rounded-2xl bg-base-200/70 p-3">
                        <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">Periodo</p>
                        <p class="mt-1 font-medium text-base-content">{{ $carrera->ple_codigo }} · {{ $carrera->ple_descri }}</p>
                    </div>
                    <div class="rounded-2xl bg-base-200/70 p-3">
                        <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">Habilitación</p>
                        <p class="mt-1 font-medium text-base-content">#{{ $carrera->hal_id }}</p>
                    </div>
                </div>
            </div>

            {{-- Stats: carga en paralelo con las demás secciones --}}
            <livewire:alumno.detalle-carrera.stats
                :alu-id="(int) $alumno->alu_id"
                :hal-id="(int) $carrera->hal_id"
                :rsc-id="(int) $carrera->hal_idrsc"
                :periodo-id="(int) $carrera->hal_idple"
                lazy
            />

            {{-- Materias + Evaluaciones + Asistencia + Deudas: cada una carga de forma independiente --}}
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <livewire:alumno.detalle-carrera.materias
                    :alu-id="(int) $alumno->alu_id"
                    :hal-id="(int) $carrera->hal_id"
                    :rsc-id="(int) $carrera->hal_idrsc"
                    lazy
                />
                <livewire:alumno.detalle-carrera.evaluaciones
                    :hal-id="(int) $carrera->hal_id"
                    lazy
                />
                <livewire:alumno.detalle-carrera.asistencias
                    :alu-id="(int) $alumno->alu_id"
                    :rsc-id="(int) $carrera->hal_idrsc"
                    :periodo-id="(int) $carrera->hal_idple"
                    lazy
                />
                <livewire:alumno.detalle-carrera.deudas
                    :alu-id="(int) $alumno->alu_id"
                    :rsc-id="(int) $carrera->hal_idrsc"
                    :periodo-id="(int) $carrera->hal_idple"
                    lazy
                />
            </div>

            {{-- Extracto académico: ancho completo, carga de forma independiente --}}
            <livewire:alumno.detalle-carrera.extracto
                :alu-id="(int) $alumno->alu_id"
                :hal-id="(int) $carrera->hal_id"
                lazy
            />

        </div>
    @endif
</div>
