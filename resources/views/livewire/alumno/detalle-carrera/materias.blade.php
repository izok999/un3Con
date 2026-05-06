<?php

use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;

new #[Lazy] class extends Component
{
    public int $aluId = 0;
    public int $halId = 0;
    public int $rscId = 0;
    public Collection $materias;

    public function boot(): void
    {
        $this->materias = collect();
    }

    public function mount(int $aluId, int $halId, int $rscId, AlumnoExternoService $service): void
    {
        $this->aluId = $aluId;
        $this->halId = $halId;
        $this->rscId = $rscId;
        $this->materias = $service->materiasPorHabilitacion($aluId, $halId, $rscId);
    }

    public function placeholder(): string
    {
        return '<div class="skeleton h-64 rounded-[1.5rem]"></div>';
    }
}; ?>

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
