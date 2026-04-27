<?php

use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?object $alumno = null;
    public Collection $extracto;
    public string $error = '';
    public string $search = '';

    public function boot(): void
    {
        $this->extracto = collect();
    }

    public function mount(AlumnoExternoService $service): void
    {
        $documento = auth()->user()->documento;

        if (! $documento) {
            $this->error = 'Tu cuenta no tiene documento asociado. Contactá al administrador.';
            return;
        }

        $this->alumno = $service->resolverAlumno($documento);

        if (! $this->alumno) {
            $this->error = 'No se encontró un alumno con el documento registrado en tu cuenta.';
            return;
        }

        $this->extracto = $service->extractoAcademico($this->alumno->alu_id);
    }

    public function getExtractoFiltradoProperty()
    {
        if ($this->extracto->isEmpty()) {
            return collect();
        }
        if (! $this->search) {
            return $this->extracto;
        }
        $q = mb_strtolower($this->search);
        return $this->extracto->filter(fn($r) =>
            str_contains(mb_strtolower($r->mat_descri ?? ''), $q) ||
            str_contains(mb_strtolower($r->tev_descri ?? ''), $q) ||
            str_contains(mb_strtolower($r->act_periodo ?? ''), $q)
        );
    }
};
?>

<div>
    <x-mary-header title="Extracto Académico" subtitle="Historial de calificaciones" separator />

    @if($error !== '')
        <x-mary-alert title="{{ $error }}" icon="o-exclamation-triangle" class="alert-warning" />
    @else
        <x-mary-input
            icon="o-magnifying-glass"
            placeholder="Buscar materia, tipo o periodo..."
            wire:model.live.debounce.300ms="search"
            clearable
            class="mb-4"
        />

        @if($this->extractoFiltrado->isEmpty())
            <x-mary-alert title="Sin resultados." icon="o-information-circle" class="alert-info" />
        @else
            <x-mary-card shadow>
                <div class="overflow-x-auto">
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
                            @foreach($this->extractoFiltrado as $r)
                                <tr class="hover">
                                    <td>{{ $r->mat_descri }}</td>
                                    <td>{{ $r->tev_descri }}</td>
                                    <td>{{ $r->act_periodo }}</td>
                                    <td>{{ $r->act_fecha ? \Carbon\Carbon::parse($r->act_fecha)->format('d/m/Y') : '—' }}</td>
                                    <td class="text-center font-bold">{{ $r->cal_notaci ?? '—' }}</td>
                                    <td class="text-center">
                                        @if($r->cal_situac == 1)
                                            <x-mary-badge value="Aprobado" class="badge-success badge-sm" />
                                        @elseif($r->cal_situac == 2)
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
            </x-mary-card>
        @endif
    @endif
</div>
