<?php

use App\Services\AlumnoExternoService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;

new #[Lazy] class extends Component
{
    public int $halId = 0;
    public Collection $evaluaciones;

    public function boot(): void
    {
        $this->evaluaciones = collect();
    }

    public function mount(int $halId, AlumnoExternoService $service): void
    {
        $this->halId = $halId;
        $this->evaluaciones = $service->evaluaciones($halId)
            ->sortByDesc(function (object $evaluacion): int {
                if (blank($evaluacion->evp_fecha ?? null)) {
                    return 0;
                }

                return Carbon::createFromFormat('d/m/Y', $evaluacion->evp_fecha)->timestamp;
            })
            ->values();
    }

    public function placeholder(): string
    {
        return '<div class="skeleton h-64 rounded-[1.5rem]"></div>';
    }
}; ?>

<div class="card glass-card">
    <div class="card-body">
        <h2 class="mb-1 text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Parciales y finales</h2>
        <h3 class="card-title text-base text-base-content">Ultimas evaluaciones</h3>

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
