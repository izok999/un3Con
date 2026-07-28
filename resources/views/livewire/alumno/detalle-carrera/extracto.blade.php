<?php

use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;

new #[Lazy] class extends Component
{
    public int $aluId = 0;
    public int $halId = 0;
    public Collection $extracto;

    public int $aplazos = 0;
    public ?int $limiteAplazos = null;
    public ?float $porcentajeAplazos = null;

    public function boot(): void
    {
        $this->extracto = collect();
    }

    public function mount(int $aluId, int $halId, AlumnoExternoService $service): void
    {
        $this->aluId = $aluId;
        $this->halId = $halId;
        $this->extracto = $service->extractoImpresionPorHabilitacion($aluId, $halId);

        // Se resuelve después del extracto para que reutilice su caché en vez de repetir la consulta.
        $aplazos = $service->aplazosPorHabilitacion($aluId, $halId);

        $this->aplazos = $aplazos['aplazos'];
        $this->limiteAplazos = $aplazos['limite'];
        $this->porcentajeAplazos = $aplazos['porcentaje'];
    }

    /**
     * El legacy solo bloquea cuando los aplazos superan el tope (sp_get_verifica_limite_aplazos
     * compara con >), así que estar justo en el límite todavía no es infracción.
     */
    public function superaElTopeDeAplazos(): bool
    {
        return $this->limiteAplazos !== null && $this->aplazos > $this->limiteAplazos;
    }

    /**
     * Color del indicador según cuánto del tope de aplazos ya se consumió.
     * Las clases se escriben completas para que Tailwind las detecte al compilar.
     */
    public function claseTextoAplazos(): string
    {
        return match (true) {
            $this->porcentajeAplazos === null => 'text-base-content',
            $this->superaElTopeDeAplazos() => 'text-error',
            $this->porcentajeAplazos >= 75 => 'text-warning',
            default => 'text-success',
        };
    }

    public function claseProgresoAplazos(): string
    {
        return match (true) {
            $this->porcentajeAplazos === null => 'progress-neutral',
            $this->superaElTopeDeAplazos() => 'progress-error',
            $this->porcentajeAplazos >= 75 => 'progress-warning',
            default => 'progress-success',
        };
    }

    public function placeholder(): string
    {
        return '<div class="skeleton h-80 rounded-[1.5rem]"></div>';
    }
}; ?>

<div class="space-y-3">
<div class="card glass-card">
    <div class="card-body">
        <h2 class="mb-1 text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Situación de aplazos</h2>
        <h3 class="card-title text-base text-base-content">Aplazos acumulados en la carrera</h3>

        <div class="mt-3 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl bg-base-200/70 p-3">
                <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">Aplazos</p>
                <p class="mt-1 text-2xl font-semibold {{ $this->claseTextoAplazos() }}">{{ $aplazos }}</p>
            </div>
            <div class="rounded-2xl bg-base-200/70 p-3">
                <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">Tope de la malla</p>
                <p class="mt-1 text-2xl font-semibold text-base-content">{{ $limiteAplazos ?? '—' }}</p>
            </div>
            <div class="rounded-2xl bg-base-200/70 p-3">
                <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">Tope consumido</p>
                <p class="mt-1 text-2xl font-semibold {{ $this->claseTextoAplazos() }}">
                    {{ $porcentajeAplazos !== null ? number_format($porcentajeAplazos, 1, ',', '.').'%' : '—' }}
                </p>
            </div>
        </div>

        @if($porcentajeAplazos !== null)
            <progress
                class="progress {{ $this->claseProgresoAplazos() }} mt-3 w-full"
                value="{{ min($porcentajeAplazos, 100) }}"
                max="100"
            ></progress>

            @if($this->superaElTopeDeAplazos())
                <x-mary-alert title="Superaste el tope de aplazos permitido por tu malla curricular. Acercate a Secretaría." icon="o-exclamation-triangle" class="alert-error mt-2" />
            @elseif($aplazos === $limiteAplazos)
                <x-mary-alert title="Alcanzaste el tope de aplazos de tu malla curricular. Un aplazo más te deja fuera de plazo." icon="o-exclamation-triangle" class="alert-warning mt-2" />
            @endif
        @else
            <p class="mt-3 text-sm text-base-content/60">
                El tope de aplazos de la malla no está disponible para esta carrera.
            </p>
        @endif
    </div>
</div>

<div class="card glass-card">
    <div class="card-body">
        <h2 class="mb-1 text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Progreso de la carrera</h2>
        <h3 class="card-title text-base text-base-content">Resumen académico</h3>

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
                            @if(isset($registro->cur_print) && $registro->cur_print)
                                <tr class="bg-base-200/60">
                                    <td colspan="6" class="py-2">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-base-content">{{ $registro->cur_descri }}</span>
                                            @if(isset($registro->cur_completo) && $registro->cur_completo)
                                                <x-mary-badge value="Completado" class="badge-success badge-sm" />
                                            @else
                                                <x-mary-badge value="En curso" class="badge-info badge-sm" />
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @else
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
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>


<div class="card glass-card">
    <div class="card-body">
        <h2 class="mb-1 text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Historial de calificaciones</h2>
        <h3 class="card-title text-base text-base-content">Resumen académico</h3>

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
</div>
