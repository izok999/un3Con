<?php

use App\Services\AlumnoExternoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?object $alumno = null;
    public ?object $carrera = null;
    public Collection $materias;
    public Collection $extracto;
    public Collection $deudas;
    public Collection $asistencias;
    public Collection $evaluaciones;
    public string $error = '';
    public bool $isLoaded = false;

    public function boot(): void
    {
        $this->materias = collect();
        $this->extracto = collect();
        $this->deudas = collect();
        $this->asistencias = collect();
        $this->evaluaciones = collect();
    }

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

    /**
     * Carga las 5 queries lentas de forma diferida, invocado via wire:init.
     * Esto permite mostrar el hero card con datos reales de inmediato
     * (disponibles desde el cache en mount) mientras el resto carga.
     */
    public function loadData(AlumnoExternoService $service): void
    {
        $aluId = (int) $this->alumno->alu_id;
        $halId = (int) $this->carrera->hal_id;
        $rscId = (int) $this->carrera->hal_idrsc;
        $periodoId = (int) $this->carrera->hal_idple;

        $this->materias = $service->materiasPorHabilitacion($aluId, $halId, $rscId);
        $this->extracto = $service->extractoPorHabilitacion($aluId, $halId);
        $this->deudas = $service->deudasPorHabilitacion($aluId, $rscId, $periodoId);
        $this->asistencias = $service->asistenciaPorHabilitacion($aluId, $rscId, $periodoId);
        $this->evaluaciones = $service->evaluaciones($halId);
        $this->isLoaded = true;
    }

    public function getTotalDeudaProperty(): float|int
    {
        return $this->deudas->sum('dit_saldo');
    }

    public function getPromedioAsistenciaProperty(): int
    {
        $clases = (int) $this->asistencias->sum('alu_clase');

        if ($clases === 0) {
            return 0;
        }

        return (int) round(($this->asistencias->sum('alu_presen') / $clases) * 100);
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
        {{-- wire:init dispara loadData() una vez que Livewire inicializa el componente en el browser --}}
        <div wire:init="loadData" class="space-y-6">

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

            @if(! $isLoaded)
                {{-- Skeleton: stats --}}
                <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                    <div class="skeleton h-28 rounded-[1.5rem]"></div>
                    <div class="skeleton h-28 rounded-[1.5rem]"></div>
                    <div class="skeleton h-28 rounded-[1.5rem]"></div>
                    <div class="skeleton h-28 rounded-[1.5rem]"></div>
                </div>

                {{-- Skeleton: 4 cards 2x2 --}}
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div class="skeleton h-64 rounded-[1.5rem]"></div>
                    <div class="skeleton h-64 rounded-[1.5rem]"></div>
                    <div class="skeleton h-64 rounded-[1.5rem]"></div>
                    <div class="skeleton h-64 rounded-[1.5rem]"></div>
                </div>

                {{-- Skeleton: extracto (ancho completo) --}}
                <div class="skeleton h-80 rounded-[1.5rem]"></div>
            @else
                {{-- Stats --}}
                <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                    <div class="stat glass-card">
                        <div class="stat-figure text-primary">
                            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                        </div>
                        <div class="stat-title text-xs">Materias vigentes</div>
                        <div class="stat-value text-2xl text-primary">{{ $materias->count() }}</div>
                    </div>
                    <div class="stat glass-card">
                        <div class="stat-figure text-secondary">
                            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" /></svg>
                        </div>
                        <div class="stat-title text-xs">Evaluaciones</div>
                        <div class="stat-value text-2xl text-secondary">{{ $evaluaciones->count() }}</div>
                    </div>
                    <div class="stat glass-card">
                        <div class="stat-figure text-error">
                            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>
                        </div>
                        <div class="stat-title text-xs">Total pendiente</div>
                        <div class="stat-value text-lg text-error">Gs {{ number_format($this->totalDeuda, 0, ',', '.') }}</div>
                    </div>
                    <div class="stat glass-card">
                        <div class="stat-figure text-accent">
                            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
                        </div>
                        <div class="stat-title text-xs">Asistencia</div>
                        <div class="stat-value text-2xl text-accent">{{ $this->promedioAsistencia }}%</div>
                    </div>
                </div>

                {{-- Materias + Evaluaciones --}}
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div class="card glass-card">
                        <div class="card-body">
                            <h2 class="mb-1 text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Periodo actual</h2>
                            <h3 class="card-title text-base text-base-content">Materias inscriptas</h3>

                            @if($materias->isEmpty())
                                <x-mary-alert title="No hay materias vigentes para esta carrera." icon="o-information-circle" class="alert-info mt-2" />
                            @else
                                <div class="mt-2 overflow-x-auto">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Materia</th>
                                                <th>Curso</th>
                                                <th>Turno</th>
                                                <th>Sección</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($materias as $materia)
                                                <tr class="hover">
                                                    <td class="font-medium">{{ $materia->mat_descri }}</td>
                                                    <td>{{ $materia->cur_descri }}</td>
                                                    <td>{{ $materia->tur_descri }}</td>
                                                    <td>{{ $materia->sec_descri }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card glass-card">
                        <div class="card-body">
                            <h2 class="mb-1 text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Parciales y finales</h2>
                            <h3 class="card-title text-base text-base-content">Evaluaciones</h3>

                            @if($evaluaciones->isEmpty())
                                <x-mary-alert title="Todavía no hay evaluaciones registradas para esta carrera." icon="o-information-circle" class="alert-info mt-2" />
                            @else
                                <div class="mt-2 overflow-x-auto">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Materia</th>
                                                <th>Tipo</th>
                                                <th>Fecha</th>
                                                <th class="text-center">Puntaje</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($evaluaciones as $evaluacion)
                                                <tr class="hover">
                                                    <td class="font-medium">{{ $evaluacion->mat_descri }}</td>
                                                    <td>{{ $evaluacion->tev_descri }}</td>
                                                    <td>{{ $evaluacion->evp_fecha ? \Carbon\Carbon::createFromFormat('d/m/Y', $evaluacion->evp_fecha)->format('d/m/Y') : '—' }}</td>
                                                    <td class="text-center">{{ $evaluacion->epi_puntaj }} / {{ $evaluacion->evp_ptotal }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Asistencia --}}
                    <div class="card glass-card">
                        <div class="card-body">
                            <h2 class="mb-1 text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Por materia</h2>
                            <h3 class="card-title text-base text-base-content">Asistencia</h3>

                            @if($asistencias->isEmpty())
                                <x-mary-alert title="No hay registros de asistencia para esta carrera." icon="o-information-circle" class="alert-info mt-2" />
                            @else
                                <div class="mt-2 overflow-x-auto">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Materia</th>
                                                <th>Curso</th>
                                                <th class="text-center">Clases</th>
                                                <th class="text-center">Presencias</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($asistencias as $asistencia)
                                                <tr class="hover">
                                                    <td class="font-medium">{{ $asistencia->mat_descri }}</td>
                                                    <td>{{ $asistencia->cur_descri }}</td>
                                                    <td class="text-center">{{ $asistencia->alu_clase }}</td>
                                                    <td class="text-center">{{ $asistencia->alu_presen }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Deudas --}}
                    <div class="card glass-card">
                        <div class="card-body">
                            <h2 class="mb-1 text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Aranceles</h2>
                            <h3 class="card-title text-base text-base-content">Deudas asociadas</h3>

                            @if($deudas->isEmpty())
                                <x-mary-alert title="No hay deudas pendientes para esta carrera." icon="o-check-circle" class="alert-success mt-2" />
                            @else
                                <div class="mt-2 overflow-x-auto">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Arancel</th>
                                                <th>Vencimiento</th>
                                                <th class="text-right">Saldo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($deudas as $deuda)
                                                <tr class="hover">
                                                    <td class="font-medium">{{ $deuda->aca_descri }}</td>
                                                    <td>{{ $deuda->dit_vencim ? \Carbon\Carbon::createFromFormat('d/m/Y', $deuda->dit_vencim)->format('d/m/Y') : '—' }}</td>
                                                    <td class="text-right font-bold">Gs {{ number_format($deuda->dit_saldo ?? 0, 0, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Extracto académico (ancho completo) --}}
                <div class="card glass-card">
                    <div class="card-body">
                        <h2 class="mb-1 text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Historial de calificaciones</h2>
                        <h3 class="card-title text-base text-base-content">Extracto académico</h3>

                        @if($extracto->isEmpty())
                            <x-mary-alert title="No hay calificaciones registradas para esta carrera." icon="o-information-circle" class="alert-info mt-2" />
                        @else
                            <div class="mt-2 overflow-x-auto">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Materia</th>
                                            <th>Tipo</th>
                                            <th>Periodo</th>
                                            <th>Fecha</th>
                                            <th class="text-center">Nota</th>
                                            <th class="text-center">Situación</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($extracto as $registro)
                                            <tr class="hover">
                                                <td class="font-medium">{{ $registro->mat_descri }}</td>
                                                <td>{{ $registro->tev_descri }}</td>
                                                <td>{{ $registro->act_periodo }}</td>
                                                <td>{{ $registro->act_fecha ? \Carbon\Carbon::createFromFormat('d/m/Y', $registro->act_fecha)->format('d/m/Y') : '—' }}</td>
                                                <td class="text-center font-bold">{{ $registro->cal_notaci ?? '—' }}</td>
                                                <td class="text-center">
                                                    @if($registro->cal_situac == 1)
                                                        <x-mary-badge value="Aprobado" class="badge-success badge-sm" />
                                                    @elseif($registro->cal_situac == 2)
                                                        <x-mary-badge value="Reprobado" class="badge-error badge-sm" />
                                                    @else
                                                        <x-mary-badge value="Pendiente" class="badge-neutral badge-sm" />
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    @endif
</div>
