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

    public function boot(): void
    {
        $this->extracto = collect();
    }

    public function mount(int $aluId, int $halId, AlumnoExternoService $service): void
    {
        $this->aluId = $aluId;
        $this->halId = $halId;
        $this->extracto = $service->extractoImpresionPorHabilitacion($aluId, $halId);
    }

    public function placeholder(): string
    {
        return '<div class="skeleton h-80 rounded-[1.5rem]"></div>';
    }
}; ?>

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
