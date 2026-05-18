<?php

use App\Services\AlumnoExternoService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;

new #[Lazy] class extends Component
{
    public int $aluId = 0;
    public int $rscId = 0;
    public int $periodoId = 0;
    public Collection $deudas;
    public Collection $pagos;
    public Collection $timeline;

    public function boot(): void
    {
        $this->deudas = collect();
        $this->pagos = collect();
        $this->timeline = collect();
    }

    public function mount(int $aluId, int $rscId, int $periodoId, AlumnoExternoService $service): void
    {
        $this->aluId = $aluId;
        $this->rscId = $rscId;
        $this->periodoId = $periodoId;

        $this->deudas = $this->prepareDeudas(
            $service->deudasPorHabilitacion($aluId, $rscId, $periodoId),
        );

        $this->pagos = $this->preparePagos($service->pagosAlumno($aluId));
        $this->timeline = $this->buildTimeline($this->deudas, $this->pagos);
    }

    public function placeholder(): string
    {
        return '<div class="skeleton h-64 rounded-[1.5rem]"></div>';
    }

    protected function prepareDeudas(Collection $deudas): Collection
    {
        return $deudas
            ->map(function (object $deuda): object {
                $deuda->timeline_timestamp = $this->resolveDateTimestamp($deuda->dit_vencim ?? null);
                $deuda->timeline_date = $this->formatDate($deuda->dit_vencim ?? null);

                return $deuda;
            })
            ->sortBy('timeline_timestamp')
            ->values();
    }

    protected function preparePagos(Collection $pagos): Collection
    {
        return $pagos
            ->map(function (object $pago): object {
                $pago->timeline_timestamp = $this->resolveDateTimestamp($pago->cob_fecha ?? null);
                $pago->timeline_date = $this->formatDate($pago->cob_fecha ?? null);

                return $pago;
            })
            ->sortBy('timeline_timestamp')
            ->values();
    }

    protected function buildTimeline(Collection $deudas, Collection $pagos): Collection
    {
        $timelineDeudas = $deudas->map(function (object $deuda): object {
            $referencia = filled($deuda->ple_codigo ?? null)
                ? trim(sprintf('%s · %s', $deuda->ple_codigo, $deuda->ple_descri ?? ''))
                : 'Saldo pendiente';

            return (object) [
                'type' => 'deuda',
                'concept' => $deuda->aca_descri ?? 'Deuda pendiente',
                'date' => $deuda->timeline_date ?? '—',
                'sort_timestamp' => (int) ($deuda->timeline_timestamp ?? PHP_INT_MAX),
                'reference' => $referencia !== '' ? $referencia : 'Saldo pendiente',
                'amount' => (float) ($deuda->dit_saldo ?? 0),
                'unit' => $deuda->uac_descri ?? null,
            ];
        });

        $timelinePagos = $pagos->map(function (object $pago): object {
            $concepto = trim((string) ($pago->cob_arancel ?? $pago->mat_descri ?? 'Pago registrado'));
            $referencia = filled($pago->cob_numero ?? null)
                ? 'Recibo #'.$pago->cob_numero
                : 'Pago registrado';

            if (filled($pago->cob_perceptor ?? null)) {
                $referencia .= ' · '.$pago->cob_perceptor;
            }

            return (object) [
                'type' => 'pago',
                'concept' => $concepto !== '' ? $concepto : 'Pago registrado',
                'date' => $pago->timeline_date ?? '—',
                'sort_timestamp' => (int) ($pago->timeline_timestamp ?? PHP_INT_MAX),
                'reference' => $referencia,
                'amount' => (float) ($pago->cob_monto ?? 0),
                'unit' => $pago->uac_descri ?? null,
            ];
        });

        return $timelineDeudas
            ->merge($timelinePagos)
            ->sortBy('sort_timestamp')
            ->values();
    }

    protected function resolveDateTimestamp(mixed $value): int
    {
        $date = $this->parseDate($value);

        return $date?->timestamp ?? PHP_INT_MAX;
    }

    protected function formatDate(mixed $value): string
    {
        $date = $this->parseDate($value);

        return $date?->format('d/m/Y') ?? '—';
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        $dateValue = trim((string) $value);

        if ($dateValue === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd/m/Y H:i:s', 'Y-m-d H:i:s'] as $format) {
            try {
                return Carbon::createFromFormat($format, $dateValue);
            } catch (Throwable) {
                // Try the next known legacy format.
            }
        }

        try {
            return Carbon::parse($dateValue);
        } catch (Throwable) {
            return null;
        }
    }
}; ?>

<div class="card glass-card">
    <div class="card-body">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="mb-1 text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Orden cronologico</h2>
                <h3 class="card-title text-base text-base-content">Estado de cuenta</h3>
            </div>

            <div class="flex flex-wrap gap-2 text-xs">
                <span class="badge badge-warning badge-sm">{{ $deudas->count() }} deudas de esta carrera</span>
                <span class="badge badge-success badge-sm">{{ $pagos->count() }} pagos legacy del alumno</span>
            </div>
        </div>

        <p class="mt-2 text-xs text-base-content/60">
            Las deudas se filtran por esta carrera. Los pagos vienen del historial general legado del alumno.
        </p>

        @if($timeline->isEmpty())
            <x-mary-alert title="No hay movimientos financieros visibles para esta carrera." icon="o-check-circle" class="alert-success mt-2" />
        @else
            <div class="mt-2 overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Concepto</th>
                            <th>Fecha</th>
                            <th>Referencia</th>
                            <th class="text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($timeline as $movimiento)
                            <tr class="hover">
                                <td>
                                    @if($movimiento->type === 'pago')
                                        <x-mary-badge value="Pago" class="badge-success badge-sm" />
                                    @else
                                        <x-mary-badge value="Deuda" class="badge-warning badge-sm" />
                                    @endif
                                </td>
                                <td>
                                    <div class="font-medium">{{ $movimiento->concept }}</div>
                                    @if(filled($movimiento->unit ?? null))
                                        <div class="text-xs text-base-content/55">{{ $movimiento->unit }}</div>
                                    @endif
                                </td>
                                <td>{{ $movimiento->date }}</td>
                                <td>{{ $movimiento->reference }}</td>
                                <td class="text-right font-bold {{ $movimiento->type === 'pago' ? 'text-success' : 'text-error' }}">
                                    Gs {{ number_format($movimiento->amount, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
