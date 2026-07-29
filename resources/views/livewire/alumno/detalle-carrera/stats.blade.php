<?php

use App\Services\AlumnoExternoService;
use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;

new #[Lazy] class extends Component
{
    public int $aluId = 0;
    public int $halId = 0;
    public int $rscId = 0;
    public int $periodoId = 0;

    public int $materiasCount = 0;
    public float $totalDeuda = 0;
    public ?float $promedio = null;

    public int $aplazos = 0;
    public ?int $limiteAplazos = null;
    public ?float $porcentajeAplazos = null;

    public function mount(int $aluId, int $halId, int $rscId, int $periodoId, AlumnoExternoService $service): void
    {
        $this->aluId = $aluId;
        $this->halId = $halId;
        $this->rscId = $rscId;
        $this->periodoId = $periodoId;

        $materias = $service->materiasPorHabilitacion($aluId, $halId, $rscId);
        $deudas = $service->deudasPorHabilitacion($aluId, $rscId, $periodoId);

        $this->materiasCount = $materias->count();
        $this->totalDeuda = (float) $deudas->sum('dit_saldo');

        $this->promedio = $service->promedioPorHabilitacion($halId);

        // Comparte caché con la tarjeta de aplazos del resumen académico.
        $aplazos = $service->aplazosPorHabilitacion($aluId, $halId);
        $this->aplazos = $aplazos['aplazos'];
        $this->limiteAplazos = $aplazos['limite'];
        $this->porcentajeAplazos = $aplazos['porcentaje'];
    }

    /**
     * El legacy bloquea recién cuando los aplazos superan el tope (comparación estricta
     * en sp_get_verifica_limite_aplazos), igual que la tarjeta del resumen académico.
     */
    public function claseValorAplazos(): string
    {
        return match (true) {
            $this->porcentajeAplazos === null => 'text-accent',
            $this->limiteAplazos !== null && $this->aplazos > $this->limiteAplazos => 'text-error',
            $this->porcentajeAplazos >= 75 => 'text-warning',
            default => 'text-success',
        };
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            <div class="skeleton h-28 rounded-[1.5rem]"></div>
            <div class="skeleton h-28 rounded-[1.5rem]"></div>
            <div class="skeleton h-28 rounded-[1.5rem]"></div>
            <div class="skeleton h-28 rounded-[1.5rem]"></div>
        </div>
        HTML;
    }
}; ?>

<div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
    <div class="stat glass-card">
        <div class="stat-figure text-primary">
            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
        </div>
        <div class="stat-title text-xs">Materias vigentes</div>
        <div class="stat-value text-2xl text-primary">{{ $materiasCount }}</div>
    </div>
    <div class="stat glass-card" data-testid="stat-promedio">
        <div class="stat-figure text-secondary">
            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" /></svg>
        </div>
        <div class="stat-title text-xs">Promedio de la carrera</div>
        <div class="stat-value text-2xl text-secondary">
            {{ $promedio !== null ? number_format($promedio, 2, ',', '.') : '—' }}
        </div>
        @if($promedio !== null)
            <div class="stat-desc text-xs">sobre las calificaciones del extracto</div>
        @endif
    </div>
    <div class="stat glass-card">
        <div class="stat-figure text-error">
            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>
        </div>
        <div class="stat-title text-xs">Total pendiente</div>
        <div class="stat-value text-lg text-error">Gs {{ number_format($totalDeuda, 0, ',', '.') }}</div>
    </div>
    <div class="stat glass-card" data-testid="stat-aplazos">
        <div class="stat-figure {{ $this->claseValorAplazos() }}">
            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
        </div>
        <div class="stat-title text-xs">Aplazos</div>
        <div class="stat-value text-2xl {{ $this->claseValorAplazos() }}">
            @if($porcentajeAplazos !== null)
                {{ number_format($porcentajeAplazos, 1, ',', '.') }}%
            @else
                {{ $aplazos }}
            @endif
        </div>
        <div class="stat-desc text-xs">
            @if($limiteAplazos !== null)
                {{ $aplazos }} de {{ $limiteAplazos }} permitidos por la malla
            @else
                {{ $aplazos === 1 ? 'aplazo acumulado' : 'aplazos acumulados' }} · tope no disponible
            @endif
        </div>
    </div>
</div>
