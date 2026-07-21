<?php

use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?object $alumno = null;
    public Collection $materias;
    public string $error = '';

    public function boot(): void
    {
        $this->materias = collect();
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

        $this->materias = $service->materiasInscriptas($this->alumno->alu_id);
    }
};
?>

<div>
    <x-slot name="header">Materias Inscriptas</x-slot>

    <x-mary-header title="Materias Inscriptas" subtitle="Periodo vigente" icon="o-book-open" separator />

    @if($error !== '')
        <x-mary-alert title="{{ $error }}" icon="o-exclamation-triangle" class="alert-warning" />
    @elseif($materias->isEmpty())
        <x-mary-alert title="No tenés materias inscriptas en el periodo actual." icon="o-information-circle" class="alert-info" />
    @else
        <div class="card glass-card overflow-hidden">
            {{-- Franja de acento institucional --}}
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-secondary to-accent"></div>

            <div class="card-body gap-4">
                {{-- Mini-stat: conteo de materias --}}
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10">
                        <x-icon name="o-book-open" class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-base-content/45">Total</p>
                        <p class="text-2xl font-bold text-primary">
                            {{ $materias->count() }}
                            <span class="text-sm font-normal text-base-content/60">
                                {{ $materias->count() === 1 ? 'materia inscripta' : 'materias inscriptas' }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="divider my-0"></div>

                {{-- Tabla --}}
                <div class="overflow-x-auto rounded-2xl border border-base-300/60">
                    <table class="table table-sm">
                        <thead>
                            <tr class="bg-base-200/70">
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/55">Materia</th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/55">Curso</th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/55">Periodo</th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/55">Turno</th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/55">Sección</th>
                                <th class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/55">Sede</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($materias as $m)
                                <tr class="hover:bg-base-200/50 transition">
                                    <td class="font-semibold text-primary">{{ $m->mat_descri }}</td>
                                    <td class="text-base-content/80">{{ $m->cur_descri }}</td>
                                    <td>
                                        <span class="badge badge-outline badge-primary badge-sm">{{ $m->ple_codigo }}</span>
                                    </td>
                                    <td class="text-base-content/80">{{ $m->tur_descri }}</td>
                                    <td class="text-base-content/80">{{ $m->sec_descri }}</td>
                                    <td class="text-base-content/80">{{ $m->uac_descri }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
