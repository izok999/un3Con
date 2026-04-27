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
    <x-mary-header title="Mis Carreras" subtitle="Habilitaciones vigentes" separator />

    @if($error !== '')
        <x-mary-alert title="{{ $error }}" icon="o-exclamation-triangle" class="alert-warning" />
    @elseif($carreras->isEmpty())
        <x-mary-alert title="No se encontraron carreras activas." icon="o-information-circle" class="alert-info" />
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($carreras as $carrera)
                <x-mary-card shadow class="border border-base-300">
                    <div class="space-y-1">
                        <p class="font-bold text-primary text-lg">{{ $carrera->uac_descri }}</p>
                        <p class="text-sm text-base-content/70">{{ $carrera->pac_descri }} &mdash; {{ $carrera->ciu_descri }}</p>
                        <div class="flex gap-2 mt-2 flex-wrap">
                            <x-mary-badge value="{{ $carrera->ple_codigo }}: {{ $carrera->ple_descri }}" class="badge-neutral" />
                            @if($carrera->hal_vigent)
                                <x-mary-badge value="Vigente" class="badge-success" />
                            @else
                                <x-mary-badge value="No vigente" class="badge-warning" />
                            @endif
                        </div>
                    </div>
                </x-mary-card>
            @endforeach
        </div>
    @endif
</div>
