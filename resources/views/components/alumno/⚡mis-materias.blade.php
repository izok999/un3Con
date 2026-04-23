<?php

use Livewire\Component;
use App\Services\AlumnoExternoService;

new class extends Component
{
    public ?object $alumno = null;
    public $materias;
    public string $error = '';

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

        $this->materias = $service->materiasInscriptas($this->alumno->alu_id);
    }
};
?>

<div>
    <x-mary-header title="Materias Inscriptas" subtitle="Periodo vigente" separator />

    @if($error)
        <x-mary-alert title="{{ $error }}" icon="o-exclamation-triangle" class="alert-warning" />
    @elseif($materias?->isEmpty())
        <x-mary-alert title="No tenés materias inscriptas en el periodo actual." icon="o-information-circle" class="alert-info" />
    @else
        <x-mary-card shadow>
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Materia</th>
                            <th>Curso</th>
                            <th>Periodo</th>
                            <th>Turno</th>
                            <th>Sección</th>
                            <th>Sede</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($materias as $m)
                            <tr class="hover">
                                <td class="font-medium">{{ $m->mat_descri }}</td>
                                <td>{{ $m->cur_descri }}</td>
                                <td>
                                    <x-mary-badge value="{{ $m->ple_codigo }}" class="badge-neutral badge-sm" />
                                </td>
                                <td>{{ $m->tur_descri }}</td>
                                <td>{{ $m->sec_descri }}</td>
                                <td>{{ $m->uac_descri }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-mary-card>
    @endif
</div>
