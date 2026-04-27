<?php

use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?object $alumno = null;
    public Collection $deudas;
    public string $error = '';

    public function boot(): void
    {
        $this->deudas = collect();
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

        $this->deudas = $service->deudas($this->alumno->alu_id);
    }

    public function getTotalProperty(): float|int
    {
        return $this->deudas?->sum('dit_saldo') ?? 0;
    }
};
?>

<div>
    <x-mary-header title="Mis Deudas" subtitle="Saldos pendientes de aranceles" separator />

    @if($error !== '')
        <x-mary-alert title="{{ $error }}" icon="o-exclamation-triangle" class="alert-warning" />
    @elseif($deudas->isEmpty())
        <x-mary-alert title="No tenés deudas pendientes." icon="o-check-circle" class="alert-success" />
    @else
        <div class="mb-4">
            <x-mary-stat
                title="Total pendiente"
                value="Gs {{ number_format($this->total, 0, ',', '.') }}"
                icon="o-banknotes"
                class="bg-error/10 text-error"
            />
        </div>

        <x-mary-card shadow>
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Arancel</th>
                            <th>Carrera</th>
                            <th>Periodo</th>
                            <th>Vencimiento</th>
                            <th class="text-right">Saldo</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deudas as $d)
                            <tr class="hover">
                                <td>{{ $d->aca_descri }}</td>
                                <td>{{ $d->uac_descri }}</td>
                                <td>{{ $d->ple_codigo }}: {{ $d->ple_descri }}</td>
                                <td>
                                    {{ $d->dit_vencim ? \Carbon\Carbon::parse($d->dit_vencim)->format('d/m/Y') : '&mdash;' }}
                                </td>
                                <td class="text-right font-bold">
                                    Gs {{ number_format($d->dit_saldo ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="text-center">
                                    @if($d->deu_situac === 'P')
                                        <x-mary-badge value="Pendiente" class="badge-warning badge-sm" />
                                    @elseif($d->deu_situac === 'C')
                                        <x-mary-badge value="Cancelado" class="badge-success badge-sm" />
                                    @else
                                        <x-mary-badge value="{{ $d->deu_situac }}" class="badge-neutral badge-sm" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-mary-card>
    @endif
</div>
