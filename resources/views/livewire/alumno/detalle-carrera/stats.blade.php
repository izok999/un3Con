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
    public int $evaluacionesCount = 0;
    public float $totalDeuda = 0;
    public int $promedioAsistencia = 0;

    public function mount(int $aluId, int $halId, int $rscId, int $periodoId, AlumnoExternoService $service): void
    {
        $this->aluId = $aluId;
        $this->halId = $halId;
        $this->rscId = $rscId;
        $this->periodoId = $periodoId;

        $materias = $service->materiasPorHabilitacion($aluId, $halId, $rscId);
        $evaluaciones = $service->evaluaciones($halId);
        $deudas = $service->deudasPorHabilitacion($aluId, $rscId, $periodoId);
        $asistencias = $service->asistenciaPorHabilitacion($aluId, $rscId, $periodoId);

        $this->materiasCount = $materias->count();
        $this->evaluacionesCount = $evaluaciones->count();
        $this->totalDeuda = (float) $deudas->sum('dit_saldo');

        $clases = (int) $asistencias->sum('alu_clase');
        $this->promedioAsistencia = $clases === 0
            ? 0
            : (int) round(($asistencias->sum('alu_presen') / $clases) * 100);
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
    <div class="stat glass-card">
        <div class="stat-figure text-secondary">
            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" /></svg>
        </div>
        <div class="stat-title text-xs">Evaluaciones</div>
        <div class="stat-value text-2xl text-secondary">{{ $evaluacionesCount }}</div>
    </div>
    <div class="stat glass-card">
        <div class="stat-figure text-error">
            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>
        </div>
        <div class="stat-title text-xs">Total pendiente</div>
        <div class="stat-value text-lg text-error">Gs {{ number_format($totalDeuda, 0, ',', '.') }}</div>
    </div>
    <div class="stat glass-card">
        <div class="stat-figure text-accent">
            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
        </div>
        <div class="stat-title text-xs">Asistencia</div>
        <div class="stat-value text-2xl text-accent">{{ $promedioAsistencia }}%</div>
    </div>
</div>
