<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>
    <div data-dashboard-stagger class="dashboard-stagger space-y-6">
        <div data-dashboard-stagger-item class="card glass-card" style="--dashboard-stagger-index: 0;">
            <div class="card-body gap-2">
                <h3 class="card-title text-base">Bienvenido, {{ auth()->user()->name }}</h3>
                <p class="text-base-content/70">Usá el menú lateral para acceder a tus consultas académicas y financieras.</p>
            </div>
        </div>

        @role('ALUMNO')
            <div data-dashboard-stagger-item style="--dashboard-stagger-index: 1;">
                <livewire:alumno.dashboard-carreras lazy />
            </div>

            <section class="space-y-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-base-content/45">Accesos rápidos</p>
                    <h2 class="text-xl font-semibold text-base-content">Consultas disponibles</h2>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div data-dashboard-stagger-item class="stat glass-card" style="--dashboard-stagger-index: 2;">
                        <div class="stat-figure text-primary">
                            <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" /></svg>
                        </div>
                        <div class="stat-title">Mis Carreras</div>
                        <div class="stat-desc">Habilitaciones activas</div>
                        <div class="stat-actions">
                            <a href="{{ route('alumno.carreras') }}" class="btn btn-primary btn-xs">Ver</a>
                        </div>
                    </div>
                    <div data-dashboard-stagger-item class="stat glass-card" style="--dashboard-stagger-index: 3;">
                        <div class="stat-figure text-secondary">
                            <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                        </div>
                        <div class="stat-title">Extracto Académico</div>
                        <div class="stat-desc">Calificaciones históricas</div>
                        <div class="stat-actions">
                            <a href="{{ route('alumno.extracto') }}" class="btn btn-secondary btn-xs">Ver</a>
                        </div>
                    </div>
                    <div data-dashboard-stagger-item class="stat glass-card" style="--dashboard-stagger-index: 4;">
                        <div class="stat-figure text-accent">
                            <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                        </div>
                        <div class="stat-title">Materias Inscriptas</div>
                        <div class="stat-desc">Periodo vigente</div>
                        <div class="stat-actions">
                            <a href="{{ route('alumno.materias') }}" class="btn btn-accent btn-xs">Ver</a>
                        </div>
                    </div>
                    <div data-dashboard-stagger-item class="stat glass-card" style="--dashboard-stagger-index: 5;">
                        <div class="stat-figure text-error">
                            <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>
                        </div>
                        <div class="stat-title">Estado de Cuenta</div>
                        <div class="stat-desc">Deudas y saldos</div>
                        <div class="stat-actions">
                            <a href="{{ route('alumno.deudas') }}" class="btn btn-error btn-xs">Ver</a>
                        </div>
                    </div>
                </div>
            </section>
        @else
            <div data-dashboard-stagger-item class="card glass-card" style="--dashboard-stagger-index: 1;">
                <div class="card-body gap-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-base-content/45">Panel general</p>
                    <h2 class="text-xl font-semibold text-primary">Tu acceso está listo</h2>
                    <p class="text-base-content/70">Usá el menú lateral para navegar por los módulos habilitados para tu rol.</p>
                </div>
            </div>
        @endrole
    </div>

</x-app-layout>
