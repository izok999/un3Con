<?php

use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;

new class extends Component
{
    public Collection $carreras;
    public string $error = '';

    public function boot(): void
    {
        $this->carreras = collect();
    }

    public function mount(AlumnoExternoService $service): void
    {
        $user = auth()->user();

        if (! filled($user->documento)) {
            $this->error = 'Tu cuenta no tiene documento asociado. Contactá al administrador.';

            return;
        }

        $alumno = $service->resolverAlumno($user->documento);

        if (! $alumno) {
            $this->error = 'No se encontró un alumno con el documento registrado en tu cuenta.';

            return;
        }

        $this->carreras = $service->carreras($alumno->alu_id);
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <section class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="space-y-2">
                    <div class="skeleton h-3 w-20"></div>
                    <div class="skeleton h-8 w-72"></div>
                </div>
                <div class="skeleton h-9 w-40 rounded-[1.15rem]"></div>
            </div>
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div class="rounded-[1.75rem] border border-base-300 bg-base-100/85 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-2">
                            <div class="skeleton h-3 w-16"></div>
                            <div class="skeleton h-7 w-52"></div>
                            <div class="skeleton h-4 w-40"></div>
                        </div>
                        <div class="skeleton h-5 w-16 rounded-[0.75rem]"></div>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="skeleton h-16 rounded-2xl"></div>
                        <div class="skeleton h-16 rounded-2xl"></div>
                    </div>
                    <div class="mt-5 flex items-center justify-between">
                        <div class="skeleton h-4 w-32"></div>
                        <div class="skeleton h-4 w-20"></div>
                    </div>
                </div>
                <div class="rounded-[1.75rem] border border-base-300 bg-base-100/85 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-2">
                            <div class="skeleton h-3 w-16"></div>
                            <div class="skeleton h-7 w-52"></div>
                            <div class="skeleton h-4 w-40"></div>
                        </div>
                        <div class="skeleton h-5 w-16 rounded-[0.75rem]"></div>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="skeleton h-16 rounded-2xl"></div>
                        <div class="skeleton h-16 rounded-2xl"></div>
                    </div>
                    <div class="mt-5 flex items-center justify-between">
                        <div class="skeleton h-4 w-32"></div>
                        <div class="skeleton h-4 w-20"></div>
                    </div>
                </div>
            </div>
        </section>
        HTML;
    }
}; ?>

<section class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-base-content/45">Tus carreras</p>
            <h2 class="text-2xl font-semibold text-primary">Accedé directo al detalle de cada habilitación</h2>
        </div>

        <a href="{{ route('alumno.carreras') }}" class="btn btn-outline btn-primary btn-sm w-full sm:w-auto">Ver listado completo</a>
    </div>

    @if($error !== '')
        <div class="alert alert-warning shadow-sm">
            <span>{{ $error }}</span>
        </div>
    @elseif($carreras->isEmpty())
        <div class="alert alert-info shadow-sm">
            <span>No se encontraron carreras activas o históricas vinculadas a tu documento.</span>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @foreach($carreras as $carrera)
                <a href="{{ route('alumno.carreras.show', ['halId' => $carrera->hal_id]) }}"
                   class="group relative block overflow-hidden rounded-[1.75rem] border border-primary/15 bg-base-100/85 p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-primary/40 hover:shadow-xl">

                    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-secondary to-accent"></div>

                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-base-content/45">Carrera</p>
                            <h3 class="text-xl font-semibold text-primary">{{ $carrera->pac_descri }}</h3>
                            <p class="text-sm text-base-content/70">{{ $carrera->uac_descri }}</p>
                        </div>

                        @if($carrera->hal_vigent)
                            <span class="badge badge-success badge-sm shrink-0">Vigente</span>
                        @else
                            <span class="badge badge-warning badge-sm shrink-0">No vigente</span>
                        @endif
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-base-200/70 p-3">
                            <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">Sede</p>
                            <p class="mt-1 font-medium text-base-content">{{ $carrera->ciu_descri }}</p>
                        </div>
                        <div class="rounded-2xl bg-base-200/70 p-3">
                            <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">Periodo</p>
                            <p class="mt-1 font-medium text-base-content">{{ $carrera->ple_codigo }} · {{ $carrera->ple_descri }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex items-center justify-between text-sm font-medium">
                        <span class="text-base-content/55">Habilitación #{{ $carrera->hal_id }}</span>
                        <span class="inline-flex items-center gap-2 text-primary transition group-hover:translate-x-1">
                            Ver detalle
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                            </svg>
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>
