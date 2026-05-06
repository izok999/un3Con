<?php

use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;

new #[Lazy] class extends Component
{
    public int $aluId = 0;
    public int $rscId = 0;
    public int $periodoId = 0;
    public Collection $asistencias;

    public function boot(): void
    {
        $this->asistencias = collect();
    }

    public function mount(int $aluId, int $rscId, int $periodoId, AlumnoExternoService $service): void
    {
        $this->aluId = $aluId;
        $this->rscId = $rscId;
        $this->periodoId = $periodoId;
        $this->asistencias = $service->asistenciaPorHabilitacion($aluId, $rscId, $periodoId);
    }

    public function placeholder(): string
    {
        return '<div class="skeleton h-64 rounded-[1.5rem]"></div>';
    }
}; ?>

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
