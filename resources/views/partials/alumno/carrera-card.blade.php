@php($halId = data_get($carrera, 'hal_id'))

@if($halId)
    <a href="{{ route('alumno.carreras.show', ['halId' => $halId]) }}"
       wire:navigate
       class="group relative block overflow-hidden rounded-[1.75rem] border border-primary/15 bg-base-100/85 p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-primary/40 hover:shadow-xl">
@else
    <div class="relative block overflow-hidden rounded-[1.75rem] border border-primary/15 bg-base-100/85 p-5 shadow-sm">
@endif

    <div class="absolute inset-x-0 top-0 h-1 bg-linear-to-r from-primary via-secondary to-accent"></div>

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
        @if($halId)
            <span class="text-base-content/55">Habilitación #{{ $halId }}</span>
            <span class="inline-flex items-center gap-2 text-primary transition group-hover:translate-x-1">
                Ver detalle
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                </svg>
            </span>
        @else
            <span class="text-base-content/55">Detalle de habilitación disponible próximamente.</span>
        @endif
    </div>

@if($halId)
    </a>
@else
    </div>
@endif