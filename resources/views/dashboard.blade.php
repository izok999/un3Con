<x-app-layout>
    <x-slot name="header">{{ __('Dashboard') }}</x-slot>
    <div data-dashboard-stagger class="dashboard-stagger space-y-6">
        <div data-dashboard-stagger-item class="card glass-card overflow-hidden" style="--dashboard-stagger-index: 0;">
            {{-- Franja de acento institucional, idéntica a la del detalle de carrera --}}
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-secondary to-accent"></div>
            <div class="card-body gap-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                    {{-- Ícono institucional --}}
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10">
                        <svg class="h-7 w-7 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                        </svg>
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
