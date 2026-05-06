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
    public Collection $deudas;

    public function boot(): void
    {
        $this->deudas = collect();
    }

    public function mount(int $aluId, int $rscId, int $periodoId, AlumnoExternoService $service): void
    {
        $this->aluId = $aluId;
        $this->rscId = $rscId;
        $this->periodoId = $periodoId;
        $this->deudas = $service->deudasPorHabilitacion($aluId, $rscId, $periodoId);
    }

    public function placeholder(): string
    {
        return '<div class="skeleton h-64 rounded-[1.5rem]"></div>';
    }
}; ?>

<div class="card glass-card">
    <div class="card-body">
        <h2 class="mb-1 text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Aranceles</h2>
        <h3 class="card-title text-base text-base-content">Deudas asociadas</h3>

        @if($deudas->isEmpty())
            <x-mary-alert title="No hay deudas pendientes para esta carrera." icon="o-check-circle" class="alert-success mt-2" />
        @else
            <div class="mt-2 overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Arancel</th>
                            <th>Vencimiento</th>
                            <th class="text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deudas as $deuda)
                            <tr class="hover">
                                <td class="font-medium">{{ $deuda->aca_descri }}</td>
                                <td>{{ $deuda->dit_vencim ? \Carbon\Carbon::createFromFormat('d/m/Y', $deuda->dit_vencim)->format('d/m/Y') : '—' }}</td>
                                <td class="text-right font-bold">Gs {{ number_format($deuda->dit_saldo ?? 0, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
