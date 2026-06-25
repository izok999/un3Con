<x-app-layout>
    <x-slot name="header">{{ __('Dashboard') }}</x-slot>
    <div data-dashboard-stagger class="dashboard-stagger space-y-6">
        <div data-dashboard-stagger-item class="card glass-card overflow-hidden" style="--dashboard-stagger-index: 0;">
            {{-- Franja de acento institucional, idéntica a la del detalle de carrera --}}
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-secondary to-accent"></div>
            <div class="card-body gap-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                    {{-- Ícono institucional --}}
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-primary/10 overflow-hidden">
                        <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }} logo" class="h-10 w-10 object-contain" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-base-content/45">{{ __('Portal del Estudiante') }}</p>
                        <h2 class="text-xl font-semibold text-primary">{{ __('Bienvenido, :name', ['name' => auth()->user()->name]) }}</h2>
                        <p class="mt-0.5 text-sm text-base-content/65">{{ __('Usá el menú lateral para acceder a tus consultas académicas y financieras.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        @role('ALUMNO')
            <div data-dashboard-stagger-item style="--dashboard-stagger-index: 1;">
                <livewire:alumno.dashboard-carreras lazy />
            </div>


        @else
            <div data-dashboard-stagger-item class="card glass-card" style="--dashboard-stagger-index: 1;">
                <div class="card-body gap-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-base-content/45">{{ __('Panel general') }}</p>
                    <h2 class="text-xl font-semibold text-primary">{{ __('Tu acceso está listo') }}</h2>
                    <p class="text-base-content/70">{{ __('Usá el menú lateral para navegar por los módulos habilitados para tu rol.') }}</p>
                </div>
            </div>
        @endrole
    </div>

</x-app-layout>
