<?php

use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?object $alumno = null;
    public Collection $carreras;
    public string $error = '';

    public function boot(): void
    {
        $this->carreras = collect();
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

        $this->carreras = $service->carreras($this->alumno->alu_id);
    }
};
?>

<div>
    <x-slot name="header">Mis Carreras</x-slot>

    <x-mary-header title="Mis Carreras" subtitle="Habilitaciones vigentes" icon="o-building-library" separator />

    @if($error !== '')
        <x-mary-alert title="{{ $error }}" icon="o-exclamation-triangle" class="alert-warning" />
    @elseif($carreras->isEmpty())
        <x-mary-alert title="No se encontraron carreras activas o históricas vinculadas a tu documento." icon="o-information-circle" class="alert-info" />
    @else
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @foreach($carreras as $carrera)
                @include('partials.alumno.carrera-card', ['carrera' => $carrera])
            @endforeach
        </div>
    @endif
</div>
